# ADR 0001 — `audio:export-speaker-manifest` spans all datasets

**Status:** accepted, 2026-09-04.

## Context

Every counter, report and work queue in this application is scoped to the one active dataset
(`config('dataset.main_file_id')`, `Text::scopeMainFile()` / `Audio::scopeMainFile()`), and
`docs/dataset-switch-plan.md` records why: the customer wants previous datasets out of the
statistics. The export pipeline follows the same rule and additionally hides the speaker: the
`.tsv` has no speaker column and filenames are random hashes.

The `kaa-tts-voices` project needs the opposite view. It builds per-speaker voice sets for TTS
speaker adaptation (≥5 min of verified audio per speaker) and a full list of which speaker read
which audio. A voice belongs to a speaker, not to a dataset: the same speaker recorded for
files 1–7 in March–April 2026, for file 8 in May–June and may record for file 10 now. Scoping to
the active dataset would split one voice across exports and drop the 2026 March–April cohort
entirely. The speaker → audio link exists only in the database (`audio.edit_speaker_id`), and
for the March–April exports the `.tsv` filename is the *converted* name
(`edit_converted_audio_filename`), not the original, so both filenames are needed.

## Decision

Add a read-only Artisan command `audio:export-speaker-manifest` that dumps **every verified
audio across all datasets** (`is_correct = true`, no `mainFile` scope, split parents excluded,
split parts included) with: audio id, speaker id, gender, age, `file_id`, `text_id`, original
filename + storage disk + public URL, converted filename, duration, recording and moderation
timestamps, original and normalized transcript. No names, no phone numbers — the speaker id is
enough to look a person up in the admin panel when needed.

The command does not create an `Export`, does not stamp `exported_at` and does not touch any
row: it is a view, not an export step, and must stay out of the export gate described in
`README.md`.

## Consequences

- This is a deliberate exception to "everything is scoped to the active dataset". It is the only
  such command; new dataset-scoped features keep following the rule.
- The command is covered by Pest tests and is deployed with the usual `.deploy.sh`; running it on
  prod is read-only and safe under live recording.
- Consumers: `kaa-tts-voices` (`CONTEXT.md` there documents the local pipeline). If the manifest
  ever needs bucket object sizes for duration estimation, that is a separate listing step, not a
  reason to widen this command's scope.
