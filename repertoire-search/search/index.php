<!doctype html>
<html lang="et">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EMIC teoste otsing</title>
    <link rel="stylesheet" href="./style.css">
    <style>
        :root {
            --bg: #f4f1e8;
            --panel: #ffffff;
            --accent: #315f42;
            --accent-2: #b56d37;
            --text: #23312a;
            --line: #d9d4c5;
        }
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: radial-gradient(circle at 20% 10%, #fff7df, var(--bg)); color: var(--text); }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 24px 16px 40px; }
        .hero { background: linear-gradient(135deg, #f7f0dc, #e9f3ea); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 18px; }
        .hero h1 { margin: 0 0 8px; font-size: 2rem; }
        .hero p { margin: 0; }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
        .form-section { border-bottom: 1px solid var(--line); padding-bottom: 16px; margin-bottom: 16px; }
        .form-section:last-of-type { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .grid { display: grid; grid-template-columns: repeat(12, minmax(0,1fr)); gap: 12px; }
        .field { grid-column: span 12; }
        .field.half { grid-column: span 6; }
        .field.third { grid-column: span 4; }
        .field.full { grid-column: span 12; }
        label { display: block; font-size: .92rem; margin-bottom: 6px; font-weight: 700; }
        input[type=text], input[type=number], select { width: 100%; box-sizing: border-box; border: 1px solid var(--line); border-radius: 8px; padding: 10px; font: inherit; background: #fff; }
        /* Range slider */
        .range-field { grid-column: span 6; }
        .range-label { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }
        .range-label span { font-size: .92rem; font-weight: 700; }
        .range-label output { font-size: .88rem; color: var(--accent); font-weight: 700; }
        .range-wrap { display: flex; gap: 8px; align-items: center; }
        .range-wrap input[type=range] { flex: 1; accent-color: var(--accent); }
        /* Accordion */
        .accordion-toggle { background: none; border: none; padding: 0; font: inherit; font-size: .92rem; color: var(--accent); font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; border-radius: 0; }
        .accordion-toggle::before { content: '▶'; font-size: .7rem; transition: transform .2s; }
        .accordion-toggle[aria-expanded=true]::before { transform: rotate(90deg); }
        .accordion-body { display: none; margin-top: 12px; }
        .accordion-body.open { display: block; }
        .actions { display: flex; gap: 10px; margin-top: 16px; }
        button.primary { background: var(--accent); color: #fff; border: 0; border-radius: 999px; padding: 10px 18px; font: inherit; font-weight: 700; cursor: pointer; }
        button.secondary { background: #ece8da; color: var(--text); border: 0; border-radius: 999px; padding: 10px 18px; font: inherit; font-weight: 700; cursor: pointer; }
        .tags { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
        .tag { border: 1px solid #c8d6c6; background: #f0f7ef; color: #1f4f30; border-radius: 999px; padding: 5px 10px; font-size: .86rem; }
        .tag button { padding: 0; margin-left: 8px; background: transparent; color: inherit; border: none; cursor: pointer; font-weight: 700; }
        .autocomplete { position: relative; }
        .menu { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 20; background: #fff; border: 1px solid var(--line); border-radius: 8px; max-height: 220px; overflow: auto; display: none; }
        .menu.show { display: block; }
        .menu button { display: block; width: 100%; text-align: left; border-radius: 0; padding: 10px; background: #fff; border: none; border-bottom: 1px solid #f0eee6; font: inherit; cursor: pointer; }
        .menu button:hover { background: #f5f9f5; }
        .results { margin-top: 16px; }
        .item { border-top: 1px solid var(--line); padding: 12px 0; }
        .item a { color: var(--accent); font-weight: 700; text-decoration: none; }
        .meta { color: #5e635e; font-size: .92rem; margin-top: 4px; }
        .status { margin-top: 12px; color: #5d4f2f; }
        @media (max-width: 900px) {
            .field.half, .field.third, .range-field { grid-column: span 12; }
        }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="hero">
            <h1>EMIC teoste otsing</h1>
            <p>Prototüüp eesti keeles. Otsi helilooja, pealkirja, žanri ja koosseisu järgi.</p>
        </section>

        <section class="panel">
            <form id="searchForm" novalidate>

                <!-- Section 1: Basic filters -->
                <div class="form-section">
                    <div class="grid">
                        <div class="field third">
                            <label for="genreId">Žanr</label>
                            <select id="genreId" name="genreId"><option value="">Kõik žanrid</option></select>
                        </div>
                        <div class="field third">
                            <label for="composerId">Helilooja</label>
                            <select id="composerId" name="composerId"><option value="">Kõik heliloojad</option></select>
                        </div>
                        <div class="field third">
                            <label for="title">Pealkiri</label>
                            <input id="title" name="title" type="text" placeholder="Sõna või sõna osa">
                        </div>
                        <div class="field full">
                            <label for="keyword">Otsingusõna</label>
                            <input id="keyword" name="keyword" type="text" placeholder="Vaba teksti otsing">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Instrumentation -->
                <div class="form-section">
                    <div class="grid">
                        <div class="field half autocomplete">
                            <label for="instrumentInput">Koosseis</label>
                            <input id="instrumentInput" type="text" placeholder="Nt vn, fl, hp ...">
                            <div id="instrumentMenu" class="menu" role="listbox"></div>
                            <div id="instrumentTags" class="tags"></div>
                        </div>
                        <div class="range-field">
                            <div class="range-label">
                                <span>Esitajaid</span>
                                <output id="performersVal">0 – 100</output>
                            </div>
                            <div class="range-wrap">
                                <input id="performersFrom" name="performersFrom" type="range" min="0" max="16" value="0">
                                <input id="performersTo" name="performersTo" type="range" min="0" max="16" value="16">
                            </div>
                        </div>
                        <div class="field full" style="display:flex;align-items:center;gap:8px;">
                            <input id="onlySelectedInstruments" name="onlySelectedInstruments" type="checkbox" style="width:auto;">
                            <label for="onlySelectedInstruments" style="margin:0;font-weight:400;">Ainult need instrumendid/hääled</label>
                        </div>
                    </div>
                </div>

                <!-- Section 3: More options (accordion) -->
                <div class="form-section">
                    <button type="button" class="accordion-toggle" id="moreToggle" aria-expanded="false" aria-controls="moreBody">
                        Rohkem valikuid
                    </button>
                    <div class="accordion-body" id="moreBody">
                        <div class="grid" style="margin-top:0;">
                            <div class="range-field">
                                <div class="range-label">
                                    <span>Helilooja sünniaasta</span>
                                    <output id="bornYearVal">1845 – <span id="bornYearToLabel"></span></output>
                                </div>
                                <div class="range-wrap">
                                    <input id="bornYearFrom" name="bornYearFrom" type="range" min="1845" max="2026" value="1845">
                                    <input id="bornYearTo" name="bornYearTo" type="range" min="1845" max="2026" value="2026">
                                </div>
                            </div>
                            <div class="range-field">
                                <div class="range-label">
                                    <span>Teose loomisaasta</span>
                                    <output id="compositionYearVal">1845 – <span id="compositionYearToLabel"></span></output>
                                </div>
                                <div class="range-wrap">
                                    <input id="compositionYearFrom" name="compositionYearFrom" type="range" min="1845" max="2026" value="1845">
                                    <input id="compositionYearTo" name="compositionYearTo" type="range" min="1845" max="2026" value="2026">
                                </div>
                            </div>
                            <div class="range-field">
                                <div class="range-label">
                                    <span>Kestus (min)</span>
                                    <output id="durationVal">0 – 480</output>
                                </div>
                                <div class="range-wrap">
                                    <input id="durationFrom" name="durationFrom" type="range" min="0" max="480" value="0">
                                    <input id="durationTo" name="durationTo" type="range" min="0" max="480" value="480">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button class="primary" type="submit">Otsi</button>
                    <button class="secondary" type="button" id="resetBtn">Tühjenda</button>
                </div>
            </form>

            <div class="status" id="status"></div>
            <div class="results" id="results"></div>
        </section>
    </main>

    <script>
        // Accordion
        const moreToggle = document.getElementById('moreToggle');
        const moreBody = document.getElementById('moreBody');
        moreToggle.addEventListener('click', () => {
            const open = moreBody.classList.toggle('open');
            moreToggle.setAttribute('aria-expanded', open);
        });

        // Range slider display helpers
        function updateOutput(fromId, toId, outputId, suffix = '', maxLabel = null) {
            const from = document.getElementById(fromId);
            const to = document.getElementById(toId);
            const out = document.getElementById(outputId);
            function update() {
                const lo = Math.min(Number(from.value), Number(to.value));
                const hi = Math.max(Number(from.value), Number(to.value));
                const hiStr = (maxLabel !== null && hi >= Number(to.max)) ? maxLabel : hi + suffix;
                out.textContent = lo + ' – ' + hiStr;
            }
            from.addEventListener('input', update);
            to.addEventListener('input', update);
            update();
        }
        updateOutput('performersFrom', 'performersTo', 'performersVal', '', '16+');
        updateOutput('bornYearFrom', 'bornYearTo', 'bornYearVal');
        updateOutput('compositionYearFrom', 'compositionYearTo', 'compositionYearVal');
        updateOutput('durationFrom', 'durationTo', 'durationVal', ' min');
    </script>
    <script src="./js/search.js"></script>
</body>
</html>
