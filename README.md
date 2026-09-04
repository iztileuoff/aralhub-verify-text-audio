# Aralhub — Verify Text & Audio

Laravel 12 backend for the Aralhub speech-dataset workflow: speakers record audio for texts,
moderators verify it, and verified audio is exported as a `.tsv` dataset. Audio durations are
computed by an **external Python script** and imported back, and every export is tracked so the
pipeline can be re-run safely on continuously growing data.

> API: `api.verify.aralhub.uz` (Fastpanel). Tech stack: PHP 8.4, Laravel 12, Sanctum, FastExcel,
> S3-compatible object storage (flysystem), SQLite/MySQL.

---

## Where recordings are stored

New recordings go to the disk named by `AUDIO_DISK` (`config/audio.php`), and every `audio` /
`texts` row keeps the disk it was written to in `storage_disk`. Rows with `storage_disk = NULL`
predate the column and are read from `AUDIO_LEGACY_DISK`.

That is what makes the object storage swappable: changing `AUDIO_DISK` only affects uploads from
that moment on, while everything already uploaded keeps being read — and deleted — from the disk
it actually lives on. No file migration is required to switch providers.

Configured disks are in `config/filesystems.php`: `r2` (Cloudflare R2 — in use since the switch;
same S3 driver, `region=auto`, no per-object ACLs, so the bucket must be published through an
`r2.dev` subdomain or a custom domain set as `R2_URL`) and `yandex-s3`, which now only serves the
recordings made before it — hence `AUDIO_LEGACY_DISK=yandex-s3`.

⚠️ The external Python script fetches originals by public URL built from `id;filename`, so when
`AUDIO_DISK` changes it needs the new base URL as well.

⚠️ `audio:delete` and `audio:delete-incorrect` used to call `Storage::delete()` with no disk, which
resolves to the default `local` disk — so they dropped the database rows but never removed anything
from object storage. Both now go through `Audio::deleteStoredFiles()` and really delete. Files
orphaned by earlier runs are still in the bucket and have to be swept separately.

---

## Datasets

Every text belongs to a `File` — one uploaded dataset. Exactly one of them is **active**, and all
work queues, counters and reports are scoped to it: `DATASET_MAIN_FILE_ID` (`config/dataset.php`,
read by `Text::scopeMainFile()` and `Audio::scopeMainFile()`). Switching datasets is a change of
that variable followed by `optimize:clear` — `config:cache` alone leaves the dashboard cache stale.

`files.label` is the human-readable name of a dataset (`v3`), used to tell datasets apart in the
admin panel and to name their export groups. It is set at import time and editable afterwards:

```bash
php artisan import:excel-file 207.xlsx --label="v3"              # new dataset
php artisan import:excel-file 208.xlsx --file-id=10 --label="v3" # next batch of the same one
```

⚠️ A batch cut out of a master sheet often arrives **without a header row**, and then its
first data row is read as the header — the `text` column is never found and the row is lost.
`import:excel-file` refuses such a file; add the header
(`id, role, intent, text, script, style, contains, words, needs_native_review`) and re-run.

```
PATCH /api/v1/admin/files/{file}   { "label": "v3" }   # rename; null clears it
GET   /api/v1/admin/dataset                            # the active dataset, without paging the list
GET   /api/v1/admin/files                              # every dataset, each with is_active
```

`is_active` is **derived** from `DATASET_MAIN_FILE_ID`, not stored — a stored flag could drift out
of step with the config and make the admin panel show one dataset while every query used another.

---

## Recording rules

Two rules shape what the speaker queue (`GET admin/verify/speak/text`) hands out:

1. **One speaker records a text once.** The queue never offers a text the speaker already
   has an audio for, so every recording of a text is a different voice.
2. **A text collects at most `DATASET_MAX_AUDIO_PER_TEXT` recordings** (`config/dataset.php`,
   default **3**). Texts nobody has read come first; only when there are none left does the
   queue fall back to the lowest audio count still under the limit.

Rule 1 is enforced on the way in as well, and that is the part that matters in production:
uploading an audio takes up to a minute, so clients resend `POST speak/text/{text}/audio/complete`
after a timeout or a double tap. Such a retry is answered with the saved state (200) instead of
creating a second take — before this guard existed it produced 557 duplicate
text+speaker pairs, 550 of them within five minutes of each other.

---

## Audio export pipeline

The dataset is produced in a repeatable cycle. Step 2 runs in a separate Python app; everything
else is Artisan commands. All files live in `storage/app/`.

