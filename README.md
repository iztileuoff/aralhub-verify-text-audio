# Aralhub — Verify Text & Audio

Laravel 12 backend for the Aralhub speech-dataset workflow: speakers record audio for texts,
moderators verify it, and verified audio is exported as a `.tsv` dataset. Audio durations are
computed by an **external Python script** and imported back, and every export is tracked so the
pipeline can be re-run safely on continuously growing data.

> API: `api.verify.aralhub.uz` (Fastpanel). Tech stack: PHP 8.4, Laravel 12, Sanctum, FastExcel,
> Yandex S3 (flysystem), SQLite/MySQL.

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
| `--name`      | _(none)_              | Optional label stored on the created `Export`.                     |
| `--all`       | off                   | Re-dump **everything** ready, ignoring `exported_at` (see below).  |

- **Selects:** `is_correct = true` **AND** `text.file_id = {file-id}` **AND**
  `edit_converted_audio_duration IS NOT NULL` **AND** (without `--all`) `exported_at IS NULL`.
- **TSV columns** (tab-separated, no header):
  `text_id`, `<basename>.wav`, `edit_original_transcript`, `edit_normalized_transcript`,
  `edit_tokenized_transcript`, `edit_converted_audio_duration`, `edit_speaker_gender`.
- **Tracking:** a normal run (with new rows) creates a new `Export` record (`filename`,
  `exported_count`, optional `name`) and stamps each exported audio with `exported_at = now()` and
  `export_id = <new export>`.
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

## Data model notes

`audio` table additions used by the pipeline:

| Column                          | Meaning                                                                 |
| ------------------------------- | ----------------------------------------------------------------------- |
| `edit_converted_audio_duration` | Converted-audio duration; `NULL` until Python computes it.              |
| `exported_at`                   | When the audio was exported. Acts as the "already exported" gate.       |
| `export_id` → `exports.id`      | Which export batch the audio belongs to (grouping label).               |

`exports` table: `id`, `name`, `filename`, `exported_count`, timestamps. Each `audio:export-correct`
run creates one record (`Export hasMany Audio`).

---

## Deployment

Production runs on Fastpanel. The `.deploy.sh` script (run on the server) pulls `main`, installs
dependencies, runs `migrate --force`, rebuilds caches and restarts the queue. See the comments in
`.deploy.sh` for binary overrides.

After the **first** deploy that ships the export feature, run the one-time grouping backfill:

```bash
php artisan export:seed-groups
```

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
