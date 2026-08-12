# План: переключение на новый датасет

> Обновлено: 2026-08-12. Статус: **разбит на issues #35–#42, работа идёт.**
> Закрыто: #36. Следующий шаг — #37 (фильтр активного датасета в дашборде и профилях).

## Задача

Загрузить в прод новый датасет текстов. Требования заказчика:

1. **Все предыдущие данные должны выпасть из статистики** — дашборд, отчёты спикеров и
   модераторов, профили пользователей считают только активный датасет.
2. **Новый датасет должен явно отличаться от предыдущих** — по `file_id`, имени файла и
   группе экспорта.

Принятые решения (2026-08-12):

| Вопрос | Решение |
|---|---|
| Как привязать статистику | **Только активный датасет** — всё считается от `config('dataset.main_file_id')`. Параметра выбора датасета в отчётах не будет. |
| 39 087 неозвученных текстов `file_id = 8` | **Отбрасываются.** После переключения в очередь спикеров не попадают. |
| Итоговые счётчики спикеров (профиль, рейтинг) | **Обнуляются** — тоже считаются по активному датасету. |

Старые данные остаются в базе, просто перестают отображаться.

---

## 1. Состояние прода на 2026-08-12

| file_id | Файл | Тексты (`is_main`) | Аудио | Без модерации | Выгружено |
|---|---|---|---|---|---|
| 1–3 | `train.tsv`, `test.tsv`, `dev.tsv` | 1 990 | 16 458 | 0 | 16 256 |
| 4–7 | `FINISH_DATASET*.xlsx`, `final qq.xlsx` | 2 098 | 16 763 | 0 | 16 550 |
| **8** | **`karakalpak_corpus_v2_all_texts.txt`** | **135 767** | **95 851** | **368** | **45 260** |

Детали по активному `file_id = 8`:

- 135 667 строк импорта + 100 приветственных текстов (`texts:add-greetings`), плюс 666
  `is_split_part` записей (не датасетные тексты).
- **39 087** текстов ни разу не озвучены, **3 469** помечены `has_text_error`,
  **223** удерживаются спикерами (`speak_started_at` не сброшен).
- Аудио: 95 185 озвучено, 74 937 `is_correct = true`, **368** ждут модерации.

Всего по базе: **129 072** аудио, из них **50 340** без `edit_converted_audio_duration`
и **51 006** ещё не выгружены.

Таблица `exports`: `Legacy (file_id < 8)` — 32 806, `file_id = 8` — 45 260.

Переменной `DATASET_MAIN_FILE_ID` в проде **нет** — работает дефолт `8` из
`config/dataset.php`. Новый датасет получит `file_id = 9`.

---

## 2. Откуда код берёт `file_id` — три разных механизма

### A. Через `config('dataset.main_file_id')` — переключается переменной окружения

`Text::scopeMainFile()`, `SpeakTextController:41`, `LongAudioController:19`,
`LongAudioProcessedController:26`, `LongAudioPartTextUpdateController:54`,
часть `DailyQuotaController`.

### B. Захардкоженная константа `FILE_ID = 8` — не переключится

**Исправлено в #36** — константы больше нет ни в одном файле `app/`:
`ReportSpeakerController`, `ReportModeratorController`, `ExportReportUserController`,
`ExportReportCommand`, `ExportDailyReportCommand` берут значение из конфига.

Было: после переключения все отчёты по спикерам и модераторам продолжили бы
показывать данные старого датасета.

### C. Фильтра по файлу нет вообще — старое и новое смешается

| Файл | Что смешивается |
|---|---|
| `DailyQuotaController:78-110` | `edit_finished_texts_count`, `edit_not_finished_texts_count`, `daily_quota_texts_count`, `daily_quota_audios_count`, `daily_quota_check_audios_count`, `edit_finished_today_count`, `speak_finished_today_count`, `moderator_finished_today_count` |
| `ProfileReportController:46,113` | личный отчёт пользователя (спикер + модератор) целиком |
| `Verify/UserController:32-37` | все `withCount`: итоговые и дневные счётчики по пользователям |
| `ModeratorAudioController`, `ModeratorTextController` | очередь модерации |
| `FinishedAudioController:16-21` | список готовых аудио |

### D. Команды экспорта

`ExportAudioFilenamesCommand:17` и `ExportCorrectAudioCommand:17` — дефолт `--file-id=8`
зашит в сигнатуру.

---

## 3. Риски перехода

1. **`SpeakTextController:28-31`** — запрос удерживаемого текста без фильтра по файлу.
   После переключения 223 спикера снова получат старый текст `file_id = 8`.
2. **Бэклог file 8 потеряется.** Когда дефолт `--file-id` станет конфигурируемым, старые
   51 006 аудио нужно будет выгружать явным `--file-id=8`.
3. **Кэш дашборда** — `DailyQuotaController`, `Cache::flexible` на 1 ч свежести / 2 ч
   устаревания. После переключения нужен сброс ключа `daily_quota_data`.
4. **`import:text-file`** пишет строки по одной внутри одной транзакции. Для 135k строк
   это долгая и тяжёлая транзакция — при большом новом датасете стоит перевести на
   пакетный `insert()` чанками.
5. **Дубликаты** — если в новом датасете встречаются предложения из старого, спикеры
   будут переозвучивать уже записанное.
6. После переключения `edit_finished_texts_count` сразу станет равен размеру датасета:
   `import:text-file` проставляет `edit_finished_at` при импорте. Это ожидаемо.

