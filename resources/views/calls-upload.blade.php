<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Загрузка звонков — {{ $batch }}</title>
<style>
:root{--bg:#f4f5f7;--card:#fff;--fg:#1d2330;--mut:#667085;--line:#e3e6eb;--acc:#2f6fed;--ok:#1f9d55;--err:#d93636}
@media (prefers-color-scheme:dark){:root{--bg:#0f1115;--card:#181b22;--fg:#e8eaf0;--mut:#8b93a5;--line:#2a2f3a;--acc:#4f8cff;--ok:#3ecf8e;--err:#ff6b6b}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
main{max-width:720px;margin:0 auto;padding:32px 16px 48px}
h1{font-size:24px;margin:0 0 4px}
h2{font-size:16px;margin:0 0 12px}
.muted{color:var(--mut)}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:20px;margin:20px 0}
.choices{display:flex;flex-wrap:wrap;gap:12px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 18px;border-radius:10px;border:1px solid var(--line);background:var(--card);color:var(--fg);font:inherit;font-weight:600;cursor:pointer}
.btn.primary{background:var(--acc);border-color:var(--acc);color:#fff}
.btn[aria-disabled=true]{opacity:.45;pointer-events:none}
.hint{color:var(--mut);font-size:13px;margin:12px 0 0}
.bar{height:12px;border-radius:6px;background:var(--line);overflow:hidden}
.bar>div{height:100%;width:0;background:var(--acc);transition:width .4s}
.bar.done>div{background:var(--ok)}
#stats{font-variant-numeric:tabular-nums;margin:10px 0 0}
#state{margin:6px 0 0;font-weight:600}
#state.ok{color:var(--ok)}#state.err{color:var(--err)}
#errors{margin:10px 0 0;padding-left:18px;color:var(--err);font-size:13px;max-height:200px;overflow:auto}
</style>
</head>
<body>
<main>
  <h1>Загрузка звонков</h1>
  <p class="muted">Партия <b>{{ $batch }}</b> · ссылка действует до {{ $expiresAt->copy()->timezone('Asia/Tashkent')->format('d.m.Y H:i') }} (Ташкент)</p>

  <section class="card">
    <h2>Что загрузить</h2>
    <div class="choices">
      <label class="btn primary" id="folderBtn">📁 Выбрать папку со звонками
        <input id="pickFolder" type="file" webkitdirectory directory multiple hidden>
      </label>
      <label class="btn" id="archiveBtn">🗜 Выбрать архив (rar, zip, 7z)
        <input id="pickArchive" type="file" accept=".rar,.zip,.7z" multiple hidden>
      </label>
    </div>
    <p class="hint">
      <b>Папка</b> — загрузятся все аудиофайлы из неё и вложенных папок, остальные файлы пропустятся.<br>
      <b>Архив</b> — выберите сам архив; если он разбит на части (part1.rar, part2.rar…), выберите все части сразу.
    </p>
  </section>

  <section class="card" id="progressCard" hidden>
    <h2>Загрузка</h2>
    <div class="bar" id="bar"><div id="barFill"></div></div>
    <p id="stats"></p>
    <p id="state"></p>
    <ul id="errors"></ul>
    <button class="btn" id="retry" hidden>Повторить неудавшиеся</button>
  </section>

  <p class="hint">
    Не закрывайте вкладку до конца загрузки. Если связь оборвётся или вкладка закроется — откройте эту ссылку
    снова и выберите то же самое: уже загруженное пропустится, загрузка продолжится с места остановки.
  </p>
</main>

<script>
'use strict';
const CFG = @json($config);

const $ = id => document.getElementById(id);
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const audioRe = new RegExp('\\.(' + CFG.audioExtensions.join('|') + ')$', 'i');
const archiveRe = new RegExp(CFG.archivePattern, 'i');
const fmtBytes = b => b >= 1e9 ? (b / 1e9).toFixed(2) + ' ГБ' : b >= 1e6 ? (b / 1e6).toFixed(1) + ' МБ' : Math.max(1, Math.round(b / 1e3)) + ' КБ';
const fmtTime = s => {
  if (!isFinite(s) || s <= 0) return '—';
  s = Math.round(s);
  const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60);
  return h ? `${h} ч ${m} мин` : m ? `${m} мин` : `${s} с`;
};

/** An error that retrying will not fix: a rejected file, or (fatal) a dead link. */
class StopError extends Error {
  constructor(message, fatal = false) { super(message); this.fatal = fatal; }
}

async function api(url, body = {}) {
  let lastError = new Error('Нет связи с сервером');
  for (let attempt = 1; attempt <= 5; attempt++) {
    let res;
    try {
      res = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(body)});
    } catch {
      await sleep(1500 * attempt);
      continue;
    }
    const data = await res.json().catch(() => ({}));
    if (res.ok) return data;
    if (res.status === 403) throw new StopError('Ссылка недействительна или её срок истёк — попросите новую.', true);
    if (res.status === 422) {
      const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
      throw new StopError(first || data.message || 'Сервер не принял данные.');
    }
    lastError = new Error(data.message || ('Ошибка сервера ' + res.status));
    await sleep(1500 * attempt);
  }
  throw lastError;
}