```
┌─ 1. audio:export-filenames ─┐      ┌─ 3. update:duration ─┐      ┌─ 4. audio:export-correct ─┐
│  candidates → id;filename   │      │  result.txt → fill   │      │  ready & new → .tsv       │
│  audio_filenames.txt        │      │  edit_converted_     │      │  + create Export          │
└──────────────┬──────────────┘      │  audio_duration      │      │  + stamp exported_at /    │
               │                      └──────────▲───────────┘      │    export_id              │
               ▼                                 │                  └───────────────────────────┘
        2. (external) Python computes durations  │
           reads each file, echoes the id  ──────┘
           result.txt: id;duration
```

Why it is safe under realtime recording: linkage is by **database flags**, not by a snapshot.
An audio is exported only after Python returns a duration for it; audios recorded mid-cycle have
`edit_converted_audio_duration = NULL` and simply wait for the next cycle. `exported_at` guarantees
nothing is exported twice and nothing ready is missed.

Which dataset the pipeline works on is the **active dataset**: `config('dataset.main_file_id')`,
set by `DATASET_MAIN_FILE_ID` in `.env` (default `8`). Every command below defaults to it; passing
`--file-id` explicitly is how the backlog of a previous dataset gets finished off after a switch.

### Run order

```bash
# 1. Produce the work list for Python (id;filename per line)
php artisan audio:export-filenames --filename=audio_filenames.txt

#    → Python reads audio_filenames.txt, writes storage/app/result.txt (id;duration per line)

# 3. Import the durations (matched by id)
php artisan update:duration --filename=result.txt

# 4. Export the new, ready audios to .tsv (creates an Export record)
php artisan audio:export-correct --filename=correct_audios.tsv --name="batch 2026-06"

# Finishing off the backlog of a previous dataset: pass its id explicitly
php artisan audio:export-correct --file-id=8 --name="v2 final"
```

---

## Commands reference

### `audio:export-filenames`

Dumps the audios that still need a duration so the Python script can process them.

| Option        | Default                | Description                                  |
| ------------- | ---------------------- | -------------------------------------------- |
| `--filename`  | `audio_filenames.txt`  | Output file under `storage/app/`.            |
| `--file-id`   | active dataset         | Only audios whose `text.file_id` matches.    |

- **Selects:** `is_correct = true` **AND** `text.file_id = {file-id}` **AND** `edit_converted_audio_duration IS NULL`.
- **Output:** one line per audio — `id;filename`, where `filename` is `edit_audio_filename` without
  the `audio/` prefix (e.g. `42;clip_001.mp3`). The `id` lets the Python result map back unambiguously.

### `update:duration`

Imports the durations produced by Python.

| Option        | Default        | Description                          |
| ------------- | -------------- | ------------------------------------ |
| `--filename`  | `result.txt`   | Input file under `storage/app/`.     |

- **Input:** one line per audio — `id;duration` (e.g. `42;1530`).
- **Effect:** `Audio::find(id)` and sets `edit_converted_audio_duration`, **only if it is currently
  `NULL`** (never overwrites). Unknown ids are skipped and logged. Matching by primary key avoids the
  filename-collision / SQL `LIKE` wildcard pitfalls.

### `audio:export-correct`

Exports verified audios to a tab-separated dataset and records the export.

| Option        | Default               | Description                                                         |
| ------------- | --------------------- | ------------------------------------------------------------------ |
| `--filename`  | `correct_audios.tsv`  | Output file under `storage/app/`.                                  |
| `--file-id`   | active dataset        | Only audios whose `text.file_id` matches.                          |
| `--name`      | `<label> batch YYYY-MM` | Export group name; the default is built from the dataset's `label`. |
| `--all`       | off                   | Re-dump **everything** ready, ignoring `exported_at` (see below).  |

- **Selects:** `is_correct = true` **AND** `text.file_id = {file-id}` **AND**
  `edit_converted_audio_duration IS NOT NULL` **AND** (without `--all`) `exported_at IS NULL`.
- **TSV columns** (tab-separated, no header):
  `text_id`, `<basename>.wav`, `edit_original_transcript`, `edit_normalized_transcript`,
  `edit_tokenized_transcript`, `edit_converted_audio_duration`, `edit_speaker_gender`.
- **Tracking:** a normal run (with new rows) creates a new `Export` record (`filename`,
  `exported_count`, `name`) and stamps each exported audio with `exported_at = now()` and
  `export_id = <new export>`.
- **Group naming:** without `--name` the group is named after the dataset and the month —
  `"v3 batch 2026-08"` for a file labelled `v3`, `"file 8 batch 2026-08"` for one with no label.
  That is the convention; `--name` overrides it for one-off exports.
- **`--all`:** a raw re-dump for ad-hoc needs — it writes the `.tsv` but does **not** create an
  `Export` or touch `exported_at` / `export_id`, so history is preserved.