---

## 4. Фазы

### Фаза 0 — закрыть старый датасет

Не зависит от готовности нового датасета, можно делать сразу.

- [ ] `audio:export-filenames --file-id=8` → Python считает длительности → `update:duration`
- [ ] `audio:export-correct --file-id=8 --name="v2 final"` — закрыть 51 006 аудио
- [ ] Домодерировать 368 аудио `file_id = 8`

Результат: по file 8 не остаётся открытых хвостов, переключение безопасно.

### Фаза 1 — код: привязать всю статистику к одному источнику

Деплоится **до** загрузки датасета.

- [x] Заменить 5 констант `FILE_ID = 8` на `config('dataset.main_file_id')` (#36)
- [ ] `DailyQuotaController:78-110` — добавить `is_main` + `file_id` в восемь нефильтрованных
      показателей и в `audioTodayStats`
- [ ] `ProfileReportController:46,113` — `whereHas('text', fn ($q) => $q->mainFile())` в обоих отчётах
- [ ] `Verify/UserController:32-37` — фильтр по файлу внутри `withCount`-замыканий
      (итоговые счётчики пользователей обнулятся — так и задумано)
- [ ] `SpeakTextController:28-31` — фильтр по файлу в запросе удерживаемого текста
- [ ] `ExportAudioFilenamesCommand:17`, `ExportCorrectAudioCommand:17` — сигнатура
      `{--file-id=}`, пустое значение → `config('dataset.main_file_id')`
- [ ] После Фазы 0: фильтр по файлу в `ModeratorAudioController`, `ModeratorTextController`,
      `FinishedAudioController`
- [ ] Тесты: существующие уже переопределяют `config(['dataset.main_file_id' => …])`,
      добавить кейсы на новые фильтры; `vendor/bin/pint`, `php artisan test`

### Фаза 2 — загрузка датасета

- [ ] Подготовить `.txt`: одна строка — одно предложение, UTF-8, LF, без внутренних дублей
- [ ] Проверить пересечение с существующими `edit_original_transcript` и вычистить совпадения
- [ ] Залить файл в `storage/app/public/` на проде
- [ ] `php artisan import:text-file <файл>.txt --user=1` → создаётся **File #9**
- [ ] Проверить: `rows_imported`, `is_main = 1`, `is_split_part = 0`, количество текстов
- [ ] При необходимости `texts:add-greetings --file=9`
- [ ] При большом объёме — предварительно перевести импорт на чанковый `insert()`

### Фаза 3 — переключение

- [ ] В прод `.env`: `DATASET_MAIN_FILE_ID=9`
- [ ] `php artisan optimize` (пересобрать кэш конфига) + сбросить кэш `daily_quota_data`
- [ ] Освободить 223 удерживаемых текста file 8: `speak_started_at` и `edit_speaker_id` → `null`
- [ ] Проверка: спикерам выдаётся только `file_id = 9`; дашборд считает с нуля; отчёты
      спикеров и модераторов пустые

### Фаза 4 — визуальное разделение датасетов (опционально)

- [ ] Колонки `label` и `is_active` в таблице `files`
- [ ] Отображение активного датасета в админке
- [ ] Конвенция имён групп экспорта: `v3 batch YYYY-MM`

---

## 5. Issues

| Issue | Фаза | Содержание | Статус |
|---|---|---|---|
| #35 | 0 | Закрыть экспорт и модерацию `file_id = 8` | открыт |
| #36 | 1 | `FILE_ID = 8` → конфиг в 5 файлах отчётов | ✅ закрыт |
| #37 | 1 | Фильтр активного датасета в `DailyQuotaController`, `ProfileReportController`, `Verify/UserController` | открыт |
| #38 | 1 | Фильтр файла в удерживаемом тексте `SpeakTextController` + очередях модерации | открыт |
| #39 | 1 | Конфигурируемый `--file-id` в командах экспорта | открыт |
| #40 | 2 | Импорт нового датасета (+ чанковый `insert`, проверка дублей) | открыт |
| #41 | 3 | Переключение прода и постпроверка | открыт |
| #42 | 4 | `files.label` / `is_active` и отображение в админке | открыт |

---

## Приложение: команды проверки на проде

```bash
cd /var/www/fastuser/data/www/api.verify.aralhub.uz

# Тексты по датасетам
/opt/php8.4/bin/php artisan tinker --execute="
  print_r(DB::table('texts')->selectRaw('file_id, is_main, is_split_part, count(*) as c')
    ->groupBy('file_id','is_main','is_split_part')->orderBy('file_id')->get()->toArray());"

# Аудио по датасетам: озвучено / без модерации / принято / выгружено
/opt/php8.4/bin/php artisan tinker --execute="
  print_r(DB::table('audio')->join('texts','audio.text_id','=','texts.id')
    ->selectRaw('texts.file_id, count(*) as total,
       sum(audio.speak_finished_at is not null) as spoken,
       sum(audio.is_correct is null and audio.speak_finished_at is not null) as unmoderated,
       sum(audio.is_correct=1) as correct,
       sum(audio.exported_at is not null) as exported')
    ->groupBy('texts.file_id')->get()->toArray());"
```

Деплой — `./.deploy.sh` из каталога приложения (см. `README.md` §Deployment).
На сервере системный `php` — 8.2.24, актуальный интерпретатор — `/opt/php8.4/bin/php`.
