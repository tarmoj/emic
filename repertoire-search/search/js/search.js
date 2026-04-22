const form = document.getElementById('searchForm');
const genreSelect = document.getElementById('genreId');
const composerSelect = document.getElementById('composerId');
const statusEl = document.getElementById('status');
const resultsEl = document.getElementById('results');
const instrumentInput = document.getElementById('instrumentInput');
const instrumentMenu = document.getElementById('instrumentMenu');
const instrumentTags = document.getElementById('instrumentTags');
const resetBtn = document.getElementById('resetBtn');

let selectedInstruments = [];
let instrumentCache = [];
let defaultRangeFilters = null;

function setStatus(text) {
  statusEl.textContent = text;
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = String(value ?? '');
  return div.innerHTML;
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, options);
  if (!response.ok) {
    console.log("Error. Response:", response, url, options);
    throw new Error('HTTP ' + response.status);
  }
  return response.json();
}

function renderTags() {
  instrumentTags.innerHTML = '';

  selectedInstruments.forEach((item) => {
    const tag = document.createElement('span');
    tag.className = 'tag';
    tag.innerHTML = `${escapeHtml(item.lyhend)} - ${escapeHtml(item.nimi)} <button type="button" data-abbr="${escapeHtml(item.lyhend)}">x</button>`;
    instrumentTags.appendChild(tag);
  });
}

function addInstrument(item) {
  if (selectedInstruments.some((x) => x.lyhend === item.lyhend)) {
    return;
  }
  selectedInstruments.push(item);
  renderTags();
}

function removeInstrument(abbr) {
  selectedInstruments = selectedInstruments.filter((item) => item.lyhend !== abbr);
  renderTags();
}

instrumentTags.addEventListener('click', (event) => {
  const btn = event.target.closest('button[data-abbr]');
  if (!btn) return;
  removeInstrument(btn.dataset.abbr);
});

async function loadMetadata() {
  const data = await fetchJson('./api/metadata.php');
  if (!data.ok) {
    throw new Error(data.error || 'Metaandmete laadimine ebaõnnestus');
  }

  const yearMax = data.yearMax;
  ['bornYearTo', 'compositionYearTo'].forEach((id) => {
    const el = document.getElementById(id);
    el.value = yearMax;
    el.max = yearMax;
  });

  data.genres.forEach((genre) => {
    const opt = document.createElement('option');
    opt.value = genre.id;
    opt.textContent = genre.nimi;
    genreSelect.appendChild(opt);
  });

  data.composers.forEach((composer) => {
    const opt = document.createElement('option');
    opt.value = composer.id;
    opt.textContent = composer.nimi;
    composerSelect.appendChild(opt);
  });

  defaultRangeFilters = {
    bornYearFrom: Number(document.getElementById('bornYearFrom').value || 0),
    bornYearTo: Number(document.getElementById('bornYearTo').value || 0),
    compositionYearFrom: Number(document.getElementById('compositionYearFrom').value || 0),
    compositionYearTo: Number(document.getElementById('compositionYearTo').value || 0),
    durationFrom: Number(document.getElementById('durationFrom').value || 0),
    durationTo: Number(document.getElementById('durationTo').value || 0),
    performersFrom: Number(document.getElementById('performersFrom').value || 0),
    performersTo: Number(document.getElementById('performersTo').value || 0),
    soloistsFrom: Number(document.getElementById('soloistsFrom').value || 0),
    soloistsTo: Number(document.getElementById('soloistsTo').value || 0)
  };
}

function renderInstrumentMenu(items) {
  instrumentMenu.innerHTML = '';

  if (!items.length) {
    instrumentMenu.classList.remove('show');
    return;
  }

  items.forEach((item) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = `${item.lyhend} - ${item.nimi} (${item.nimi_eng})`;
    btn.addEventListener('click', () => {
      addInstrument(item);
      instrumentInput.value = '';
      instrumentMenu.classList.remove('show');
    });
    instrumentMenu.appendChild(btn);
  });

  instrumentMenu.classList.add('show');
}