### `export:seed-groups`

One-time backfill that organises pre-existing exported audios into export groups. Idempotent.

| Option             | Default | Description                                            |
| ------------------ | ------- | ------------------------------------------------------ |
| `--legacy-file-id` | `8`     | Threshold `N`: `file_id < N` is legacy, `file_id = N` is its own group. |

- Creates `Export` "Legacy (file_id < N)" and "file_id = N" (via `firstOrCreate`).
- Assigns `export_id` to already-exported audios (`exported_at IS NOT NULL`) by `file_id`, only where
  `export_id` is still `NULL`, then refreshes each export's `exported_count`.
- Run it **once on a fresh `exports` table, before any new `audio:export-correct` runs**, so the
  legacy group gets id `1` and the `file_id = 8` group gets id `2`.

---

### `audio:export-speaker-manifest`

Dumps **every verified audio of every dataset** with the speaker who read it and where its file
lives. It exists for the `kaa-tts-voices` project (per-speaker voice sets for TTS): the export
`.tsv` has no speaker column and filenames are random hashes, so the speaker → audio link is
only reachable through the database. Read-only — no `Export`, no `exported_at`.

| Option        | Default                 | Description                       |
| ------------- | ----------------------- | --------------------------------- |
| `--filename`  | `speaker_manifest.tsv`  | Output file under `storage/app/`. |

- **Selects:** `is_correct = true` across **all** `file_id`s (deliberately not scoped to the
  active dataset — see `docs/adr/0001-speaker-manifest-spans-all-datasets.md`); split parents
  (`split_status = SPLIT`) are dropped, their parts are kept.
- **Output:** tab-separated **with a header row**: `audio_id`, `speaker_id`, `gender`, `age`,
  `file_id`, `text_id`, `is_split_part`, `parent_audio_id`, `audio_filename`, `storage_disk`,
  `audio_url`, `converted_filename`, `duration_samples`, `duration_s`, `recorded_at`,
  `moderated_at`, `exported_at`, `export_id`, `transcript_original`, `transcript_normalized`.
  `storage_disk` is resolved per row (`NULL` → `AUDIO_LEGACY_DISK`), `audio_url` is built from
  that disk's configured base URL. No names or phone numbers — `speaker_id` is enough to look a
  person up in the admin panel.
- ⚠️ For recordings converted in March–April 2026 the old date-range `.tsv`s carry the
  **converted** filename (`audio_conv/…`), which differs from the original — match those on
  `converted_filename`, everything later on `audio_filename`.

## Data model notes

`audio` table additions used by the pipeline:

| Column                          | Meaning                                                                 |
| ------------------------------- | ----------------------------------------------------------------------- |
| `edit_converted_audio_duration` | Converted-audio duration; `NULL` until Python computes it.              |
| `exported_at`                   | When the audio was exported. Acts as the "already exported" gate.       |
| `export_id` → `exports.id`      | Which export batch the audio belongs to (grouping label).               |

`exports` table: `id`, `name`, `filename`, `exported_count`, timestamps. Each `audio:export-correct`
run creates one record (`Export hasMany Audio`).

`files` table: the uploaded datasets. `label` is the human-readable name; `is_active` is not a
column but an accessor comparing `id` with `config('dataset.main_file_id')`.

---

## Deployment

Production runs on Fastpanel. The `.deploy.sh` script (run on the server) pulls `main`, installs
dependencies, runs `migrate --force`, rebuilds caches and restarts the queue. See the comments in
`.deploy.sh` for binary overrides.

After the **first** deploy that ships the export feature, run the one-time grouping backfill:

```bash
php artisan export:seed-groups
```

### Monitoring (Pulse)

The dashboard is at `/pulse`, behind the `viewPulse` gate — super admins and admins only, same
as `/login` (phone + password) and the `/dashboard` page that links to it. Requests, slow
queries, exceptions and cache stats are recorded by the app itself; nothing extra runs for them.

The **Servers** card is the exception: it needs `pulse:check`, and on this host there is no
Supervisor access, so it runs from the `fastuser` crontab once a minute instead of as a daemon:

```cron
* * * * * cd /var/www/fastuser/data/www/api.verify.aralhub.uz && /opt/php8.4/bin/php artisan pulse:check --once >/dev/null 2>&1
```

That gives the card one data point per minute rather than a continuous stream. Remove the entry
and the card goes blank while the rest of Pulse keeps working. The queue worker runs under
Supervisor (`api-verify-queue-worker`); this app has no `schedule:run` cron because it has no
scheduled tasks.

---

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev      # serve + queue listener + vite
```

Run the tests with:

```bash
php artisan test            # or: vendor/bin/pest
vendor/bin/pint             # code style
```
