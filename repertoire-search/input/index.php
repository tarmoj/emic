<?php
declare(strict_types=1);

// --- Bootstrap -----------------------------------------------------------

$apiKey       = trim((string) file_get_contents(__DIR__ . '/api-key.txt'));
$systemPrompt = (string) file_get_contents(__DIR__ . '/system_prompt.txt');

$GEMINI_MODEL = 'gemini-2.5-flash-lite';
$GEMINI_URL   = "https://generativelanguage.googleapis.com/v1beta/models/{$GEMINI_MODEL}:generateContent?key={$apiKey}";

// --- Helpers -------------------------------------------------------------

function is_api_request(): bool
{
    $ct     = strtolower($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($ct, 'application/json')
        || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'));
}

function read_post_input(): array
{
    $raw = (string) file_get_contents('php://input');
    if (trim($raw) !== '') {
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            return $parsed;
        }
    }
    return $_POST;
}

function extract_json_from_text(string $text): array
{
    $text = trim($text);
    $text = (string) preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = (string) preg_replace('/\s*```$/i', '', $text);
    $text = trim($text);

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{.*\}/s', $text, $m) === 1) {
        $decoded = json_decode($m[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    throw new RuntimeException('Ei suutnud JSON-i eraldada Gemini vastusest: ' . substr($text, 0, 200));
}

function call_gemini(string $url, string $systemPrompt, int $teosId, string $instrumentation): array
{
    $userText = "Teose ID: {$teosId}\nInstrumentatsioon: {$instrumentation}";

    $body = json_encode([
        'system_instruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [['text' => $userText]],
            ],
        ],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
        ],
    ], JSON_UNESCAPED_UNICODE);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $body,
            'timeout'       => 60,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new RuntimeException('HTTP päring ebaõnnestus: ' . (error_get_last()['message'] ?? 'tundmatu viga'));
    }

    // Parse HTTP status from response headers
    $httpCode = 200;
    foreach ($http_response_header as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m) === 1) {
            $httpCode = (int) $m[1];
        }
    }

    if ($httpCode !== 200) {
        $errData = json_decode($response, true);
        $errMsg  = is_array($errData) ? ($errData['error']['message'] ?? $response) : $response;
        throw new RuntimeException("Gemini API viga (HTTP {$httpCode}): {$errMsg}");
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Gemini tagastas vigase vastuse.');
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($text === null) {
        throw new RuntimeException('Gemini vastuses puudub tekst.');
    }

    return extract_json_from_text($text);
}

// --- Request handling ----------------------------------------------------

$isPost   = ($_SERVER['REQUEST_METHOD'] === 'POST');
$isApi    = is_api_request();
$input    = $isPost ? read_post_input() : [];

$teosId              = 0;
$instrumentationText = '';
$resultJson          = null;
$resultJsonStr       = '';
$errorMsg            = null;

if ($isPost && !empty($input)) {
    $teosId              = max(0, (int) ($input['teosId'] ?? 0));
    $instrumentationText = trim((string) ($input['instrumentation'] ?? ''));

    if ($teosId < 1 || $teosId > 50000) {
        $errorMsg = 'Teose ID peab olema vahemikus 1–50000.';
    } elseif ($instrumentationText === '') {
        $errorMsg = 'Instrumenatatsioon ei tohi olla tühi.';
    } else {
        try {
            $resultJson    = call_gemini($GEMINI_URL, $systemPrompt, $teosId, $instrumentationText);
            $resultJsonStr = (string) json_encode($resultJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();
        }
    }

    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        if ($errorMsg !== null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $errorMsg], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'ok'     => true,
                'teosId' => $teosId,
                'result' => $resultJson,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }
}

// --- Manual-entry panel data (instrument/ensemble tables) ----------------

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

