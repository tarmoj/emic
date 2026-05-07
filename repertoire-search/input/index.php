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
  input[type=number], textarea { width: 100%; box-sizing: border-box; padding: .4rem; font-size: 1rem; border: 1px solid #aaa; border-radius: 3px; }
  textarea { font-family: monospace; resize: vertical; }
  .buttons { margin-top: 1rem; display: flex; gap: .5rem; }
  button { padding: .45rem 1.2rem; font-size: 1rem; cursor: pointer; border: 1px solid #888; border-radius: 3px; background: #f0f0f0; }
  button[type=submit] { background: #2a6eb5; color: #fff; border-color: #1f55a0; }
  button[type=submit]:hover { background: #1f55a0; }
  .error { color: #c00; margin-top: .75rem; font-size: .95rem; }
  #spinner { display: none; margin-left: .5rem; color: #555; font-size: .9rem; }
</style>
</head>
<body>

<h1>EMIC instrumentatsiooni sisestamine</h1>

<form id="form">
  <label for="teosId">Teose ID</label>
  <input type="number" id="teosId" name="teosId" min="1" max="50000" value="<?= $safeTeosId ?>" required>

  <label for="instrumentation">Instrumenatatsioon</label>
  <textarea id="instrumentation" name="instrumentation" rows="4"><?= $safeInstrumentation ?></textarea>

  <div class="buttons">
    <button type="submit">Teisenda</button>
    <button type="button" id="clearBtn">Tühjenda</button>
    <span id="spinner">Töötlen…</span>
  </div>

  <?php if ($safeError !== ''): ?>
    <p class="error" id="errorMsg"><?= $safeError ?></p>
  <?php else: ?>
    <p class="error" id="errorMsg" style="display:none"></p>
  <?php endif; ?>
</form>

<label for="output">Väljund</label>
<textarea id="output" rows="18" readonly><?= $safeResult ?></textarea>

<script>
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
    document.getElementById('instrumentation').value = '';
    document.getElementById('output').value          = '';
    const errorEl             = document.getElementById('errorMsg');
    errorEl.textContent       = '';
    errorEl.style.display     = 'none';
});
</script>

</body>
</html>