let autocompleteTimer;
instrumentInput.addEventListener('input', () => {
  clearTimeout(autocompleteTimer);

  const q = instrumentInput.value.trim();
  if (!q) {
    instrumentMenu.classList.remove('show');
    return;
  }

  autocompleteTimer = setTimeout(async () => {
    try {
      const data = await fetchJson('./api/instruments.php?q=' + encodeURIComponent(q));
      if (!data.ok) {
        throw new Error(data.error || 'Instrumentide laadimine ebaõnnestus');
      }
      instrumentCache = data.items;
      renderInstrumentMenu(instrumentCache);
    } catch (err) {
      setStatus('Instrumentide otsing ebaonnestus.');
    }
  }, 180);
});

document.addEventListener('click', (event) => {
  if (!event.target.closest('.autocomplete')) {
    instrumentMenu.classList.remove('show');
  }
});

function buildPayload() {
  const data = new FormData(form);
  const payload = Object.fromEntries(data.entries());

  const intKeys = [
    'genreId',
    'composerId',
    'bornYearFrom',
    'bornYearTo',
    'compositionYearFrom',
    'compositionYearTo',
    'durationFrom',
    'durationTo',
    'performersFrom',
    'performersTo',
    'soloistsFrom',
    'soloistsTo'
  ];

  intKeys.forEach((key) => {
    payload[key] = Number(payload[key] || 0);
  });

  const defaults = defaultRangeFilters || {
    bornYearFrom: 1845,
    bornYearTo: 0,
    compositionYearFrom: 1845,
    compositionYearTo: 0,
    durationFrom: 0,
    durationTo: 480,
    performersFrom: 0,
    performersTo: 100,
    soloistsFrom: 0,
    soloistsTo: 20
  };

  payload.activeFilters = {
    bornYear: payload.bornYearFrom !== defaults.bornYearFrom || payload.bornYearTo !== defaults.bornYearTo,
    compositionYear: payload.compositionYearFrom !== defaults.compositionYearFrom || payload.compositionYearTo !== defaults.compositionYearTo,
    duration: payload.durationFrom !== defaults.durationFrom || payload.durationTo !== defaults.durationTo,
    performers: payload.performersFrom !== defaults.performersFrom || payload.performersTo !== defaults.performersTo,
    soloists: payload.soloistsFrom !== defaults.soloistsFrom || payload.soloistsTo !== defaults.soloistsTo
  };

  payload.onlySelectedInstruments = document.getElementById('onlySelectedInstruments').checked;
  payload.selectedInstruments = selectedInstruments.map((item) => item.lyhend);
  payload.page = 1;
  payload.perPage = 50;

  return payload;
}

function renderResults(data) {
  if (!data.items.length) {
    resultsEl.innerHTML = '<p>Tulemusi ei leitud.</p>';
    return;
  }

  const html = data.items.map((item) => {
    return `
      <article class="item">
        <a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(item.helilooja)} - ${escapeHtml(item.pealkiri)}</a>
        <div class="meta">${escapeHtml(item.koosseis_tekst || '-')}</div>
        <div class="meta">Aasta: ${escapeHtml(item.aasta ?? '-')} | Kestus: ${escapeHtml(item.pikkus_min ?? '-')} min | Esitajaid: ${escapeHtml(item.esitajaid ?? '-')} | Soliste: ${escapeHtml(item.soliste ?? '-')}</div>
      </article>
    `;
  }).join('');

  resultsEl.innerHTML = html;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  setStatus('Otsin...');

  try {
    const payload = buildPayload();
    const data = await fetchJson('./api/search.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!data.ok) {
      throw new Error(data.error || 'Otsing ebaonnestus');
    }

    setStatus(`Leitud: ${data.total}`);
    renderResults(data);
  } catch (err) {
    console.error(err);
    setStatus('Otsing ebaonnestus. Kontrolli serveri logi.');
  }
});

resetBtn.addEventListener('click', () => {
  form.reset();
  selectedInstruments = [];
  renderTags();
  resultsEl.innerHTML = '';
  setStatus('Filtrid on tyhjendatud.');
});

async function init() {
  setStatus('Laen andmeid...');
  try {
    await loadMetadata();
    setStatus('Valmis. Sisesta filtrid ja vajuta Otsi.');
  } catch (err) {
    setStatus('Alglaadimine ebaonnestus. Kontrolli andmebaasi uhendust.');
  }
}
init();