$instrJson     = json_encode($instruments, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$ensemblesJson = json_encode($ensembles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

// --- HTML page -----------------------------------------------------------

$safeTeosId       = htmlspecialchars((string) ($teosId ?: ''), ENT_QUOTES, 'UTF-8');
$safeInstrumentation = htmlspecialchars($instrumentationText, ENT_QUOTES, 'UTF-8');
$safeResult       = htmlspecialchars($resultJsonStr, ENT_QUOTES, 'UTF-8');
$safeError        = $errorMsg !== null ? htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') : '';

?><!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EMIC instrumentatsiooni sisestamine</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 700px; margin: 2rem auto; padding: 0 1rem; color: #222; }
  h1 { font-size: 1.3rem; margin-bottom: 1.5rem; }
  label { display: block; font-weight: bold; margin-top: 1rem; margin-bottom: .25rem; }
  input[type=number], input[type=text], textarea { width: 100%; box-sizing: border-box; padding: .4rem; font-size: 1rem; border: 1px solid #aaa; border-radius: 3px; }
  textarea { font-family: monospace; resize: vertical; }
  .buttons { margin-top: 1rem; display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
  button { padding: .45rem 1.2rem; font-size: 1rem; cursor: pointer; border: 1px solid #888; border-radius: 3px; background: #f0f0f0; }
  button[type=submit] { background: #2a6eb5; color: #fff; border-color: #1f55a0; }
  button[type=submit]:hover { background: #1f55a0; }
  .error { color: #c00; margin-top: .75rem; font-size: .95rem; }
  #spinner { display: none; margin-left: .5rem; color: #555; font-size: .9rem; }

  /* --- Ported from editor.php (manual-entry panel) --- */
  *, *::before, *::after { box-sizing: border-box; }
  #manual-panel h2 { font-size: 1em; margin: 0 0 10px 0; padding: 6px 10px; background: #dde; border-left: 4px solid #669; }
  #manual-panel .section { background: #fff; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 14px; padding: 12px 14px; }
  #manual-panel .sub-section { background: #f9f9ff; border: 1px solid #dde; border-radius: 3px; padding: 8px 12px; margin-top: 6px; }

  #manual-panel .field-row { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
  #manual-panel .field-row label { display: flex; align-items: center; gap: 4px; white-space: nowrap; font-weight: normal; margin: 0; }
  #manual-panel .btn-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 8px; }

  #manual-panel input[type=text], #manual-panel input[type=number], #manual-panel textarea, #manual-panel select {
      border: 1px solid #bbb; border-radius: 3px; padding: 3px 6px; font-size: 13px; width: auto;
  }
  #manual-panel input[type=text]:focus, #manual-panel input[type=number]:focus, #manual-panel textarea:focus, #manual-panel select:focus {
      outline: none; border-color: #669; box-shadow: 0 0 0 2px #ddf;
  }

  #manual-panel button, .btn-primary { padding: 4px 12px; border: 1px solid #999; border-radius: 3px; background: #eee; cursor: pointer; font-size: 13px; }
  #manual-panel button:hover { background: #ddd; }
  .btn-primary { background: #446; color: #fff; border-color: #335; }
  .btn-primary:hover { background: #557; }
  .btn-secondary { background: #e8e8ff; border-color: #aad; }
  .btn-secondary:hover { background: #d8d8ff; }
  .btn-remove { background: #fee; border-color: #faa; color: #800; padding: 2px 8px; font-size: 12px; }
  .btn-remove:hover { background: #fcc; }

  .success { color: #060; background: #efe; border: 1px solid #afa; border-radius: 3px; padding: 4px 8px; margin: 4px 0; display: inline-block; }
  #save-status, #load-status { font-size: 12px; }

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

  /* --- Instrument+count rows (orchestral "other" / percussion "extra") --- */
  .other-instr-row {
      display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
      padding: 4px 8px; margin-bottom: 6px;
      background: #f8fff8; border: 1px solid #cec; border-radius: 3px;
  }
  .other-instr-count { width: 55px; text-align: center; }

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

  /* --- Modal --- */
  dialog { border: 1px solid #aaa; border-radius: 6px; padding: 20px; min-width: 340px; }
  dialog::backdrop { background: rgba(0,0,0,.4); }
  dialog h3 { margin: 0 0 14px 0; }
  dialog .field-row label { display: flex; align-items: center; gap: 4px; font-weight: normal; }
  dialog input[type=text] { width: auto; }

  /* --- Scoring variants --- */
  .variant-row {
      border: 1px solid #d5c8a8; border-radius: 4px; padding: 8px 10px; margin-bottom: 10px;
      background: #fffdf5;
  }
  .variant-row-top { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
  .variant-label-input { width: 260px; }
  .variant-editor { background: #fefef8; border: 1px solid #e0d8b0; border-radius: 3px; padding: 10px 12px; }
  .variant-editor .field-row label { font-weight: normal; }
</style>
</head>
<body>

<h1>EMIC instrumentatsiooni sisestamine</h1>

<?php if ($dbError): ?>
<div class="error">Andmebaasi ühenduse viga: <?= $dbError ?></div>
<?php endif; ?>

<form id="form">
  <label for="teosId">Teose ID</label>
  <input type="number" id="teosId" name="teosId" min="1" max="50000" value="<?= $safeTeosId ?>" required>

  <label for="pealkiri">Pealkiri</label>
  <input type="text" id="pealkiri" name="pealkiri">

  <label for="instrumentation">Instrumenatatsioon</label>
  <textarea id="instrumentation" name="instrumentation" rows="4"><?= $safeInstrumentation ?></textarea>

  <div class="buttons">
    <button type="submit">Teisenda</button>
    <button type="button" id="btn-toggle-manual">Käsitsi sisestus</button>
    <button type="button" id="clearBtn">Tühjenda</button>
    <button type="button" id="btn-save" class="btn-primary">💾 Salvesta andmebaasi</button>
    <span id="spinner">Töötlen…</span>
    <span id="save-status"></span>
  </div>

  <?php if ($safeError !== ''): ?>
    <p class="error" id="errorMsg"><?= $safeError ?></p>
  <?php else: ?>
    <p class="error" id="errorMsg" style="display:none"></p>
  <?php endif; ?>
</form>

<label for="output">Väljund</label>
<textarea id="output" rows="18" readonly><?= $safeResult ?></textarea>

<!-- ===== KÄSITSI SISESTUS ===== -->
<div id="manual-panel" style="display:none">

  <div class="btn-row">
    <button type="button" id="btn-update-json">↻ Uuenda vormist</button>
    <button type="button" id="btn-parse-json">↑ Parsi vormi</button>
  </div>

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
        <label>Tüüp:
          <select id="electronics-type" style="width:160px">
            <option value="live">live</option>
            <option value="fixed_media">fixed_media</option>
            <option value="other">other</option>
          </select>
        </label>
        <label style="margin-left:10px">Täpsustus: <input type="text" id="electronics-details" style="width:280px"></label>
      </div>
    </div>
  </section>

  <!-- ===== MÄRKUSED ===== -->
  <section class="section" id="s-notes">
    <h2>Märkused</h2>
    <div class="field-row">
      <label>Märkus (est): <input type="text" id="note-est" style="width:320px"></label>
      <label style="margin-left:12px">Märkus (eng): <input type="text" id="note-eng" style="width:320px"></label>
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

  <!-- ===== ANSAMBLID ===== -->
  <section class="section" id="s-ansamblid">
    <h2>Ansamblid (kooslused)</h2>
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
      <button type="button" id="btn-new-instr" class="btn-secondary">Lisa uus pill/hääl tabelisse…</button>
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
        <label>Vaskpillid:</label>
        <label title="Metsasarved">hn <input type="number" id="br-hn" value="0" min="0" class="orch-num"></label>
        <label title="Trompetid">tp <input type="number" id="br-tp" value="0" min="0" class="orch-num"></label>
        <label title="Tromboonid">tbn <input type="number" id="br-tbn" value="0" min="0" class="orch-num"></label>
        <label title="Tuubad">tuba <input type="number" id="br-tuba" value="0" min="0" class="orch-num"></label>
      </div>
      <div class="field-row" style="align-items:flex-start">
        <label style="margin-top:3px">Muud pillid:</label>
        <div style="display:flex; flex-direction:column; gap:6px">
          <div id="orch-other-list"></div>
          <div class="btn-row" style="margin-top:0">
            <button type="button" id="btn-add-orch-other">+ Lisa pill</button>
            <button type="button" id="btn-new-instr-orch-other" class="btn-secondary">Lisa uus pill/hääl tabelisse…</button>
          </div>
        </div>
      </div>
      <div class="field-row" style="align-items:flex-start">
        <label style="margin-top:3px">Löökpillid:</label>
        <div style="display:flex; flex-direction:column; gap:6px">
          <div class="field-row" style="margin-bottom:0">
            <label><input type="checkbox" id="perc-timpani"> Timpanid</label>
            <label style="margin-left:10px">Teised mängijad: <input type="number" id="perc-other" value="0" min="0" style="width:55px"></label>
          </div>
          <div>
            <span style="color:#666; font-size:11px; font-weight:bold">Lisad:</span>
            <div id="perc-extra-list" style="margin-top:4px"></div>
            <div class="btn-row" style="margin-top:0">
              <button type="button" id="btn-add-perc-extra">+ Lisa pill</button>
              <button type="button" id="btn-new-instr-perc-extra" class="btn-secondary">Lisa uus pill/hääl tabelisse…</button>
            </div>
          </div>
        </div>
      </div>
      <div class="field-row">
        <label><input type="checkbox" id="orch-strings"> Keelpillid</label>
      </div>
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

</div>

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
    dblLbl.className = 'part-sub-label'; dblLbl.textContent = 'Dubleerivad pillid:';
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
// INSTRUMENT + COUNT ROW (orchestral_layout.other / percussion.extra)
// ---------------------------------------------------------------------------

function createOtherInstrumentRow(data) {
    data = data || {};
    const row = document.createElement('div');
    row.className = 'other-instr-row';

    const combo = createInstrCombo(data.instrument_id || '', null);

    const cntIn = document.createElement('input');
    cntIn.type      = 'number';
    cntIn.className = 'other-instr-count';
    cntIn.value     = data.count ?? 1;
    cntIn.min       = 1;
    cntIn.title     = 'Arv';
    cntIn.addEventListener('input', updateOutput);
    cntIn.addEventListener('change', updateOutput);

    const rm = document.createElement('button');
    rm.type = 'button'; rm.className = 'btn-remove'; rm.textContent = 'Eemalda';
    rm.addEventListener('click', () => { row.remove(); updateOutput(); });

    [combo, makeFieldLabel('arv'), cntIn, rm].forEach(el => row.appendChild(el));
    return row;
}

function getOtherInstrumentList(container) {
    if (!container) return [];
    return Array.from(container.querySelectorAll('.other-instr-row'))
        .map(row => {
            const combo = row.querySelector('.instr-combo');
            return {
                instrument_id: combo ? combo.dataset.value : '',
                count:         parseInt(row.querySelector('.other-instr-count').value, 10) || 1,
            };
        })
        .filter(item => item.instrument_id);
}

function rebuildOtherInstrumentList(container, items) {
    if (!container) return;
    container.innerHTML = '';
    (items || []).forEach(item => container.appendChild(createOtherInstrumentRow(item)));
}

// ---------------------------------------------------------------------------
// JSON BUILD
// ---------------------------------------------------------------------------

function buildJSON() {
    const obj = {};

    obj.total_player_count = parseInt(document.getElementById('total-player-count').value, 10) || 0;

    if (document.getElementById('has-electronics').checked) {
        obj.electronics = {
            type:    document.getElementById('electronics-type').value.trim() || 'electronics',
            details: document.getElementById('electronics-details').value.trim(),
        };
    } else {
        obj.electronics = null;
    }

    obj.has_vocal = document.getElementById('has-vocal').checked;

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

    if (document.getElementById('has-orch').checked) {
        obj.orchestral_layout = {
            woodwinds: ['fl','ob','cl','bn'].map(id =>
                parseInt(document.getElementById('ww-' + id).value, 10) || 0),
            brass: ['hn','tp','tbn','tuba'].map(id =>
                parseInt(document.getElementById('br-' + id).value, 10) || 0),
            percussion: {
                timpani:      document.getElementById('perc-timpani').checked,
                other_players: parseInt(document.getElementById('perc-other').value, 10) || 0,
                extra:        getOtherInstrumentList(document.getElementById('perc-extra-list')),
            },
            strings: document.getElementById('orch-strings').checked,
            other:   getOtherInstrumentList(document.getElementById('orch-other-list')),
        };
    } else {
        obj.orchestral_layout = null;
    }

    obj.note     = document.getElementById('note-eng').value.trim();
    obj.note_est = document.getElementById('note-est').value.trim();

    const hasChoir = document.getElementById('has-choir').checked;
    if (hasChoir) {
        obj.has_vocal = true;
        const choirType = document.getElementById('choir-type').value;
        obj.ensembles.unshift({
            ensemble_id:  choirType + '_choir',
            player_count: obj.total_player_count,
            standard:     true,
            note:         '',
            note_est:     '',
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

    const variants = [];
    document.querySelectorAll('#variant-list .variant-row').forEach(row => {
        if (row._getJSON) variants.push(row._getJSON());
    });
    if (variants.length > 0) {
        obj.scoring_variants = variants;
    }

    return obj;
}

function updateOutput() {
    const out = document.getElementById('output');
    try {
        out.value = JSON.stringify(buildJSON(), null, 2);
    } catch (e) {
        // ignore
    }
}

// ---------------------------------------------------------------------------
// JSON PARSE → FORM
// ---------------------------------------------------------------------------

function blankInstrumentation() {
    return {
        total_player_count: 0,
        electronics: null,
        has_vocal: false,
        ensembles: [],
        parts: [],
        orchestral_layout: null,
        vocal_details: null,
        note: '',
        note_est: '',
    };
}

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

    const isChoir = !!(json.has_vocal && json.vocal_details && json.vocal_details.is_choir);
    const choirEnsId = isChoir ? ((json.vocal_details.choir_type || 'mixed') + '_choir') : null;
    const eList = document.getElementById('ensemble-list');
    eList.innerHTML = '';
    (json.ensembles || [])
        .filter(e => !choirEnsId || e.ensemble_id !== choirEnsId)
        .forEach(e => eList.appendChild(createEnsembleRow(e)));

    const pList = document.getElementById('part-list');
    pList.innerHTML = '';
    (json.parts || []).forEach(p => pList.appendChild(createPartRow(p)));

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
        rebuildOtherInstrumentList(document.getElementById('perc-extra-list'), perc.extra || []);
        document.getElementById('orch-strings').checked  = ol.strings ?? false;
        rebuildOtherInstrumentList(document.getElementById('orch-other-list'), ol.other || []);
    } else {
        rebuildOtherInstrumentList(document.getElementById('perc-extra-list'), []);
        rebuildOtherInstrumentList(document.getElementById('orch-other-list'), []);
    }

    document.getElementById('note-eng').value = json.note     || '';
    document.getElementById('note-est').value = json.note_est || '';

    document.getElementById('has-choir').checked = isChoir;
    document.getElementById('choir-section').style.display = isChoir ? 'block' : 'none';
    if (isChoir && json.vocal_details) {
        const vd = json.vocal_details;
        document.getElementById('choir-type').value   = vd.choir_type || 'mixed';
        document.getElementById('choir-voices').value = vd.voices ?? 0;
        rebuildTagContainer(document.getElementById('choir-voice-dist-tags'), vd.voice_distribution || []);
        rebuildTagContainer(document.getElementById('choir-soloists-tags'), vd.soloists || []);
        document.getElementById('choir-other').value  = vd.other || '';
    }

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

    const vList = document.getElementById('variant-list');
    vList.innerHTML = '';
    (json.scoring_variants || []).forEach(v => {
        vList.appendChild(createVariantRow(v.label || '', v.instrumentation));
    });
}

// ---------------------------------------------------------------------------
// VARIANT EDITOR (reusable mini-form, mirrors main form sections)
// ---------------------------------------------------------------------------

function createVariantEditor(initialData) {
    const wrap = document.createElement('div');
    wrap.className = 'variant-editor';
    wrap.innerHTML = `
        <div class="field-row">
            <label>Mängijaid kokku: <input type="number" class="ve-total" value="0" min="0" style="width:70px"></label>
            <label style="margin-left:20px"><input type="checkbox" class="ve-has-vocal"> Vokaal</label>
            <label style="margin-left:20px"><input type="checkbox" class="ve-has-electronics"> Elektroonika</label>
        </div>
        <div class="ve-electronics sub-section" style="display:none">
            <div class="field-row">
                <label>Tüüp:
                    <select class="ve-elec-type" style="width:140px">
                        <option value="live">live</option>
                        <option value="fixed_media">fixed_media</option>
                        <option value="other">other</option>
                    </select>
                </label>
                <label style="margin-left:10px">Täpsustus: <input type="text" class="ve-elec-details" style="width:240px"></label>
            </div>
        </div>
        <div class="field-row">
            <label>Märkus (est): <input type="text" class="ve-note-est" style="width:220px"></label>
            <label style="margin-left:12px">Märkus (eng): <input type="text" class="ve-note-eng" style="width:220px"></label>
        </div>
        <div style="font-size:12px;font-weight:bold;color:#669;margin:8px 0 4px">Ansamblid (kooslused)</div>
        <div class="ve-ensemble-list"></div>
        <div class="btn-row" style="margin-bottom:8px"><button type="button" class="ve-btn-add-ensemble">+ Lisa ansambel</button></div>
        <div style="font-size:12px;font-weight:bold;color:#669;margin:8px 0 4px">Partiid</div>
        <div class="ve-part-list"></div>
        <div class="btn-row" style="margin-bottom:8px"><button type="button" class="ve-btn-add-part">+ Lisa partii</button></div>
        <div class="field-row" style="margin-top:4px">
            <label><input type="checkbox" class="ve-has-orch"> Orkestri koosseis</label>
        </div>
        <div class="ve-orch-section" style="display:none">
            <div class="field-row">
                <label>Puupillid:</label>
                <label title="Flöödid">fl <input type="number" class="ve-ww-fl orch-num" value="0" min="0"></label>
                <label title="Oboed">ob <input type="number" class="ve-ww-ob orch-num" value="0" min="0"></label>
                <label title="Klarnetid">cl <input type="number" class="ve-ww-cl orch-num" value="0" min="0"></label>
                <label title="Fagotid">bn <input type="number" class="ve-ww-bn orch-num" value="0" min="0"></label>
            </div>
            <div class="field-row">
                <label>Vaskpillid:</label>
                <label title="Metsasarved">hn <input type="number" class="ve-br-hn orch-num" value="0" min="0"></label>
                <label title="Trompetid">tp <input type="number" class="ve-br-tp orch-num" value="0" min="0"></label>
                <label title="Tromboonid">tbn <input type="number" class="ve-br-tbn orch-num" value="0" min="0"></label>
                <label title="Tuubad">tuba <input type="number" class="ve-br-tuba orch-num" value="0" min="0"></label>
            </div>
            <div class="field-row" style="align-items:flex-start">
                <label style="margin-top:3px">Muud pillid:</label>
                <div style="display:flex; flex-direction:column; gap:6px">
                    <div class="ve-orch-other-list"></div>
                    <div class="btn-row" style="margin-top:0">
                        <button type="button" class="ve-btn-add-orch-other">+ Lisa pill</button>
                        <button type="button" class="ve-btn-new-instr-orch-other btn-secondary">Lisa uus pill/hääl tabelisse…</button>
                    </div>
                </div>
            </div>
            <div class="field-row" style="align-items:flex-start">
                <label style="margin-top:3px">Löökpillid:</label>
                <div style="display:flex; flex-direction:column; gap:6px">
                    <div class="field-row" style="margin-bottom:0">
                        <label><input type="checkbox" class="ve-perc-timpani"> Timpanid</label>
                        <label style="margin-left:10px">Teised: <input type="number" class="ve-perc-other" value="0" min="0" style="width:50px"></label>
                    </div>
                    <div>
                        <span style="color:#666; font-size:11px; font-weight:bold">Lisad:</span>
                        <div class="ve-perc-extra-list" style="margin-top:4px"></div>
                        <div class="btn-row" style="margin-top:0">
                            <button type="button" class="ve-btn-add-perc-extra">+ Lisa pill</button>
                            <button type="button" class="ve-btn-new-instr-perc-extra btn-secondary">Lisa uus pill/hääl tabelisse…</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="field-row">
                <label><input type="checkbox" class="ve-orch-strings"> Keelpillid</label>
            </div>
        </div>
        <div class="field-row" style="margin-top:4px">
            <label><input type="checkbox" class="ve-has-choir"> Koor</label>
        </div>
        <div class="ve-choir-section" style="display:none">
            <div class="field-row">
                <label>Tüüp:
                    <select class="ve-choir-type">
                        <option value="mixed">Segakoor</option>
                        <option value="male">Meeskoor</option>
                        <option value="female">Naiskoor</option>
                        <option value="boys">Poistekoor</option>
                        <option value="children">Lastekoor</option>
                        <option value="youth">Noortekoor</option>
                    </select>
                </label>
                <label style="margin-left:12px">Hääli: <input type="number" class="ve-choir-voices" value="0" min="0" style="width:55px"></label>
            </div>
            <div class="field-row" style="align-items:center">
                <label>Jaotus:</label>
                <div class="tag-container ve-choir-vd-tags"></div>
            </div>
            <div class="field-row" style="align-items:center">
                <label>Solistid:</label>
                <div class="tag-container ve-choir-sol-tags"></div>
            </div>
            <div class="field-row">
                <label>Lisainfo: <input type="text" class="ve-choir-other" style="width:440px"></label>
            </div>
        </div>`;

    const q = sel => wrap.querySelector(sel);

    initTagContainer(q('.ve-choir-vd-tags'),   true,  true);
    initTagContainer(q('.ve-choir-sol-tags'),  true,  false);

    q('.ve-btn-add-orch-other').addEventListener('click', () => {
        q('.ve-orch-other-list').appendChild(createOtherInstrumentRow());
        updateOutput();
    });
    q('.ve-btn-add-perc-extra').addEventListener('click', () => {
        q('.ve-perc-extra-list').appendChild(createOtherInstrumentRow());
        updateOutput();
    });
    q('.ve-btn-new-instr-orch-other').addEventListener('click', () =>
        document.getElementById('modal-new-instr').showModal());
    q('.ve-btn-new-instr-perc-extra').addEventListener('click', () =>
        document.getElementById('modal-new-instr').showModal());

    q('.ve-has-electronics').addEventListener('change', () => {
        q('.ve-electronics').style.display = q('.ve-has-electronics').checked ? 'block' : 'none';
        updateOutput();
    });
    q('.ve-has-orch').addEventListener('change', () => {
        q('.ve-orch-section').style.display = q('.ve-has-orch').checked ? 'block' : 'none';
        updateOutput();
    });
    q('.ve-has-choir').addEventListener('change', () => {
        const show = q('.ve-has-choir').checked;
        q('.ve-choir-section').style.display = show ? 'block' : 'none';
        if (show) q('.ve-has-vocal').checked = true;
        updateOutput();
    });
    q('.ve-has-vocal').addEventListener('change', updateOutput);
    q('.ve-btn-add-ensemble').addEventListener('click', () => {
        q('.ve-ensemble-list').appendChild(createEnsembleRow());
        updateOutput();
    });
    q('.ve-btn-add-part').addEventListener('click', () => {
        q('.ve-part-list').appendChild(createPartRow());
        updateOutput();
    });
    wrap.querySelectorAll('input[type=text], input[type=number], select').forEach(el => {
        el.addEventListener('input', updateOutput);
        el.addEventListener('change', updateOutput);
    });

    wrap._getJSON = function () {
        const obj = {};
        obj.total_player_count = parseInt(q('.ve-total').value, 10) || 0;
        obj.electronics = q('.ve-has-electronics').checked ? {
            type:    q('.ve-elec-type').value.trim() || 'electronics',
            details: q('.ve-elec-details').value.trim(),
        } : null;
        obj.has_vocal = q('.ve-has-vocal').checked;
        obj.ensembles = [];
        wrap.querySelectorAll('.ve-ensemble-list .ensemble-row').forEach(row => {
            const sel    = row.querySelector('.ensemble-select');
            const custIn = row.querySelector('.ensemble-custom-id');
            const ensId  = sel.value === '__custom__' ? custIn.value.trim() : sel.value;
            if (!ensId) return;
            obj.ensembles.push({
                ensemble_id:  ensId,
                player_count: obj.total_player_count,
                standard:     row.querySelector('.ensemble-standard').checked,
                note:         row.querySelector('.ensemble-note').value.trim(),
                note_est:     row.querySelector('.ensemble-note-est').value.trim(),
            });
        });
        obj.parts = [];
        wrap.querySelectorAll('.ve-part-list .part-row').forEach(row => {
            const combo = row.querySelector('.instr-combo');
            obj.parts.push({
                instrument_id:           combo ? combo.dataset.value : '',
                alternative_instruments: getTagValues(row.querySelector('.alternatives-tags')),
                doubles:                 getTagValues(row.querySelector('.doubles-tags')),
                count:                   parseInt(row.querySelector('.part-count').value, 10) || 1,
                role:                    row.querySelector('.part-role').value,
                note:                    (row.querySelector('.part-note').value || '').trim(),
            });
        });
        if (q('.ve-has-orch').checked) {
            obj.orchestral_layout = {
                woodwinds:  ['fl','ob','cl','bn'].map(id => parseInt(q('.ve-ww-'+id).value,10)||0),
                brass:      ['hn','tp','tbn','tuba'].map(id => parseInt(q('.ve-br-'+id).value,10)||0),
                percussion: {
                    timpani:       q('.ve-perc-timpani').checked,
                    other_players: parseInt(q('.ve-perc-other').value,10)||0,
                    extra:         getOtherInstrumentList(q('.ve-perc-extra-list')),
                },
                strings: q('.ve-orch-strings').checked,
                other:   getOtherInstrumentList(q('.ve-orch-other-list')),
            };
        } else {
            obj.orchestral_layout = null;
        }
        obj.note     = q('.ve-note-eng').value.trim();
        obj.note_est = q('.ve-note-est').value.trim();
        const hasChoir = q('.ve-has-choir').checked;
        if (hasChoir) {
            obj.has_vocal = true;
            const choirType = q('.ve-choir-type').value;
            obj.ensembles.unshift({
                ensemble_id:  choirType + '_choir',
                player_count: obj.total_player_count,
                standard:     true,
                note:         '',
                note_est:     '',
            });
            obj.vocal_details = {
                is_choir:           true,
                choir_type:         choirType,
                voices:             parseInt(q('.ve-choir-voices').value,10)||0,
                voice_distribution: getTagValues(q('.ve-choir-vd-tags')),
                soloists:           getTagValues(q('.ve-choir-sol-tags')),
                other:              q('.ve-choir-other').value.trim(),
            };
        } else if (obj.has_vocal) {
            obj.vocal_details = { is_choir: false, choir_type: 'none', voices: 0,
                                   voice_distribution: [], soloists: [], other: '' };
        } else {
            obj.vocal_details = null;
        }
        return obj;
    };

    wrap._setJSON = function (json) {
        if (!json || typeof json !== 'object') return;
        q('.ve-total').value       = json.total_player_count ?? 0;
        q('.ve-has-vocal').checked = !!json.has_vocal;
        const hasElec = !!json.electronics;
        q('.ve-has-electronics').checked   = hasElec;
        q('.ve-electronics').style.display = hasElec ? 'block' : 'none';
        if (hasElec) {
            q('.ve-elec-type').value    = json.electronics.type    || 'electronics';
            q('.ve-elec-details').value = json.electronics.details || '';
        }
        const isChoir    = !!(json.has_vocal && json.vocal_details && json.vocal_details.is_choir);
        const choirEnsId = isChoir ? ((json.vocal_details.choir_type || 'mixed') + '_choir') : null;
        const eList = q('.ve-ensemble-list');
        eList.innerHTML = '';
        (json.ensembles || [])
            .filter(e => !choirEnsId || e.ensemble_id !== choirEnsId)
            .forEach(e => eList.appendChild(createEnsembleRow(e)));
        const pList = q('.ve-part-list');
        pList.innerHTML = '';
        (json.parts || []).forEach(p => pList.appendChild(createPartRow(p)));
        const hasOrch = !!(json.orchestral_layout);
        q('.ve-has-orch').checked           = hasOrch;
        q('.ve-orch-section').style.display = hasOrch ? 'block' : 'none';
        if (hasOrch) {
            const ol = json.orchestral_layout;
            const ww = ol.woodwinds || [0,0,0,0];
            ['fl','ob','cl','bn'].forEach((id,i) => q('.ve-ww-'+id).value = ww[i]??0);
            const br = ol.brass || [0,0,0,0];
            ['hn','tp','tbn','tuba'].forEach((id,i) => q('.ve-br-'+id).value = br[i]??0);
            const perc = ol.percussion || {};
            q('.ve-perc-timpani').checked = perc.timpani ?? false;
            q('.ve-perc-other').value     = perc.other_players ?? 0;
            rebuildOtherInstrumentList(q('.ve-perc-extra-list'), perc.extra || []);
            q('.ve-orch-strings').checked  = ol.strings ?? false;
            rebuildOtherInstrumentList(q('.ve-orch-other-list'), ol.other || []);
        } else {
            rebuildOtherInstrumentList(q('.ve-perc-extra-list'), []);
            rebuildOtherInstrumentList(q('.ve-orch-other-list'), []);
        }
        q('.ve-note-eng').value = json.note     || '';
        q('.ve-note-est').value = json.note_est || '';
        q('.ve-has-choir').checked           = isChoir;
        q('.ve-choir-section').style.display = isChoir ? 'block' : 'none';
        if (isChoir && json.vocal_details) {
            const vd = json.vocal_details;
            q('.ve-choir-type').value   = vd.choir_type || 'mixed';
            q('.ve-choir-voices').value = vd.voices ?? 0;
            rebuildTagContainer(q('.ve-choir-vd-tags'),  vd.voice_distribution || []);
            rebuildTagContainer(q('.ve-choir-sol-tags'), vd.soloists || []);
            q('.ve-choir-other').value  = vd.other || '';
        }
    };

    if (initialData) wrap._setJSON(initialData);
    return wrap;
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

    const toggleBtn = document.createElement('button');
    toggleBtn.type        = 'button';
    toggleBtn.className   = 'btn-secondary';
    toggleBtn.textContent = 'Näita/muuda';

    const copyBtn = document.createElement('button');
    copyBtn.type        = 'button';
    copyBtn.className   = 'btn-secondary';
    copyBtn.textContent = '← Kopeeri praegune';
    copyBtn.title       = 'Kopeerib praeguse peavormi instrumentatsiooni siia varianti';

    const rm = document.createElement('button');
    rm.type        = 'button';
    rm.className   = 'btn-remove';
    rm.textContent = 'Kustuta';
    rm.addEventListener('click', () => { row.remove(); updateOutput(); });

    top.appendChild(labelIn);
    top.appendChild(copyBtn);
    top.appendChild(toggleBtn);
    top.appendChild(rm);

    const editorWrap = document.createElement('div');
    editorWrap.style.cssText = 'display:none;margin-top:8px;padding-top:8px;border-top:1px solid #e0d8b0';
    const editor = createVariantEditor(instrObj || null);
    editorWrap.appendChild(editor);

    toggleBtn.addEventListener('click', () => {
        const show = editorWrap.style.display === 'none';
        editorWrap.style.display = show ? 'block' : 'none';
        toggleBtn.textContent = show ? 'Peida' : 'Näita/muuda';
    });

    copyBtn.addEventListener('click', () => {
        const current = buildJSON();
        delete current.scoring_variants;
        editor._setJSON(current);
        editorWrap.style.display = 'block';
        toggleBtn.textContent = 'Peida';
        updateOutput();
    });

    row.appendChild(top);
    row.appendChild(editorWrap);

    row._getJSON = () => ({
        label:           labelIn.value.trim(),
        instrumentation: editor._getJSON(),
    });

    return row;
}

// ---------------------------------------------------------------------------
// UI TOGGLES
// ---------------------------------------------------------------------------

function toggleChoir() {
    const show = document.getElementById('has-choir').checked;
    document.getElementById('choir-section').style.display = show ? 'block' : 'none';
    if (show) {
        document.getElementById('has-vocal').checked = true;
        document.getElementById('s-vocal').style.display = 'none';
    } else {
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
// AJAX (manual-entry panel: instrument/ensemble tables + save)
// ---------------------------------------------------------------------------

async function apiPost(action, body) {
    const resp = await fetch(`${API_URL}?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return resp.json();
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

function refreshAllEnsembleSelects() {
    document.querySelectorAll('.ensemble-row').forEach(row => {
        const sel     = row.querySelector('.ensemble-select');
        const current = sel.value;
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

function setStatus(elId, type, msg) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = msg;
    el.className = type === 'error' ? 'error' : (type === 'ok' ? 'success' : '');
    el.style.display = msg ? 'inline' : 'none';
}

// ---------------------------------------------------------------------------
// TEISENDA (AI) — existing behavior, now also refreshes the manual panel
// ---------------------------------------------------------------------------

const manualPanel     = document.getElementById('manual-panel');
const toggleManualBtn = document.getElementById('btn-toggle-manual');

document.getElementById('form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const teosId         = document.getElementById('teosId').value;
    const instrumentation = document.getElementById('instrumentation').value.trim();
    const errorEl        = document.getElementById('errorMsg');
    const spinner        = document.getElementById('spinner');
    const output         = document.getElementById('output');

    errorEl.style.display = 'none';
    errorEl.textContent   = '';
    output.value          = '';
    spinner.style.display = 'inline';

    try {
        const res = await fetch('', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ teosId: parseInt(teosId, 10), instrumentation }),
        });

        const data = await res.json();

        if (data.ok) {
            output.value = JSON.stringify(data.result, null, 2);
            if (manualPanel.style.display !== 'none') {
                parseFromJSON(data.result && typeof data.result === 'object' ? data.result : blankInstrumentation());
            }
        } else {
            errorEl.textContent   = data.error ?? 'Tundmatu viga.';
            errorEl.style.display = 'block';
        }
    } catch (err) {
        errorEl.textContent   = 'Võrguviga: ' + err.message;
        errorEl.style.display = 'block';
    } finally {
        spinner.style.display = 'none';
    }
});

document.getElementById('clearBtn').addEventListener('click', function() {
    document.getElementById('teosId').value          = '';
    document.getElementById('pealkiri').value         = '';
    document.getElementById('instrumentation').value = '';
    document.getElementById('output').value          = '';
    const errorEl             = document.getElementById('errorMsg');
    errorEl.textContent       = '';
    errorEl.style.display     = 'none';
    setStatus('save-status', '', '');
    if (manualPanel.style.display !== 'none') {
        parseFromJSON(blankInstrumentation());
    }
});

// ---------------------------------------------------------------------------
// KÄSITSI SISESTUS — toggle + AI↔manual sync
// ---------------------------------------------------------------------------

toggleManualBtn.addEventListener('click', () => {
    const opening = manualPanel.style.display === 'none';
    if (opening) {
        let json = null;
        try {
            const parsed = JSON.parse(document.getElementById('output').value);
            if (parsed && typeof parsed === 'object') json = parsed;
        } catch (e) { /* ignore invalid/empty JSON */ }
        parseFromJSON(json || blankInstrumentation());
        manualPanel.style.display = 'block';
        toggleManualBtn.textContent = 'Peida käsitsi sisestus';
    } else {
        updateOutput();
        manualPanel.style.display = 'none';
        toggleManualBtn.textContent = 'Käsitsi sisestus';
    }
});

// ---------------------------------------------------------------------------
// SALVESTA ANDMEBAASI
// ---------------------------------------------------------------------------

document.getElementById('btn-save').addEventListener('click', async () => {
    const id = parseInt(document.getElementById('teosId').value, 10);
    if (!id) { setStatus('save-status', 'error', 'Sisesta kehtiv Teose ID'); return; }

    if (manualPanel.style.display !== 'none') updateOutput();

    let instrObj;
    try {
        instrObj = JSON.parse(document.getElementById('output').value);
    } catch (e) {
        setStatus('save-status', 'error', 'JSON on vigane – Teisenda või uuenda käsitsi vormist');
        return;
    }

    setStatus('save-status', '', 'Salvestan…');
    try {
        const data = await apiPost('save', {
            id,
            pealkiri:         document.getElementById('pealkiri').value,
            koosseis_tekst:   document.getElementById('instrumentation').value,
            intrumentatsioon: instrObj,
        });
        if (data.error) { setStatus('save-status', 'error', data.error); return; }
        setStatus('save-status', 'ok', 'Salvestatud ✓');
    } catch (e) {
        setStatus('save-status', 'error', 'Viga: ' + e.message);
    }
});

// ---------------------------------------------------------------------------
// INIT (manual-entry panel widgets)
// ---------------------------------------------------------------------------

function init() {
    initTagContainer(document.getElementById('voice-dist-tags'), true,  true);
    initTagContainer(document.getElementById('soloists-tags'),   true,  false);
    initTagContainer(document.getElementById('choir-voice-dist-tags'), true, true);
    initTagContainer(document.getElementById('choir-soloists-tags'),   true, false);

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

    document.getElementById('btn-add-orch-other').addEventListener('click', () => {
        document.getElementById('orch-other-list').appendChild(createOtherInstrumentRow());
        updateOutput();
    });
    document.getElementById('btn-add-perc-extra').addEventListener('click', () => {
        document.getElementById('perc-extra-list').appendChild(createOtherInstrumentRow());
        updateOutput();
    });
    document.getElementById('btn-new-instr-orch-other').addEventListener('click', () =>
        document.getElementById('modal-new-instr').showModal());
    document.getElementById('btn-new-instr-perc-extra').addEventListener('click', () =>
        document.getElementById('modal-new-instr').showModal());

    document.getElementById('btn-new-instr-save').addEventListener('click', addNewInstrument);
    document.getElementById('btn-new-ens-save').addEventListener('click', addNewEnsemble);

    document.getElementById('has-electronics').addEventListener('change', toggleElectronics);
    document.getElementById('has-vocal').addEventListener('change', toggleVocalSection);
    document.getElementById('has-orch').addEventListener('change', toggleOrch);
    document.getElementById('has-choir').addEventListener('change', toggleChoir);

    ['total-player-count','electronics-type','electronics-details',
     'perc-timpani','perc-other','orch-strings',
     'vocal-is-choir','vocal-choir-type','vocal-voices','vocal-other',
     'ww-fl','ww-ob','ww-cl','ww-bn',
     'br-hn','br-tp','br-tbn','br-tuba',
     'choir-type','choir-voices','choir-other',
     'note-est','note-eng',
    ].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateOutput);
        if (el) el.addEventListener('change', updateOutput);
    });

    document.getElementById('btn-update-json').addEventListener('click', updateOutput);
    document.getElementById('btn-parse-json').addEventListener('click', () => {
        try {
            const json = JSON.parse(document.getElementById('output').value);
            parseFromJSON(json);
        } catch (e) {
            alert('JSON on vigane:\n' + e.message);
        }
    });
}

document.addEventListener('DOMContentLoaded', init);
</script>

</body>
</html>
