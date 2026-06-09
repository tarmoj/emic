<?php
declare(strict_types=1);

require_once __DIR__ . '/../search/api/db.php';

$instruments = [];
$ensembles   = [];
$dbError     = null;

try {
    $pdo         = emic_db();
    $instruments = $pdo->query('SELECT lyhend, nimi, nimi_eng FROM instrumendid ORDER BY nimi_eng')->fetchAll();
    $ensembles   = $pdo->query('SELECT id, nimi, nimi_eng FROM ansamblid ORDER BY nimi_eng')->fetchAll();
} catch (Throwable $e) {
    $dbError = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

$instrJson    = json_encode($instruments, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$ensemblesJson = json_encode($ensembles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?><!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EMIC Instrumentatsiooni redaktor</title>
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: sans-serif; font-size: 14px; margin: 0; padding: 16px 20px; background: #f5f5f5; color: #222; }
h1 { font-size: 1.3em; margin-bottom: 16px; }
h2 { font-size: 1em; margin: 0 0 10px 0; padding: 6px 10px; background: #dde; border-left: 4px solid #669; }

.section { background: #fff; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 14px; padding: 12px 14px; }
.sub-section { background: #f9f9ff; border: 1px solid #dde; border-radius: 3px; padding: 8px 12px; margin-top: 6px; }

.field-row { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.field-row label { display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.btn-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 8px; }

input[type=text], input[type=number], textarea, select {
    border: 1px solid #bbb; border-radius: 3px; padding: 3px 6px; font-size: 13px;
}
input[type=text]:focus, input[type=number]:focus, textarea:focus, select:focus {
    outline: none; border-color: #669; box-shadow: 0 0 0 2px #ddf;
}
textarea { resize: vertical; }

button { padding: 4px 12px; border: 1px solid #999; border-radius: 3px; background: #eee; cursor: pointer; font-size: 13px; }
button:hover { background: #ddd; }
.btn-primary { background: #446; color: #fff; border-color: #335; }
.btn-primary:hover { background: #557; }
.btn-secondary { background: #e8e8ff; border-color: #aad; }
.btn-secondary:hover { background: #d8d8ff; }
.btn-remove { background: #fee; border-color: #faa; color: #800; padding: 2px 8px; font-size: 12px; }
.btn-remove:hover { background: #fcc; }

.error { color: #c00; background: #fee; border: 1px solid #faa; border-radius: 3px; padding: 4px 8px; margin: 4px 0; }
.success { color: #060; background: #efe; border: 1px solid #afa; border-radius: 3px; padding: 4px 8px; margin: 4px 0; }

/* --- Ensemble rows --- */
.ensemble-row {
    display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
    padding: 6px 8px; margin-bottom: 6px;
    background: #f8f8ff; border: 1px solid #dde; border-radius: 3px;
}
.ensemble-row select { min-width: 200px; max-width: 280px; }
.ensemble-custom-id { min-width: 180px; }
.orch-num { width: 52px; text-align: center; }

/* --- Part rows --- */
.part-row {
    padding: 8px 10px; margin-bottom: 8px;
    background: #f8fff8; border: 1px solid #cec; border-radius: 3px;
}
.part-row-top { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
.part-sub { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 14px; }
.part-sub-block { display: flex; flex-direction: column; gap: 4px; }
.part-sub-label { font-size: 11px; color: #666; font-weight: bold; }

/* --- Instrument autocomplete --- */
.instr-combo { position: relative; display: inline-block; }
.instr-combo .instr-input { width: 220px; }
.instr-combo .suggestions {
    position: absolute; top: 100%; left: 0; z-index: 1000;
    background: #fff; border: 1px solid #aaa; border-top: none;
    list-style: none; margin: 0; padding: 0;
    min-width: 340px; max-height: 220px; overflow-y: auto;
    box-shadow: 0 4px 8px rgba(0,0,0,.15);
}
.instr-combo .suggestions li {
    padding: 5px 8px; cursor: pointer; font-size: 13px; white-space: nowrap;
}
.instr-combo .suggestions li:hover,
.instr-combo .suggestions li.active { background: #ddf; }
.tag-combo .instr-input { width: 160px; }

/* --- Tags --- */
.tag-container { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; min-height: 28px; }
.tag {
    display: inline-flex; align-items: center; gap: 3px;
    background: #e0e8ff; border: 1px solid #aac; border-radius: 12px;
    padding: 2px 8px; font-size: 12px; white-space: nowrap;
}
.tag-remove {
    background: none; border: none; cursor: pointer; color: #669;
    font-size: 13px; padding: 0 2px; line-height: 1;
}
.tag-remove:hover { color: #c00; }
.tag-input-area { display: inline-flex; align-items: center; gap: 4px; }
.tag-text-input { width: 120px; font-size: 12px; }
.tag-add-btn { padding: 2px 8px; font-size: 12px; }

/* --- JSON textarea --- */
#json-output { font-family: 'Courier New', monospace; font-size: 12px; width: 100%; min-height: 260px; }

/* --- Modal --- */
dialog { border: 1px solid #aaa; border-radius: 6px; padding: 20px; min-width: 340px; }
dialog::backdrop { background: rgba(0,0,0,.4); }
dialog h3 { margin: 0 0 14px 0; }

#save-status, #load-status { font-size: 12px; }

/* --- Scoring variants --- */
.variant-row {
    border: 1px solid #d5c8a8; border-radius: 4px; padding: 8px 10px; margin-bottom: 10px;
    background: #fffdf5;
}
.variant-row-top { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
.variant-label-input { width: 260px; }
.variant-json { font-family: 'Courier New', monospace; font-size: 11px; width: 100%; min-height: 120px; background: #fafafa; }
.variant-json-error { color: #c00; font-size: 11px; }
</style>
</head>
<body>

<h1>EMIC Instrumentatsiooni redaktor</h1>

<?php if ($dbError): ?>
<div class="error">Andmebaasi ühenduse viga: <?= $dbError ?></div>
<?php endif; ?>

<!-- ===== TEOS ===== -->
<section class="section" id="s-teos">
  <h2>Teos</h2>
  <div class="field-row">
    <label>Teose ID:</label>
    <input type="number" id="work-id" min="1" max="99999" style="width:90px" placeholder="1–99999">
    <button type="button" id="btn-load">Laadi</button>
    <button type="button" id="btn-new">Uus teos</button>
    <span id="load-status"></span>
  </div>
  <div class="field-row">
    <label>Pealkiri:</label>
    <input type="text" id="work-title" style="width:440px">
  </div>
  <div class="field-row" id="koosseis-tekst-row" style="display:none">
    <label>Koosseis tekst:</label>
    <textarea id="koosseis-tekst" rows="2" style="width:560px"></textarea>
  </div>
</section>

<!-- ===== ÜLDINFO ===== -->
<section class="section" id="s-uldinfo">
  <h2>Üldinfo</h2>
  <div class="field-row">
    <label>Mängijaid kokku:
      <input type="number" id="total-player-count" value="0" min="0" style="width:70px">
    </label>
    <label style="margin-left:20px"><input type="checkbox" id="has-vocal"> Vokaal</label>
    <label style="margin-left:20px"><input type="checkbox" id="has-electronics"> Elektroonika</label>
  </div>
  <div id="electronics-section" class="sub-section" style="display:none">
    <div class="field-row">
      <label>Tüüp: <input type="text" id="electronics-type" value="electronics" style="width:160px"></label>
      <label style="margin-left:10px">Täpsustus: <input type="text" id="electronics-details" style="width:280px"></label>
    </div>
  </div>
</section>

<!-- ===== ANSAMBLID ===== -->
<section class="section" id="s-ansamblid">
  <h2>Ansamblid</h2>
  <div id="ensemble-list"></div>
  <div class="btn-row">
    <button type="button" id="btn-add-ensemble">+ Lisa ansambel</button>
    <button type="button" id="btn-new-ensemble" class="btn-secondary">Lisa uus ansambel tabelisse…</button>
  </div>
</section>

<!-- ===== PARTIID ===== -->
<section class="section" id="s-osad">
  <h2>Partiid (pillid / hääled)</h2>
  <div id="part-list"></div>
  <div class="btn-row">
    <button type="button" id="btn-add-part">+ Lisa partii</button>
    <button type="button" id="btn-new-instr" class="btn-secondary">Lisa uus pill/hää<label for="
    "></label> tabelisse…</button>
  </div>
</section>

<!-- ===== ORKESTRI KOOSSEIS ===== -->
<section class="section" id="s-orch">
  <h2><label><input type="checkbox" id="has-orch"> Orkestri koosseis</label></h2>
  <div id="orch-section" style="display:none">
    <div class="field-row">
      <label>Puupillid:</label>
      <label title="Flöödid">fl <input type="number" id="ww-fl" value="0" min="0" class="orch-num"></label>
      <label title="Oboed">ob <input type="number" id="ww-ob" value="0" min="0" class="orch-num"></label>
      <label title="Klarnetid">cl <input type="number" id="ww-cl" value="0" min="0" class="orch-num"></label>
      <label title="Fagotid">bn <input type="number" id="ww-bn" value="0" min="0" class="orch-num"></label>
    </div>
    <div class="field-row">
      <label>Vasepillid:</label>
      <label title="Metsasarved">hn <input type="number" id="br-hn" value="0" min="0" class="orch-num"></label>
      <label title="Trompetid">tp <input type="number" id="br-tp" value="0" min="0" class="orch-num"></label>
      <label title="Tromboonid">tbn <input type="number" id="br-tbn" value="0" min="0" class="orch-num"></label>
      <label title="Tuubad">tuba <input type="number" id="br-tuba" value="0" min="0" class="orch-num"></label>
    </div>
    <div class="field-row" style="align-items:center">
      <label>Löökpillid:</label>
      <label><input type="checkbox" id="perc-timpani"> Timpanid</label>
      <label style="margin-left:10px">Teised mängijad: <input type="number" id="perc-other" value="0" min="0" style="width:55px"></label>
      <label style="margin-left:10px">Lisad:</label>
      <div class="tag-container" id="perc-extra-tags"></div>
    </div>
    <div class="field-row">
      <label><input type="checkbox" id="orch-strings"> Keelpillid</label>
    </div>
    <div class="field-row" style="align-items:center">
      <label>Muud:</label>
      <div class="tag-container" id="orch-other-tags"></div>
    </div>
  </div>
</section>

<!-- ===== VOKAALDETAILID ===== -->
<section class="section" id="s-vocal" style="display:none">
  <h2>Vokaaldetailid</h2>
  <div class="field-row">
    <label><input type="checkbox" id="vocal-is-choir"> Koor</label>
    <label style="margin-left:20px">Koori tüüp:
      <select id="vocal-choir-type">
        <option value="none">ei ole koor</option>
        <option value="mixed">segakoor</option>
        <option value="male">meeskoor</option>
        <option value="female">naiskoor</option>
        <option value="boys">poistekoor</option>
        <option value="children">lastekoor</option>
        <option value="youth">noortekoor</option>
      </select>
    </label>
    <label style="margin-left:20px">Häälte arv:
      <input type="number" id="vocal-voices" value="0" min="0" style="width:60px">
    </label>
  </div>
  <div class="field-row" style="align-items:center">
    <label>Häälte jaotus:</label>
    <div class="tag-container" id="voice-dist-tags"></div>
  </div>
  <div class="field-row" style="align-items:center">
    <label>Solistid:</label>
    <div class="tag-container" id="soloists-tags"></div>
  </div>
  <div class="field-row">
    <label>Muu: <input type="text" id="vocal-other" style="width:320px"></label>
  </div>
</section>

<!-- ===== KOOR ===== -->
<section class="section" id="s-choir">
  <h2><label><input type="checkbox" id="has-choir"> Koor</label></h2>
  <div id="choir-section" style="display:none">
    <div class="field-row">
      <label>Koori tüüp:
        <select id="choir-type">
          <option value="mixed">Segakoor (mixed)</option>
          <option value="male">Meeskoor (male)</option>
          <option value="female">Naiskoor (female)</option>
          <option value="boys">Poistekoor (boys)</option>
          <option value="children">Lastekoor (children)</option>
          <option value="youth">Noortekoor (youth)</option>
        </select>
      </label>
      <label style="margin-left:20px">Hääli:
        <input type="number" id="choir-voices" value="0" min="0" style="width:60px">
      </label>
    </div>
    <div class="field-row" style="align-items:center">
      <label>Häälte jaotus:</label>
      <div class="tag-container" id="choir-voice-dist-tags"></div>
    </div>
    <div class="field-row" style="align-items:center">
      <label>Solistid:</label>
      <div class="tag-container" id="choir-soloists-tags"></div>
    </div>
    <div class="field-row">
      <label>Märkus (eng): <input type="text" id="choir-note" style="width:340px" placeholder="nt. divisi"></label>
      <label style="margin-left:12px">Märkus (est): <input type="text" id="choir-note-est" style="width:260px"></label>
    </div>
    <div class="field-row">
      <label>Lisainfo: <input type="text" id="choir-other" style="width:560px"></label>
    </div>
  </div>
</section>

<!-- ===== SCORING VARIANTS ===== -->
<section class="section" id="s-variants">
  <h2>Scoring variants (koosseisu variandid)</h2>
  <p style="margin:0 0 10px 0; color:#666; font-size:12px;">Iga variant koosneb sildist ja täielikust instrumentatsiooniobjektist (JSON). Kasuta "Kopeeri praegune" et täita variandi JSON aktiivse vormi põhjal.</p>
  <div id="variant-list"></div>
  <div class="btn-row">
    <button type="button" id="btn-add-variant">+ Lisa variant</button>
  </div>
</section>

<!-- ===== JSON ===== -->
<section class="section" id="s-json">
  <h2>JSON väljund</h2>
  <div class="btn-row">
    <button type="button" id="btn-update-json">↻ Uuenda vormist</button>
    <button type="button" id="btn-parse-json">↑ Parsi vormi</button>
    <button type="button" id="btn-save" class="btn-primary">💾 Salvesta andmebaasi</button>
    <span id="save-status"></span>
  </div>
  <textarea id="json-output" spellcheck="false"></textarea>
</section>

<!-- ===== MODAL: uus pill ===== -->
<dialog id="modal-new-instr">
  <h3>Lisa uus pill tabelisse</h3>
  <div class="field-row">
    <label style="width:130px">Lühend:</label>
    <input type="text" id="new-instr-lyhend" style="width:200px" placeholder="nt. bambfl">
  </div>
  <div class="field-row">
    <label style="width:130px">Nimi (eesti):</label>
    <input type="text" id="new-instr-nimi" style="width:280px" placeholder="nt. bambusflööt">
  </div>
  <div class="field-row">
    <label style="width:130px">Nimi (inglise):</label>
    <input type="text" id="new-instr-nimi-eng" style="width:280px" placeholder="nt. bamboo flute">
  </div>
  <div id="new-instr-error" class="error" style="display:none"></div>
  <div class="btn-row">
    <button type="button" id="btn-new-instr-save" class="btn-primary">Lisa tabelisse</button>
    <button type="button" onclick="document.getElementById('modal-new-instr').close()">Tühista</button>
  </div>
</dialog>

<!-- ===== MODAL: uus ansambel ===== -->
<dialog id="modal-new-ensemble">
  <h3>Lisa uus ansambel tabelisse</h3>
  <div class="field-row">
    <label style="width:130px">ID:</label>
    <input type="text" id="new-ens-id" style="width:240px" placeholder="nt. taiko_ensemble">
  </div>
  <div class="field-row">
    <label style="width:130px">Nimi (eesti):</label>
    <input type="text" id="new-ens-nimi" style="width:280px" placeholder="nt. taiko ansambel">
  </div>
  <div class="field-row">
    <label style="width:130px">Nimi (inglise):</label>
    <input type="text" id="new-ens-nimi-eng" style="width:280px" placeholder="nt. taiko ensemble">
  </div>
  <div id="new-ens-error" class="error" style="display:none"></div>
  <div class="btn-row">
    <button type="button" id="btn-new-ens-save" class="btn-primary">Lisa tabelisse</button>
    <button type="button" onclick="document.getElementById('modal-new-ensemble').close()">Tühista</button>
  </div>
</dialog>

<script>
'use strict';

// ---------------------------------------------------------------------------
// DATA (injected by PHP)
// ---------------------------------------------------------------------------
const INSTRUMENTS = <?= $instrJson ?>;
const ENSEMBLES   = <?= $ensemblesJson ?>;
const API_URL     = 'editor_api.php';

// ---------------------------------------------------------------------------
// UTILITY
// ---------------------------------------------------------------------------

function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function instrLabel(lyhend) {
    const i = INSTRUMENTS.find(x => x.lyhend === lyhend);
    return i ? `${i.lyhend} – ${i.nimi_eng}` : lyhend;
}

function getTagValues(container) {
    if (!container) return [];
    return Array.from(container.querySelectorAll('.tag')).map(t => t.dataset.value);
}

// ---------------------------------------------------------------------------
// INSTRUMENT AUTOCOMPLETE COMBO
// ---------------------------------------------------------------------------

/**
 * Creates a searchable instrument combo widget.
 * onSelect(instrObj) is called when an item is picked.
 * Returns the outer div (with .instr-combo class).
 * div.dataset.value holds the current lyhend.
 */
function createInstrCombo(initialValue, onSelect) {
    const div   = document.createElement('div');
    div.className = 'instr-combo';
    div.dataset.value = initialValue || '';

    const input = document.createElement('input');
    input.type        = 'text';
    input.className   = 'instr-input';
    input.autocomplete = 'off';
    input.placeholder  = 'Otsi pilli…';

    const ul = document.createElement('ul');
    ul.className   = 'suggestions';
    ul.style.display = 'none';

    if (initialValue) {
        input.value = instrLabel(initialValue);
    }

    let activeIdx = -1;

    function showSuggestions(q) {
        const ql = q.toLowerCase();
        const hits = INSTRUMENTS.filter(i =>
            i.lyhend.toLowerCase().includes(ql) ||
            i.nimi.toLowerCase().includes(ql) ||
            i.nimi_eng.toLowerCase().includes(ql)
        ).slice(0, 18);

        ul.innerHTML = '';
        activeIdx = -1;
        if (!hits.length || q === '') { ul.style.display = 'none'; return; }

        hits.forEach((instr, idx) => {
            const li = document.createElement('li');
            li.textContent = `${instr.lyhend} – ${instr.nimi_eng} / ${instr.nimi}`;
            li.dataset.idx = idx;
            li.addEventListener('mousedown', e => {
                e.preventDefault();
                pick(instr);
            });
            ul.appendChild(li);
        });
        ul.style.display = 'block';
        ul._hits = hits;
    }

    function pick(instr) {
        div.dataset.value = instr.lyhend;
        input.value       = instrLabel(instr.lyhend);
        ul.style.display  = 'none';
        if (onSelect) onSelect(instr);
        else updateOutput();
    }

    input.addEventListener('input', () => showSuggestions(input.value));
    input.addEventListener('focus', () => { if (input.value) showSuggestions(input.value); });
    input.addEventListener('blur',  () => setTimeout(() => { ul.style.display = 'none'; }, 180));
    input.addEventListener('keydown', e => {
        const items = ul.querySelectorAll('li');
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
        } else if (e.key === 'Enter' && activeIdx >= 0) {
            e.preventDefault();
            const hits = ul._hits || [];
            if (hits[activeIdx]) pick(hits[activeIdx]);
            return;
        } else {
            return;
        }
        items.forEach((li, i) => li.classList.toggle('active', i === activeIdx));
    });

    div.appendChild(input);
    div.appendChild(ul);
    return div;
}

// ---------------------------------------------------------------------------
// TAG CONTAINER
// ---------------------------------------------------------------------------

/**
 * Makes a single tag element.
 * allowDuplicates is informational for callers; removal always works.
 */
function makeTag(value, isInstrument) {
    const tag = document.createElement('span');
    tag.className    = 'tag';
    tag.dataset.value = value;

    const labelText = isInstrument ? instrLabel(value) : value;
    tag.appendChild(document.createTextNode(labelText + ' '));

    const rm = document.createElement('button');
    rm.type      = 'button';
    rm.className = 'tag-remove';
    rm.textContent = '×';
    rm.title     = 'Eemalda';
    rm.addEventListener('click', () => { tag.remove(); updateOutput(); });

    tag.appendChild(rm);
    return tag;
}

/**
 * Initialises a static .tag-container div with an add-input area.
 * isInstrument = true → uses autocomplete combo
 * allowDuplicates = true → skip duplicate check (used for voice_distribution)
 */
function initTagContainer(container, isInstrument, allowDuplicates) {
    container.innerHTML = '';
    container.dataset.isInstrument    = isInstrument ? '1' : '0';
    container.dataset.allowDuplicates = allowDuplicates ? '1' : '0';

    const addArea = document.createElement('div');
    addArea.className = 'tag-input-area';

    if (isInstrument) {
        let pending = null;
        const combo = createInstrCombo('', instr => {
            pending = instr.lyhend;
        });
        combo.classList.add('tag-combo');
        combo.querySelector('.instr-input').placeholder = 'Lisa pill…';

        const addBtn = document.createElement('button');
        addBtn.type      = 'button';
        addBtn.textContent = '+';
        addBtn.className = 'tag-add-btn';
        addBtn.addEventListener('click', () => {
            const val = combo.dataset.value;
            if (!val) return;
            if (!allowDuplicates && getTagValues(container).includes(val)) return;
            container.insertBefore(makeTag(val, true), addArea);
            combo.dataset.value = '';
            combo.querySelector('.instr-input').value = '';
            pending = null;
            updateOutput();
        });

        // Also allow Enter in the combo input
        combo.querySelector('.instr-input').addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                const val = combo.dataset.value;
                if (!val) return;
                if (!allowDuplicates && getTagValues(container).includes(val)) return;
                container.insertBefore(makeTag(val, true), addArea);
                combo.dataset.value = '';
                combo.querySelector('.instr-input').value = '';
                pending = null;
                updateOutput();
            }
        });

        addArea.appendChild(combo);
        addArea.appendChild(addBtn);
    } else {
        const inp = document.createElement('input');
        inp.type        = 'text';
        inp.className   = 'tag-text-input';
        inp.placeholder = 'Lisa…';

        const addBtn = document.createElement('button');
        addBtn.type      = 'button';
        addBtn.textContent = '+';
        addBtn.className = 'tag-add-btn';

        function addPlain() {
            const val = inp.value.trim();
            if (!val) return;
            if (!allowDuplicates && getTagValues(container).includes(val)) return;
            container.insertBefore(makeTag(val, false), addArea);
            inp.value = '';
            updateOutput();
        }

        addBtn.addEventListener('click', addPlain);
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addPlain(); } });

        addArea.appendChild(inp);
        addArea.appendChild(addBtn);
    }

    container.appendChild(addArea);
}

function rebuildTagContainer(container, values) {
    // Remove existing tags only
    container.querySelectorAll('.tag').forEach(t => t.remove());
    const addArea   = container.querySelector('.tag-input-area');
    const isInstr   = container.dataset.isInstrument    === '1';
    values.forEach(v => {
        const tag = makeTag(v, isInstr);
        container.insertBefore(tag, addArea);
    });
}

// ---------------------------------------------------------------------------
// ENSEMBLE ROW
// ---------------------------------------------------------------------------

function buildEnsembleSelect(selectedId) {
    const sel = document.createElement('select');
    sel.className = 'ensemble-select';

    ENSEMBLES.forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id;
        opt.textContent = `${e.nimi_eng} / ${e.nimi}`;
        if (e.id === selectedId) opt.selected = true;
        sel.appendChild(opt);
    });

    const custom = document.createElement('option');
    custom.value       = '__custom__';
    custom.textContent = '— kohandatud ID —';
    sel.appendChild(custom);

    return sel;
}

function createEnsembleRow(data) {
    data = data || {};
    const row = document.createElement('div');
    row.className = 'ensemble-row';

    const knownId = ENSEMBLES.find(e => e.id === data.ensemble_id);
    const sel     = buildEnsembleSelect(knownId ? data.ensemble_id : null);

    const customIn = document.createElement('input');
    customIn.type        = 'text';
    customIn.className   = 'ensemble-custom-id';
    customIn.placeholder = 'Ansambli ID (nt. taiko_ensemble)';
    customIn.style.width = '200px';

    if (!knownId && data.ensemble_id) {
        sel.value        = '__custom__';
        customIn.value   = data.ensemble_id;
    }
    customIn.style.display = sel.value === '__custom__' ? 'inline-block' : 'none';

    sel.addEventListener('change', () => {
        customIn.style.display = sel.value === '__custom__' ? 'inline-block' : 'none';
        updateOutput();
    });

    const stdLabel = document.createElement('label');
    const stdCb    = document.createElement('input');
    stdCb.type      = 'checkbox';
    stdCb.className = 'ensemble-standard';
    stdCb.checked   = data.standard ?? false;
    stdLabel.appendChild(stdCb);
    stdLabel.appendChild(document.createTextNode(' std'));
    stdLabel.title = 'Standardkoosseis';

    const note = document.createElement('input');
    note.type        = 'text';
    note.className   = 'ensemble-note';
    note.value       = data.note ?? '';
    note.placeholder = 'märkus (eng)';
    note.style.width = '180px';

    const noteEst = document.createElement('input');
    noteEst.type        = 'text';
    noteEst.className   = 'ensemble-note-est';
    noteEst.value       = data.note_est ?? '';
    noteEst.placeholder = 'märkus (est)';
    noteEst.style.width = '180px';

    const rm = document.createElement('button');
    rm.type      = 'button';
    rm.className = 'btn-remove';
    rm.textContent = 'Eemalda';
    rm.addEventListener('click', () => { row.remove(); updateOutput(); });

    [sel, customIn, stdLabel,
     document.createTextNode(' '), note, noteEst, rm
    ].forEach(el => row.appendChild(el));

    [sel, customIn, stdCb, note, noteEst].forEach(el =>
        el.addEventListener('input', updateOutput));

    return row;
}

// ---------------------------------------------------------------------------
// PART ROW
// ---------------------------------------------------------------------------

function createPartRow(data) {
    data = data || {};
    const row = document.createElement('div');
    row.className = 'part-row';

    // --- Top line ---
    const top = document.createElement('div');
    top.className = 'part-row-top';

    const combo = createInstrCombo(data.instrument_id || '', null);

    const cntIn = document.createElement('input');
    cntIn.type      = 'number';
    cntIn.className = 'part-count';
    cntIn.value     = data.count ?? 1;
    cntIn.min       = 1;
    cntIn.style.width = '55px';
    cntIn.title     = 'Arv';

    const roleSel = document.createElement('select');
    roleSel.className = 'part-role';
    [['normal','tavaline'], ['soloist','solist'], ['optional','valikuline'], ['ad_libitum','ad libitum']]
        .forEach(([v, l]) => {
            const o = document.createElement('option');
            o.value = v; o.textContent = l;
            if (v === (data.role || 'normal')) o.selected = true;
            roleSel.appendChild(o);
        });

    const rm = document.createElement('button');
    rm.type = 'button'; rm.className = 'btn-remove'; rm.textContent = 'Eemalda';
    rm.addEventListener('click', () => { row.remove(); updateOutput(); });

    const noteIn = document.createElement('input');
    noteIn.type        = 'text';
    noteIn.className   = 'part-note';
    noteIn.value       = data.note || '';
    noteIn.placeholder = 'märkus (nt. in D, muted…)';
    noteIn.style.width = '220px';
    noteIn.addEventListener('input', updateOutput);

    [combo,
     makeFieldLabel('arv'), cntIn,
     makeFieldLabel('roll'), roleSel,
     makeFieldLabel('märkus'), noteIn,
     rm
    ].forEach(el => top.appendChild(el));

    // --- Sub line: alternatives + doubles ---
    const sub = document.createElement('div');
    sub.className = 'part-sub';

    const altBlock = document.createElement('div');
    altBlock.className = 'part-sub-block';
    const altLbl = document.createElement('div');
    altLbl.className = 'part-sub-label'; altLbl.textContent = 'Alternatiivid:';
    const altTags = document.createElement('div');
    altTags.className = 'tag-container alternatives-tags';
    initTagContainer(altTags, true, false);
    rebuildTagContainer(altTags, data.alternative_instruments || []);
    altBlock.appendChild(altLbl); altBlock.appendChild(altTags);

    const dblBlock = document.createElement('div');
    dblBlock.className = 'part-sub-block';
    const dblLbl = document.createElement('div');
    dblLbl.className = 'part-sub-label'; dblLbl.textContent = 'Topeltpillid:';
    const dblTags = document.createElement('div');
    dblTags.className = 'tag-container doubles-tags';
    initTagContainer(dblTags, true, false);
    rebuildTagContainer(dblTags, data.doubles || []);
    dblBlock.appendChild(dblLbl); dblBlock.appendChild(dblTags);

    sub.appendChild(altBlock);
    sub.appendChild(dblBlock);

    [cntIn, roleSel].forEach(el => el.addEventListener('change', updateOutput));

    row.appendChild(top);
    row.appendChild(sub);
    return row;
}

function makeFieldLabel(text) {
    const span = document.createElement('span');
    span.textContent = ' ' + text + ': ';
    span.style.color = '#666';
    return span;
}

// ---------------------------------------------------------------------------
// JSON BUILD
// ---------------------------------------------------------------------------

function buildJSON() {
    const obj = {};

    obj.total_player_count = parseInt(document.getElementById('total-player-count').value, 10) || 0;

    // electronics
    if (document.getElementById('has-electronics').checked) {
        obj.electronics = {
            type:    document.getElementById('electronics-type').value.trim() || 'electronics',
            details: document.getElementById('electronics-details').value.trim(),
        };
    } else {
        obj.electronics = null;
    }

    obj.has_vocal = document.getElementById('has-vocal').checked;

    // ensembles
    obj.ensembles = [];
    document.querySelectorAll('#ensemble-list .ensemble-row').forEach(row => {
        const sel      = row.querySelector('.ensemble-select');
        const customIn = row.querySelector('.ensemble-custom-id');
        const ensId    = sel.value === '__custom__' ? customIn.value.trim() : sel.value;
        if (!ensId) return;
        obj.ensembles.push({
            ensemble_id:  ensId,
            player_count: obj.total_player_count,
            standard:     row.querySelector('.ensemble-standard').checked,
            note:         row.querySelector('.ensemble-note').value.trim(),
            note_est:     row.querySelector('.ensemble-note-est').value.trim(),
        });
    });

    // parts
    obj.parts = [];
    document.querySelectorAll('#part-list .part-row').forEach(row => {
        const combo = row.querySelector('.instr-combo');
        const instrId = combo ? combo.dataset.value : '';
        obj.parts.push({
            instrument_id:           instrId,
            alternative_instruments: getTagValues(row.querySelector('.alternatives-tags')),
            doubles:                 getTagValues(row.querySelector('.doubles-tags')),
            count:                   parseInt(row.querySelector('.part-count').value, 10) || 1,
            role:                    row.querySelector('.part-role').value,
            note:                    (row.querySelector('.part-note').value || '').trim(),
        });
    });

    // orchestral_layout
    if (document.getElementById('has-orch').checked) {
        obj.orchestral_layout = {
            woodwinds: ['fl','ob','cl','bn'].map(id =>
                parseInt(document.getElementById('ww-' + id).value, 10) || 0),
            brass: ['hn','tp','tbn','tuba'].map(id =>
                parseInt(document.getElementById('br-' + id).value, 10) || 0),
            percussion: {
                timpani:      document.getElementById('perc-timpani').checked,
                other_players: parseInt(document.getElementById('perc-other').value, 10) || 0,
                extra:        getTagValues(document.getElementById('perc-extra-tags')),
            },
            strings: document.getElementById('orch-strings').checked,
            other:   getTagValues(document.getElementById('orch-other-tags')),
        };
    } else {
        obj.orchestral_layout = null;
    }

    // vocal_details — choir section overrides generic vocal section
    const hasChoir = document.getElementById('has-choir').checked;
    if (hasChoir) {
        obj.has_vocal = true;
        const choirType = document.getElementById('choir-type').value;
        obj.ensembles.unshift({
            ensemble_id:  choirType + '_choir',
            player_count: obj.total_player_count,
            standard:     true,
            note:         document.getElementById('choir-note').value.trim(),
            note_est:     document.getElementById('choir-note-est').value.trim(),
        });
        obj.vocal_details = {
            is_choir:           true,
            choir_type:         choirType,
            voices:             parseInt(document.getElementById('choir-voices').value, 10) || 0,
            voice_distribution: getTagValues(document.getElementById('choir-voice-dist-tags')),
            soloists:           getTagValues(document.getElementById('choir-soloists-tags')),
            other:              document.getElementById('choir-other').value.trim(),
        };
    } else if (obj.has_vocal) {
        obj.vocal_details = {
            is_choir:           document.getElementById('vocal-is-choir').checked,
            choir_type:         document.getElementById('vocal-choir-type').value,
            voices:             parseInt(document.getElementById('vocal-voices').value, 10) || 0,
            voice_distribution: getTagValues(document.getElementById('voice-dist-tags')),
            soloists:           getTagValues(document.getElementById('soloists-tags')),
            other:              document.getElementById('vocal-other').value.trim(),
        };
    } else {
        obj.vocal_details = null;
    }

    // scoring_variants
    const variants = [];
    document.querySelectorAll('#variant-list .variant-row').forEach(row => {
        const label = row.querySelector('.variant-label-input').value.trim();
        const jsonTa = row.querySelector('.variant-json');
        const errEl  = row.querySelector('.variant-json-error');
        try {
            const instr = JSON.parse(jsonTa.value);
            variants.push({ label, instrumentation: instr });
            errEl.textContent = '';
        } catch (e) {
            errEl.textContent = 'JSON viga: ' + e.message;
        }
    });
    if (variants.length > 0) {
        obj.scoring_variants = variants;
    }

    return obj;
}

function updateOutput() {
    const out = document.getElementById('json-output');
    try {
        out.value = JSON.stringify(buildJSON(), null, 2);
    } catch (e) {
        // ignore
    }
}

// ---------------------------------------------------------------------------
// JSON PARSE → FORM
// ---------------------------------------------------------------------------

function parseFromJSON(json) {
    if (typeof json !== 'object' || json === null) return;

    document.getElementById('total-player-count').value =
        json.total_player_count ?? 0;

    const hasVocal = !!json.has_vocal;
    document.getElementById('has-vocal').checked = hasVocal;

    if (json.electronics) {
        document.getElementById('has-electronics').checked = true;
        document.getElementById('electronics-section').style.display = 'block';
        document.getElementById('electronics-type').value    = json.electronics.type    || 'electronics';
        document.getElementById('electronics-details').value = json.electronics.details || '';
    } else {
        document.getElementById('has-electronics').checked = false;
        document.getElementById('electronics-section').style.display = 'none';
    }

    // Ensembles — skip choir ensemble if choir section is active
    const isChoir = !!(json.has_vocal && json.vocal_details && json.vocal_details.is_choir);
    const choirEnsId = isChoir ? ((json.vocal_details.choir_type || 'mixed') + '_choir') : null;
    const eList = document.getElementById('ensemble-list');
    eList.innerHTML = '';
    (json.ensembles || [])
        .filter(e => !choirEnsId || e.ensemble_id !== choirEnsId)
        .forEach(e => eList.appendChild(createEnsembleRow(e)));

    // Parts
    const pList = document.getElementById('part-list');
    pList.innerHTML = '';
    (json.parts || []).forEach(p => pList.appendChild(createPartRow(p)));

    // Orchestral layout
    const hasOrch = !!(json.orchestral_layout);
    document.getElementById('has-orch').checked = hasOrch;
    document.getElementById('orch-section').style.display = hasOrch ? 'block' : 'none';
    if (hasOrch) {
        const ol = json.orchestral_layout;
        const ww = ol.woodwinds || [0,0,0,0];
        ['fl','ob','cl','bn'].forEach((id, i) =>
            document.getElementById('ww-' + id).value = ww[i] ?? 0);
        const br = ol.brass || [0,0,0,0];
        ['hn','tp','tbn','tuba'].forEach((id, i) =>
            document.getElementById('br-' + id).value = br[i] ?? 0);
        const perc = ol.percussion || {};
        document.getElementById('perc-timpani').checked = perc.timpani ?? false;
        document.getElementById('perc-other').value     = perc.other_players ?? 0;
        rebuildTagContainer(document.getElementById('perc-extra-tags'), perc.extra || []);
        document.getElementById('orch-strings').checked  = ol.strings ?? false;
        rebuildTagContainer(document.getElementById('orch-other-tags'), ol.other || []);
    }

    // Choir section
    document.getElementById('has-choir').checked = isChoir;
    document.getElementById('choir-section').style.display = isChoir ? 'block' : 'none';
    if (isChoir && json.vocal_details) {
        const vd = json.vocal_details;
        document.getElementById('choir-type').value   = vd.choir_type || 'mixed';
        document.getElementById('choir-voices').value = vd.voices ?? 0;
        rebuildTagContainer(document.getElementById('choir-voice-dist-tags'), vd.voice_distribution || []);
        rebuildTagContainer(document.getElementById('choir-soloists-tags'), vd.soloists || []);
        document.getElementById('choir-other').value  = vd.other || '';
        // Restore choir ensemble note fields
        const choirEns = (json.ensembles || []).find(e => e.ensemble_id === choirEnsId);
        document.getElementById('choir-note').value     = choirEns ? (choirEns.note     || '') : '';
        document.getElementById('choir-note-est').value = choirEns ? (choirEns.note_est || '') : '';
    }

    // Vocal details (generic — only when not choir)
    document.getElementById('s-vocal').style.display = (hasVocal && !isChoir) ? 'block' : 'none';
    if (hasVocal && !isChoir && json.vocal_details) {
        const vd = json.vocal_details;
        document.getElementById('vocal-is-choir').checked  = vd.is_choir ?? false;
        document.getElementById('vocal-choir-type').value  = vd.choir_type || 'none';
        document.getElementById('vocal-voices').value      = vd.voices ?? 0;
        rebuildTagContainer(document.getElementById('voice-dist-tags'), vd.voice_distribution || []);
        rebuildTagContainer(document.getElementById('soloists-tags'), vd.soloists || []);
        document.getElementById('vocal-other').value       = vd.other || '';
    }

    // Scoring variants
    const vList = document.getElementById('variant-list');
    vList.innerHTML = '';
    (json.scoring_variants || []).forEach(v => {
        vList.appendChild(createVariantRow(v.label || '', v.instrumentation));
    });

    updateOutput();
}

// ---------------------------------------------------------------------------
// SCORING VARIANT ROW
// ---------------------------------------------------------------------------

function createVariantRow(label, instrObj) {
    const row = document.createElement('div');
    row.className = 'variant-row';

    const top = document.createElement('div');
    top.className = 'variant-row-top';

    const labelIn = document.createElement('input');
    labelIn.type        = 'text';
    labelIn.className   = 'variant-label-input';
    labelIn.value       = label || '';
    labelIn.placeholder = 'Sildi nimi (nt. male choir, soloists…)';
    labelIn.addEventListener('input', updateOutput);

    const copyBtn = document.createElement('button');
    copyBtn.type        = 'button';
    copyBtn.className   = 'btn-secondary';
    copyBtn.textContent = '← Kopeeri praegune vorm siia';
    copyBtn.title       = 'Täidab variandi JSON vormi praeguse instrumentatsiooniga (ilma variantideta)';
    copyBtn.addEventListener('click', () => {
        const current = buildJSON();
        delete current.scoring_variants;
        ta.value = JSON.stringify(current, null, 2);
        errEl.textContent = '';
        updateOutput();
    });

    const rm = document.createElement('button');
    rm.type      = 'button';
    rm.className = 'btn-remove';
    rm.textContent = 'Kustuta';
    rm.addEventListener('click', () => { row.remove(); updateOutput(); });

    top.appendChild(labelIn);
    top.appendChild(copyBtn);
    top.appendChild(rm);

    const ta = document.createElement('textarea');
    ta.className   = 'variant-json';
    ta.spellcheck  = false;
    ta.placeholder = 'Instrumentatsiooni JSON…';
    if (instrObj !== undefined && instrObj !== null) {
        ta.value = JSON.stringify(instrObj, null, 2);
    }
    ta.addEventListener('input', updateOutput);

    const errEl = document.createElement('div');
    errEl.className = 'variant-json-error';

    row.appendChild(top);
    row.appendChild(ta);
    row.appendChild(errEl);
    return row;
}

// ---------------------------------------------------------------------------
// UI TOGGLES
// ---------------------------------------------------------------------------

function toggleChoir() {
    const show = document.getElementById('has-choir').checked;
    document.getElementById('choir-section').style.display = show ? 'block' : 'none';
    if (show) {
        // Choir implies vocal; hide generic vocal section to avoid conflict
        document.getElementById('has-vocal').checked = true;
        document.getElementById('s-vocal').style.display = 'none';
    } else {
        // Reveal generic vocal section only if has-vocal is still checked
        const hasVocal = document.getElementById('has-vocal').checked;
        document.getElementById('s-vocal').style.display = hasVocal ? 'block' : 'none';
    }
    updateOutput();
}

function toggleElectronics() {
    const show = document.getElementById('has-electronics').checked;
    document.getElementById('electronics-section').style.display = show ? 'block' : 'none';
    updateOutput();
}

function toggleOrch() {
    const show = document.getElementById('has-orch').checked;
    document.getElementById('orch-section').style.display = show ? 'block' : 'none';
    updateOutput();
}

function toggleVocalSection() {
    const show = document.getElementById('has-vocal').checked;
    document.getElementById('s-vocal').style.display = show ? 'block' : 'none';
    updateOutput();
}

// ---------------------------------------------------------------------------
// AJAX
// ---------------------------------------------------------------------------

async function apiPost(action, body) {
    const resp = await fetch(`${API_URL}?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return resp.json();
}

async function loadWork() {
    const id = parseInt(document.getElementById('work-id').value, 10);
    if (!id) { setStatus('load-status', 'error', 'Sisesta kehtiv ID'); return; }

    setStatus('load-status', '', 'Laadin…');
    try {
        const resp = await fetch(`${API_URL}?action=load&id=${id}`);
        const data = await resp.json();
        if (data.error) { setStatus('load-status', 'error', data.error); return; }

        document.getElementById('work-title').value    = data.pealkiri || '';
        document.getElementById('koosseis-tekst').value = data.koosseis_tekst || '';
        document.getElementById('koosseis-tekst-row').style.display =
            data.koosseis_tekst ? 'flex' : 'none';

        if (data.intrumentatsioon) {
            parseFromJSON(data.intrumentatsioon);
        }
        setStatus('load-status', 'ok', 'Laaditud ✓');
    } catch (e) {
        setStatus('load-status', 'error', 'Viga: ' + e.message);
    }
}

async function saveWork() {
    const id = parseInt(document.getElementById('work-id').value, 10);
    if (!id) { setStatus('save-status', 'error', 'Sisesta kehtiv ID'); return; }

    let instrObj;
    try {
        instrObj = JSON.parse(document.getElementById('json-output').value);
    } catch (e) {
        setStatus('save-status', 'error', 'JSON on vigane – paranda või uuenda vormist');
        return;
    }

    setStatus('save-status', '', 'Salvestan…');
    try {
        const data = await apiPost('save', {
            id,
            pealkiri:        document.getElementById('work-title').value,
            koosseis_tekst:  document.getElementById('koosseis-tekst').value,
            intrumentatsioon: instrObj,
        });
        if (data.error) { setStatus('save-status', 'error', data.error); return; }
        setStatus('save-status', 'ok', 'Salvestatud ✓');
    } catch (e) {
        setStatus('save-status', 'error', 'Viga: ' + e.message);
    }
}

async function addNewInstrument() {
    const lyhend  = document.getElementById('new-instr-lyhend').value.trim();
    const nimi    = document.getElementById('new-instr-nimi').value.trim();
    const nimiEng = document.getElementById('new-instr-nimi-eng').value.trim();
    const errEl   = document.getElementById('new-instr-error');
    errEl.style.display = 'none';

    try {
        const data = await apiPost('add_instrument', { lyhend, nimi, nimi_eng: nimiEng });
        if (data.error) {
            errEl.textContent = data.error;
            errEl.style.display = 'block';
            return;
        }
        // Add to local INSTRUMENTS array
        INSTRUMENTS.push({ lyhend: data.lyhend, nimi: data.nimi, nimi_eng: data.nimi_eng });
        INSTRUMENTS.sort((a, b) => a.nimi_eng.localeCompare(b.nimi_eng));

        document.getElementById('modal-new-instr').close();
        ['new-instr-lyhend','new-instr-nimi','new-instr-nimi-eng'].forEach(id =>
            document.getElementById(id).value = '');
    } catch (e) {
        errEl.textContent = 'Viga: ' + e.message;
        errEl.style.display = 'block';
    }
}

async function addNewEnsemble() {
    const id      = document.getElementById('new-ens-id').value.trim();
    const nimi    = document.getElementById('new-ens-nimi').value.trim();
    const nimiEng = document.getElementById('new-ens-nimi-eng').value.trim();
    const errEl   = document.getElementById('new-ens-error');
    errEl.style.display = 'none';

    try {
        const data = await apiPost('add_ensemble', { id, nimi, nimi_eng: nimiEng });
        if (data.error) {
            errEl.textContent = data.error;
            errEl.style.display = 'block';
            return;
        }
        // Add to local ENSEMBLES array and refresh all selects
        ENSEMBLES.push({ id: data.id, nimi: data.nimi, nimi_eng: data.nimi_eng });
        ENSEMBLES.sort((a, b) => a.nimi_eng.localeCompare(b.nimi_eng));
        refreshAllEnsembleSelects();

        document.getElementById('modal-new-ensemble').close();
        ['new-ens-id','new-ens-nimi','new-ens-nimi-eng'].forEach(elId =>
            document.getElementById(elId).value = '');
    } catch (e) {
        errEl.textContent = 'Viga: ' + e.message;
        errEl.style.display = 'block';
    }
}

/** Rebuild all ensemble <select> elements after a new ensemble is added. */
function refreshAllEnsembleSelects() {
    document.querySelectorAll('.ensemble-row').forEach(row => {
        const sel     = row.querySelector('.ensemble-select');
        const current = sel.value;
        // Rebuild options
        sel.innerHTML = '';
        ENSEMBLES.forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = `${e.nimi_eng} / ${e.nimi}`;
            sel.appendChild(opt);
        });
        const custom = document.createElement('option');
        custom.value = '__custom__'; custom.textContent = '— kohandatud ID —';
        sel.appendChild(custom);
        sel.value = current;
    });
}

// ---------------------------------------------------------------------------
// STATUS HELPER
// ---------------------------------------------------------------------------

function setStatus(elId, type, msg) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = msg;
    el.className = type === 'error' ? 'error' : (type === 'ok' ? 'success' : '');
    el.style.display = msg ? 'inline' : 'none';
}

// ---------------------------------------------------------------------------
// RESET (new work)
// ---------------------------------------------------------------------------

function resetForm() {
    document.getElementById('work-id').value    = '';
    document.getElementById('work-title').value = '';
    document.getElementById('koosseis-tekst').value = '';
    document.getElementById('koosseis-tekst-row').style.display = 'none';
    document.getElementById('variant-list').innerHTML = '';
    parseFromJSON({
        total_player_count: 0,
        electronics: null,
        has_vocal: false,
        ensembles: [],
        parts: [],
        orchestral_layout: null,
        vocal_details: null,
    });
    setStatus('load-status', '', '');
    setStatus('save-status', '', '');
}

// ---------------------------------------------------------------------------
// INIT
// ---------------------------------------------------------------------------

function init() {
    // Wire static tag containers
    initTagContainer(document.getElementById('perc-extra-tags'), false, false);
    initTagContainer(document.getElementById('orch-other-tags'), false, false);
    initTagContainer(document.getElementById('voice-dist-tags'), true,  true);  // allow duplicates
    initTagContainer(document.getElementById('soloists-tags'),   true,  false);
    initTagContainer(document.getElementById('choir-voice-dist-tags'), true, true);  // allow duplicates
    initTagContainer(document.getElementById('choir-soloists-tags'),   true, false);

    // Button wiring
    document.getElementById('btn-load').addEventListener('click', loadWork);
    document.getElementById('btn-new').addEventListener('click', resetForm);
    document.getElementById('btn-save').addEventListener('click', saveWork);

    document.getElementById('btn-add-ensemble').addEventListener('click', () => {
        document.getElementById('ensemble-list').appendChild(createEnsembleRow());
        updateOutput();
    });
    document.getElementById('btn-add-part').addEventListener('click', () => {
        document.getElementById('part-list').appendChild(createPartRow());
        updateOutput();
    });
    document.getElementById('btn-add-variant').addEventListener('click', () => {
        document.getElementById('variant-list').appendChild(createVariantRow('', null));
        updateOutput();
    });

    document.getElementById('btn-new-ensemble').addEventListener('click', () =>
        document.getElementById('modal-new-ensemble').showModal());
    document.getElementById('btn-new-instr').addEventListener('click', () =>
        document.getElementById('modal-new-instr').showModal());

    document.getElementById('btn-new-instr-save').addEventListener('click', addNewInstrument);
    document.getElementById('btn-new-ens-save').addEventListener('click', addNewEnsemble);

    document.getElementById('has-electronics').addEventListener('change', toggleElectronics);
    document.getElementById('has-vocal').addEventListener('change', toggleVocalSection);
    document.getElementById('has-orch').addEventListener('change', toggleOrch);
    document.getElementById('has-choir').addEventListener('change', toggleChoir);

    // Auto-update output on any change in fixed sections
    ['total-player-count','electronics-type','electronics-details',
     'perc-timpani','perc-other','orch-strings',
     'vocal-is-choir','vocal-choir-type','vocal-voices','vocal-other',
     'ww-fl','ww-ob','ww-cl','ww-bn',
     'br-hn','br-tp','br-tbn','br-tuba',
     'choir-type','choir-voices','choir-note','choir-note-est','choir-other',
    ].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateOutput);
        if (el) el.addEventListener('change', updateOutput);
    });

    // JSON textarea buttons
    document.getElementById('btn-update-json').addEventListener('click', updateOutput);
    document.getElementById('btn-parse-json').addEventListener('click', () => {
        try {
            const json = JSON.parse(document.getElementById('json-output').value);
            parseFromJSON(json);
        } catch (e) {
            alert('JSON on vigane:\n' + e.message);
        }
    });

    // Allow Enter to trigger load
    document.getElementById('work-id').addEventListener('keydown', e => {
        if (e.key === 'Enter') loadWork();
    });

    // Initial empty output
    updateOutput();
}

document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
