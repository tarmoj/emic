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
        .grid { display: grid; grid-template-columns: repeat(12, minmax(0,1fr)); gap: 12px; }
        .field { grid-column: span 12; }
        .field.half { grid-column: span 6; }
        .field.third { grid-column: span 4; }
        label { display: block; font-size: .92rem; margin-bottom: 6px; font-weight: 700; }
        input, select { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 10px; font: inherit; background: #fff; }
        .actions { display: flex; gap: 10px; margin-top: 12px; }
        button { border: 0; border-radius: 999px; padding: 10px 18px; font: inherit; font-weight: 700; cursor: pointer; }
        button.primary { background: var(--accent); color: #fff; }
        button.secondary { background: #ece8da; color: var(--text); }
        .tags { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
        .tag { border: 1px solid #c8d6c6; background: #f0f7ef; color: #1f4f30; border-radius: 999px; padding: 5px 10px; font-size: .86rem; }
        .tag button { padding: 0; margin-left: 8px; background: transparent; color: inherit; }
        .autocomplete { position: relative; }
        .menu { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 20; background: #fff; border: 1px solid var(--line); border-radius: 8px; max-height: 220px; overflow: auto; display: none; }
        .menu.show { display: block; }
        .menu button { display: block; width: 100%; text-align: left; border-radius: 0; padding: 10px; background: #fff; border-bottom: 1px solid #f0eee6; }
        .menu button:hover { background: #f5f9f5; }
        .results { margin-top: 16px; }
        .item { border-top: 1px solid var(--line); padding: 12px 0; }
        .item a { color: var(--accent); font-weight: 700; text-decoration: none; }
        .meta { color: #5e635e; font-size: .92rem; margin-top: 4px; }
        .status { margin-top: 12px; color: #5d4f2f; }
        @media (max-width: 900px) {
            .field.half, .field.third { grid-column: span 12; }
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
                <div class="grid">
                    <div class="field third">
                        <label for="genreId">Žanr</label>
                        <select id="genreId" name="genreId"><option value="">õik žanrid</option></select>
                    </div>
                    <div class="field third">
                        <label for="composerId">Helilooja</label>
                        <select id="composerId" name="composerId"><option value="">Koik heliloojad</option></select>
                    </div>
                    <div class="field third">
                        <label for="title">Pealkiri</label>
                        <input id="title" name="title" type="text" placeholder="Nt Sona sonale">
                    </div>

                    <div class="field half">
                        <label for="keyword">Otsingusõna</label>
                        <input id="keyword" name="keyword" type="text" placeholder="Vaba teksti otsing">
                    </div>
                    <div class="field half autocomplete">
                        <label for="instrumentInput">Koosseis</label>
                        <input id="instrumentInput" type="text" placeholder="Nt vn, fl, hp ...">
                        <div id="instrumentMenu" class="menu" role="listbox"></div>
                        <div id="instrumentTags" class="tags"></div>
                    </div>

                    <div class="field third">
                        <label for="bornYearFrom">Helilooja sünniaasta alates</label>
                        <input id="bornYearFrom" name="bornYearFrom" type="number" min="1845" max="2100" value="1845">
                    </div>
                    <div class="field third">
                        <label for="bornYearTo">Helilooja sünniaasta kuni</label>
                        <input id="bornYearTo" name="bornYearTo" type="number" min="1845" max="2100">
                    </div>
                    <div class="field third">
                        <label for="compositionYearFrom">Loomisaasta alates</label>
                        <input id="compositionYearFrom" name="compositionYearFrom" type="number" min="1845" max="2100" value="1845">
                    </div>

                    <div class="field third">
                        <label for="compositionYearTo">Loomisaasta kuni</label>
                        <input id="compositionYearTo" name="compositionYearTo" type="number" min="1845" max="2100">
                    </div>
                    <div class="field third">
                        <label for="durationFrom">Kestus alates (min)</label>
                        <input id="durationFrom" name="durationFrom" type="number" min="0" max="480" value="0">
                    </div>
                    <div class="field third">
                        <label for="durationTo">Kestus kuni (min)</label>
                        <input id="durationTo" name="durationTo" type="number" min="0" max="480" value="480">
                    </div>

                    <div class="field third">
                        <label for="performersFrom">Esitajaid alates</label>
                        <input id="performersFrom" name="performersFrom" type="number" min="0" max="100" value="0">
                    </div>
                    <div class="field third">
                        <label for="performersTo">Esitajaid kuni</label>
                        <input id="performersTo" name="performersTo" type="number" min="0" max="100" value="100">
                    </div>
                    <div class="field third">
                        <label for="soloistsFrom">Soliste alates</label>
                        <input id="soloistsFrom" name="soloistsFrom" type="number" min="0" max="20" value="0">
                    </div>

                    <div class="field third">
                        <label for="soloistsTo">Soliste kuni</label>
                        <input id="soloistsTo" name="soloistsTo" type="number" min="0" max="20" value="20">
                    </div>
                    <div class="field third" style="display:flex;align-items:flex-end;">
                        <label><input id="onlySelectedInstruments" name="onlySelectedInstruments" type="checkbox"> Ainult need instrumendid/haaled</label>
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

    <script src="./js/search.js"></script>
</body>
</html>