/** PUT a blob to a signed bucket URL, reporting bytes sent. */
function put(url, headers, blob, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('PUT', url);
    for (const [name, value] of Object.entries(headers || {})) xhr.setRequestHeader(name, value);
    xhr.upload.onprogress = e => onProgress(e.loaded);
    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(Object.assign(new Error('HTTP ' + xhr.status), {status: xhr.status}));
    xhr.onerror = () => reject(new Error('Сеть'));
    xhr.send(blob);
  });
}

/** Run `job` over `items` with at most `limit` at a time. */
async function pool(items, limit, job) {
  let next = 0;
  const worker = async () => { while (next < items.length) { const item = items[next++]; await job(item); } };
  await Promise.all(Array.from({length: Math.min(limit, items.length)}, worker));
}

// ---- progress ----
const progress = {total: 0, done: 0, inflight: new Map(), files: 0, filesDone: 0, samples: []};
let running = false;
let lastRun = null;
const failures = [];

function startProgress(totalBytes, totalFiles) {
  Object.assign(progress, {total: totalBytes, done: 0, files: totalFiles, filesDone: 0, samples: []});
  progress.inflight.clear();
  failures.length = 0;
  $('errors').innerHTML = '';
  $('retry').hidden = true;
  $('bar').classList.remove('done');
  $('progressCard').hidden = false;
}

function render() {
  const sent = progress.done + [...progress.inflight.values()].reduce((a, b) => a + b, 0);
  const now = Date.now();
  progress.samples.push([now, sent]);
  while (progress.samples.length > 2 && now - progress.samples[0][0] > 15000) progress.samples.shift();
  const [t0, b0] = progress.samples[0];
  const speed = now > t0 ? (sent - b0) / ((now - t0) / 1000) : 0;
  const pct = progress.total ? Math.min(100, sent / progress.total * 100) : 0;
  $('barFill').style.width = pct.toFixed(1) + '%';
  $('stats').textContent = `${pct.toFixed(1)}% · ${fmtBytes(sent)} из ${fmtBytes(progress.total)}`
    + (progress.files > 1 ? ` · файлов ${progress.filesDone} из ${progress.files}` : '')
    + (running && speed > 0 ? ` · ${fmtBytes(speed)}/с · осталось ≈ ${fmtTime((progress.total - sent) / speed)}` : '');
}
setInterval(() => { if (!$('progressCard').hidden) render(); }, 500);

function setState(text, kind = '') { $('state').textContent = text; $('state').className = kind; }
function addError(text) { const li = document.createElement('li'); li.textContent = text; $('errors').appendChild(li); }
function setBusy(busy) {
  running = busy;
  $('folderBtn').setAttribute('aria-disabled', busy);
  $('archiveBtn').setAttribute('aria-disabled', busy);
}
window.addEventListener('beforeunload', e => { if (running) { e.preventDefault(); e.returnValue = ''; } });

async function run(task) {
  lastRun = task;
  setBusy(true);
  try {
    await task();
  } catch (e) {
    setState(e.message || String(e), 'err');
  } finally {
    setBusy(false);
    render();
    if (failures.length) { $('retry').hidden = false; }
  }
}

// ---- folder of audio files ----
async function uploadFolder(fileList) {
  const all = [...fileList];
  const audio = all.filter(f => audioRe.test(f.name) && f.size > 0);
  const tooBig = audio.filter(f => f.size > CFG.maxFileBytes);
  const items = audio.filter(f => f.size <= CFG.maxFileBytes)
    .map(f => ({blob: f, path: (f.webkitRelativePath || f.name).replace(/\\/g, '/').replace(/^\/+/, ''), size: f.size}));

  startProgress(items.reduce((a, i) => a + i.size, 0), items.length);
  tooBig.forEach(f => addError(`${f.name}: больше ${fmtBytes(CFG.maxFileBytes)}, пропущен`));
  if (!items.length) throw new StopError('В выбранной папке нет аудиофайлов (mp3, wav, ogg, m4a, amr…).');

  setState('Проверяю, что уже загружено…');
  const existing = (await api(CFG.urls.existing)).files || {};
  const todo = [];
  for (const item of items) {
    if (existing[item.path] === item.size) { progress.done += item.size; progress.filesDone++; } else { todo.push(item); }
  }
  const ignored = all.length - audio.length;
  setState(`Загружаю ${todo.length} файлов` + (items.length > todo.length ? ` (${items.length - todo.length} уже были загружены)` : '') + (ignored ? `, ${ignored} не-аудио пропущено` : '') + '…');

  await uploadItems(todo);
  finish(`${items.length} файлов, ${fmtBytes(progress.total)}`);
}

