# ТЗ для фронта: разрезание длинных аудио для STT

## Контекст

STT принимает аудио **не длиннее 30 секунд**. Длинные записи нужно разрезать на 2 части.
Аудио и текст режет **оператор вручную** на фронте, бэкенд принимает 2 готовые части.

## Базовое

- **Base URL:** `/api/v1/admin`
- **Авторизация:** `Authorization: Bearer <token>` (Sanctum, роль admin)
- **Заголовок:** `Accept: application/json`

## ⚠️ Главное правило: длительность — в СЭМПЛАХ

Поле `duration` везде измеряется **в сэмплах при 16 kHz**, а не в секундах.

- `секунды = duration / 16000`
- порог 30 с = **480000** сэмплов
- при отправке части: `duration = Math.round(длительность_части_в_секундах * 16000)`
- каждая часть должна быть **строго короче 30 с** → `duration < 480000` (диапазон `1…479999`)

---

## 1. Список аудио ≥ 30 с

```
GET /api/v1/admin/long/audio?per_page=10&direction=asc&page=1
```

> **Область:** возвращаются только аудио основного датасета (`file_id` основного файла). Записи других файлов в этот список не попадают.

**Query-параметры (все опциональны):**

| Параметр | Тип | По умолчанию | Примечание |
|----------|-----|--------------|------------|
| `per_page` | int | 10 | макс. 1000 |
| `direction` | string | `asc` | `asc` / `desc` (по `id`) |
| `page` | int | 1 | |

**Ответ `200`** (пагинация Laravel):

```json
{
  "data": [
    {
      "id": 123,
      "text_id": 456,
      "audio_filename": "abc123.wav",
      "audio_url": "https://storage.yandexcloud.net/.../audio/abc123.wav",
      "duration": 512000,
      "duration_seconds": 32.0,
      "split_status": "PENDING",
      "original_transcript": "полный исходный текст ...",
      "normalized_transcript": "...",
      "tokenized_transcript": "..."
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 10, "total": 42, "last_page": 5 }
}
```

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int | id аудио (нужен для запросов разрезания) |
| `text_id` | int | id связанного текста |
| `audio_filename` | string | имя файла оригинала |
| `audio_url` | string\|null | URL оригинала (проигрывать/скачивать) |
| `duration` | int | длительность **в сэмплах** (16 kHz) |
| `duration_seconds` | float | `duration / 16000` (для отображения) |
| `split_status` | string | `NONE` / `PENDING` / `SPLIT` / `UNSPLITTABLE` |
| `original_transcript` | string\|null | **полный** текст оригинала; оператор делит его сам |
| `normalized_transcript` | string\|null | то же, нормализованный |
| `tokenized_transcript` | string\|null | то же, токенизированный |

После успешного разрезания или пометки «неразрезаемое» запись **исчезает из списка**.
Список ограничен аудио основного датасета — записи других файлов не показываются.

---

## 2. Отправка 2 разрезанных частей

```
POST /api/v1/admin/long/audio/{audio}/split
Content-Type: multipart/form-data
```

`{audio}` — `id` из списка.

**Body (ровно 2 части):**

| Поле | Тип | Обяз. | Правила |
|------|-----|-------|---------|
| `parts[0][audio]` | file | да | `.wav` |
| `parts[0][duration]` | int | да | сэмплы, `1…479999` (< 30 с) |
| `parts[0][original_transcript]` | string | да | часть текста для 1-й половины |
| `parts[0][normalized_transcript]` | string | нет | |
| `parts[0][tokenized_transcript]` | string | нет | |
| `parts[1][...]` | — | да | то же для 2-й половины |

**Успех `201`:**

```json
{ "message": "Аудио разрезано на 2 части." }
```

**Ошибки:**

| Код | Когда | Тело |
|-----|-------|------|
| `422` | ошибка валидации (не 2 части / `duration >= 480000` / нет транскрипта / не wav) | `{ "message": "...", "errors": { "parts.0.duration": ["..."] } }` |
| `422` | нельзя разрезать (короче 30 с или уже обработано) | `{ "message": "Это аудио нельзя разрезать: оно короче лимита STT или уже обработано." }` |
| `503` | сбой хранилища (операция атомарна, можно повторить) | `{ "message": "Не удалось загрузить аудио в хранилище. Попробуйте ещё раз." }` |

**Пример (curl):**

```bash
curl -X POST ".../api/v1/admin/long/audio/123/split" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "parts[0][audio]=@part1.wav" -F "parts[0][duration]=280000" \
  -F "parts[0][original_transcript]=первая половина текста" \
  -F "parts[1][audio]=@part2.wav" -F "parts[1][duration]=232000" \
  -F "parts[1][original_transcript]=вторая половина текста"
```

---

## 3. Пометить «неразрезаемое»

```
POST /api/v1/admin/long/audio/{audio}/unsplittable
```

Без тела. Используется, если запись нельзя разрезать (нет паузы / единое непрерывное произношение).

**Успех `200`:** `{ "message": "Аудио помечено как неразрезаемое." }`

**Ошибка `422`** (уже разрезано): `{ "message": "Аудио уже разрезано." }`