async function uploadItems(todo) {
  for (let i = 0; i < todo.length; i += 60) {
    const chunk = todo.slice(i, i + 60);
    let byPath = new Map();
    try {
      const signed = await api(CFG.urls.files, {files: chunk.map(({path, size}) => ({path, size}))});
      byPath = new Map(signed.uploads.map(u => [u.path, u]));
    } catch (e) {
      // One rejected file fails the whole chunk; sign one by one so only that file fails.
      if (!(e instanceof StopError) || e.fatal) throw e;
    }
    await pool(chunk, 6, item => uploadOne(item, byPath.get(item.path)));
  }
}

async function uploadOne(item, signed) {
  for (let attempt = 1; attempt <= 4; attempt++) {
    try {
      if (!signed) signed = (await api(CFG.urls.files, {files: [{path: item.path, size: item.size}]})).uploads[0];
      await put(signed.url, signed.headers, item.blob, loaded => progress.inflight.set(item, loaded));
      progress.inflight.delete(item);
      progress.done += item.size;
      progress.filesDone++;
      return;
    } catch (e) {
      progress.inflight.delete(item);
      if (e instanceof StopError) {
        if (e.fatal) throw e;
        addError(`${item.path}: ${e.message}`);
        failures.push(item);
        return;
      }
      if (e.status === 403) signed = null;
      await sleep(2000 * attempt);
    }
  }
  failures.push(item);
  addError(`${item.path}: не удалось загрузить`);
}

// ---- archives (multipart) ----
async function uploadArchives(fileList) {
  const archives = [...fileList].filter(f => archiveRe.test(f.name));
  const other = fileList.length - archives.length;
  startProgress(archives.reduce((a, f) => a + f.size, 0), archives.length);
  if (other) addError(`${other} файл(ов) не похожи на архив и пропущены`);
  if (!archives.length) throw new StopError('Выберите архив .rar, .zip или .7z.');
  const tooBig = archives.find(f => f.size > CFG.maxArchiveBytes);
  if (tooBig) throw new StopError(`${tooBig.name}: больше ${fmtBytes(CFG.maxArchiveBytes)}.`);

  setState('Проверяю, что уже загружено…');
  const existing = (await api(CFG.urls.existing)).archives || {};

  for (const file of archives) {
    if (existing[file.name] === file.size) { progress.done += file.size; progress.filesDone++; continue; }
    setState(`Загружаю ${file.name}…`);
    await uploadArchive(file);
    progress.filesDone++;
  }
  finish(`${archives.length > 1 ? archives.length + ' файла архива' : archives[0].name}, ${fmtBytes(progress.total)}`);
}

async function uploadArchive(file) {
  const base = {name: file.name, size: file.size};
  const start = await api(CFG.urls.archiveStart, base);
  const ref = {...base, upload_id: start.upload_id};
  const partSize = start.part_size;
  const uploaded = new Set(start.uploaded);
  const partBytes = n => Math.min(partSize, file.size - (n - 1) * partSize);

  const todo = [];
  for (let n = 1; n <= start.parts_total; n++) {
    if (uploaded.has(n)) { progress.done += partBytes(n); } else { todo.push(n); }
  }

  const failedParts = [];
  for (let i = 0; i < todo.length; i += 20) {
    const chunk = todo.slice(i, i + 20);
    const {urls} = await api(CFG.urls.archiveParts, {...ref, parts: chunk});
    await pool(chunk, 4, async n => {
      const blob = file.slice((n - 1) * partSize, (n - 1) * partSize + partBytes(n));
      const key = file.name + '#' + n;
      let url = urls[n];
      for (let attempt = 1; attempt <= 5; attempt++) {
        try {
          await put(url, {}, blob, loaded => progress.inflight.set(key, loaded));
          progress.inflight.delete(key);
          progress.done += blob.size;
          return;
        } catch (e) {
          progress.inflight.delete(key);
          if (e.status === 403) { url = (await api(CFG.urls.archiveParts, {...ref, parts: [n]})).urls[n]; }
          await sleep(2000 * attempt);
        }
      }
      failedParts.push(n);
    });
  }

  if (failedParts.length) {
    failures.push(file);
    throw new Error(`${file.name}: не удалось загрузить ${failedParts.length} част(ей). Нажмите «Повторить» — загруженное не пропадёт.`);
  }

  setState(`Собираю ${file.name}…`);
  await api(CFG.urls.archiveComplete, ref);
}

function finish(summary) {
  render();
  if (failures.length) {
    setState(`Загружено не всё: ${failures.length} с ошибкой. Нажмите «Повторить неудавшиеся».`, 'err');
    return;
  }
  $('bar').classList.add('done');
  setState(`Готово ✓ ${summary}. Можно закрыть страницу и сообщить нам, что загрузка закончилась.`, 'ok');
}

// ---- wiring ----
$('pickFolder').addEventListener('change', e => { const files = [...e.target.files]; e.target.value = ''; if (files.length) run(() => uploadFolder(files)); });
$('pickArchive').addEventListener('change', e => { const files = [...e.target.files]; e.target.value = ''; if (files.length) run(() => uploadArchives(files)); });
$('retry').addEventListener('click', () => { if (lastRun && !running) run(lastRun); });
</script>
</body>
</html>