---

## 4. Просмотр результатов (проверка разрезания)

```
GET /api/v1/admin/long/audio/processed?status=split&per_page=10&direction=desc&page=1
```

Возвращает уже **обработанные** длинные аудио — для проверки, что разрезание сделано правильно:
разрезанные (`SPLIT`) приходят вместе со своими 2 частями, а помеченные `UNSPLITTABLE` — с пустым `parts`.

> **Область:** как и список из шага 1 — только аудио основного датасета (`file_id` основного файла).

**Query-параметры (все опциональны):**

| Параметр | Тип | По умолчанию | Примечание |
|----------|-----|--------------|------------|
| `status` | string | — (оба) | `split` / `unsplittable`; без параметра — оба статуса |
| `per_page` | int | 10 | макс. 1000 |
| `direction` | string | `asc` | `asc` / `desc` (по `id`) |
| `page` | int | 1 | |

**Ответ `200`** (пагинация Laravel). Каждая запись — оригинал длинного аудио + массив `parts`:

```json
{
  "data": [
    {
      "id": 123,
      "text_id": 456,
      "audio_filename": "abc123.wav",
      "audio_url": "https://storage.yandexcloud.net/.../audio/abc123.wav",
      "duration": 512000,
      "duration_seconds": 32.0,
      "split_status": "SPLIT",
      "original_transcript": "полный исходный текст ...",
      "normalized_transcript": "...",
      "tokenized_transcript": "...",
      "parts": [
        {
          "id": 901,
          "text_id": 902,
          "audio_filename": "part1.wav",
          "audio_url": "https://storage.yandexcloud.net/.../audio/part1.wav",
          "duration": 280000,
          "duration_seconds": 17.5,
          "split_status": "NONE",
          "original_transcript": "первая половина текста",
          "normalized_transcript": "...",
          "tokenized_transcript": "..."
        },
        {
          "id": 903,
          "text_id": 904,
          "audio_filename": "part2.wav",
          "audio_url": "https://storage.yandexcloud.net/.../audio/part2.wav",
          "duration": 232000,
          "duration_seconds": 14.5,
          "split_status": "NONE",
          "original_transcript": "вторая половина текста",
          "normalized_transcript": "...",
          "tokenized_transcript": "..."
        }
      ]
    }
  ],
  "links": { "...": "..." },
  "meta": { "current_page": 1, "per_page": 10, "total": 12, "last_page": 2 }
}
```

| Поле | Тип | Описание |
|------|-----|----------|
| (поля оригинала) | | те же, что в списке шага 1 |
| `split_status` | string | здесь всегда `SPLIT` или `UNSPLITTABLE` |
| `parts` | array | части разрезанного аудио; у `SPLIT` — 2 элемента, у `UNSPLITTABLE` — `[]` |
| `parts[].audio_url` | string\|null | проигрывать каждую часть и сверять с оригиналом |
| `parts[].duration` / `duration_seconds` | int / float | длительность части (сэмплы / секунды) |
| `parts[].original_transcript` и др. | string\|null | текст соответствующей части |

Сценарий проверки: проиграть оригинал (`audio_url`), затем по очереди обе части из `parts`,
сверить их транскрипты и суммарную длительность с оригиналом.

---

## Пользовательский сценарий

1. Открыть список (`GET long/audio`).
2. Для записи: проиграть `audio_url`, показать транскрипт и `duration_seconds`.
3. Оператор режет аудио на 2 фрагмента (**каждый < 30 с**) и делит текст на 2 части.
4. Отправить обе части (`POST .../split`); `duration` каждой — **в сэмплах** (`секунды * 16000`).
5. Если разрезать нельзя — `POST .../unsplittable`.
6. Запись пропадает из списка.
7. Проверка результата: открыть список обработанных (`GET long/audio/processed`),
   проиграть оригинал и обе части из `parts`, сверить транскрипты и длительность;
   при необходимости отдельно посмотреть помеченные `unsplittable`.

## Статусы `split_status`

| Статус | Смысл |
|--------|-------|
| `NONE` | обычное аудио (< 30 с), обработка не нужна |
| `PENDING` | ≥ 30 с, ждёт разрезания |
| `SPLIT` | разрезано на 2 части |
| `UNSPLITTABLE` | неразрезаемое, выведено из STT |

В списке шага 1 приходят только записи **не** в статусе `SPLIT`/`UNSPLITTABLE`.
Обработанные (`SPLIT`/`UNSPLITTABLE`) смотрятся через `GET long/audio/processed` (шаг 4).

---

## Бэкенд-нюансы (не блокируют фронт)

- **Формат wav частей:** endpoint сохраняет файл и доверяет переданному `duration`, конвертацию не делает. Чтобы части совпадали с датасетом, заливать **16 kHz / mono / pcm_s16le** и считать `duration` по этому sample rate.
- **Дочерние `Text` половин скрыты** из общих текстовых очередей, списков и счётчиков (помечены `is_split_part`; issue #22 закрыт). В обычных экранах они не появляются — увидеть части можно только через `GET long/audio/processed` (шаг 4).
