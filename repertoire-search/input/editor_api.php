<?php
declare(strict_types=1);

require_once __DIR__ . '/../search/api/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'data':
            handle_data();
            break;
        case 'load':
            handle_load();
            break;
        case 'add_instrument':
            handle_add_instrument();
            break;
        case 'add_ensemble':
            handle_add_ensemble();
            break;
        case 'save':
            handle_save();
            break;
        default:
            json_response(['error' => 'Tundmatu toiming'], 400);
    }
} catch (Throwable $e) {
    json_response(['error' => debug_error($e, 'Serveri viga')], 500);
}

// ---------------------------------------------------------------------------

function handle_data(): void
{
    $pdo = emic_db();

    $instruments = $pdo
        ->query('SELECT lyhend, nimi, nimi_eng FROM instrumendid ORDER BY nimi_eng')
        ->fetchAll();

    $ensembles = $pdo
        ->query('SELECT id, nimi, nimi_eng FROM ansamblid ORDER BY nimi_eng')
        ->fetchAll();

    json_response(['instruments' => $instruments, 'ensembles' => $ensembles]);
}

// ---------------------------------------------------------------------------

function handle_load(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id <= 0) {
        json_response(['error' => 'Vigane ID'], 400);
    }

    $pdo  = emic_db();
    $stmt = $pdo->prepare(
        'SELECT teosed_id, pealkiri, koosseis_tekst, intrumentatsioon
           FROM teosed_koosseisud
          WHERE teosed_id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row === false) {
        json_response(['error' => 'Teost ei leitud'], 404);
    }

    $instrData = null;
    $raw = $row['intrumentatsioon'] ?? '';
    if ($raw !== '' && $raw !== 'NULL') {
        $instrData = json_decode($raw, true);
    }

    $tekst = $row['koosseis_tekst'] ?? '';
    if ($tekst === 'NULL') {
        $tekst = '';
    }

    json_response([
        'id'               => (int) $row['teosed_id'],
        'pealkiri'         => $row['pealkiri'],
        'koosseis_tekst'   => $tekst,
        'intrumentatsioon' => $instrData,
    ]);
}

// ---------------------------------------------------------------------------

function handle_add_instrument(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Meetod ei ole lubatud'], 405);
    }

    $data    = read_json_input();
    $lyhend  = trim((string) ($data['lyhend']   ?? ''));
    $nimi    = trim((string) ($data['nimi']      ?? ''));
    $nimiEng = trim((string) ($data['nimi_eng']  ?? ''));

    if ($lyhend === '' || $nimi === '' || $nimiEng === '') {
        json_response(['error' => 'Lühend, nimi ja ingliskeelne nimi on kohustuslikud'], 400);
    }

    if (strlen($lyhend) > 120 || strlen($nimi) > 255 || strlen($nimiEng) > 255) {
        json_response(['error' => 'Liiga pikk väärtus'], 400);
    }

    $pdo   = emic_db();
    $check = $pdo->prepare('SELECT lyhend FROM instrumendid WHERE lyhend = ?');
    $check->execute([$lyhend]);
    if ($check->fetch()) {
        json_response(['error' => 'Lühend on juba kasutusel: ' . $lyhend], 409);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO instrumendid (lyhend, nimi, nimi_eng, teised_nimed) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$lyhend, $nimi, $nimiEng, '[]']);

    json_response(['lyhend' => $lyhend, 'nimi' => $nimi, 'nimi_eng' => $nimiEng]);
}

// ---------------------------------------------------------------------------

function handle_add_ensemble(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Meetod ei ole lubatud'], 405);
    }

    $data    = read_json_input();
    $id      = trim((string) ($data['id']       ?? ''));
    $nimi    = trim((string) ($data['nimi']      ?? ''));
    $nimiEng = trim((string) ($data['nimi_eng']  ?? ''));

    if ($id === '' || $nimi === '' || $nimiEng === '') {
        json_response(['error' => 'ID, nimi ja ingliskeelne nimi on kohustuslikud'], 400);
    }

    if (!preg_match('/^[a-z][a-z0-9_]{0,119}$/', $id)) {
        json_response(['error' => 'ID peab koosnema väiketähtedest, numbritest ja allkriipsudest ning algama tähega'], 400);
    }

    if (strlen($nimi) > 255 || strlen($nimiEng) > 255) {
        json_response(['error' => 'Liiga pikk väärtus'], 400);
    }

    $pdo   = emic_db();
    $check = $pdo->prepare('SELECT id FROM ansamblid WHERE id = ?');
    $check->execute([$id]);
    if ($check->fetch()) {
        json_response(['error' => 'See ID on juba kasutusel: ' . $id], 409);
    }

    $stmt = $pdo->prepare('INSERT INTO ansamblid (id, nimi, nimi_eng) VALUES (?, ?, ?)');
    $stmt->execute([$id, $nimi, $nimiEng]);

    json_response(['id' => $id, 'nimi' => $nimi, 'nimi_eng' => $nimiEng]);
}

// ---------------------------------------------------------------------------

function handle_save(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Meetod ei ole lubatud'], 405);
    }

    $data = read_json_input();

    $id = isset($data['id']) ? filter_var($data['id'], FILTER_VALIDATE_INT) : false;
    if ($id === false || $id <= 0) {
        json_response(['error' => 'Vigane ID'], 400);
    }

    $pealkiri      = trim((string) ($data['pealkiri']       ?? ''));
    $koosseisTekst = trim((string) ($data['koosseis_tekst'] ?? ''));
    $instrObj      = $data['intrumentatsioon'] ?? null;

    $instrJson = json_encode($instrObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($instrJson === false) {
        json_response(['error' => 'Vigane instrumentatsiooni JSON'], 400);
    }

    $pdo  = emic_db();
    $stmt = $pdo->prepare(
        'INSERT INTO teosed_koosseisud (teosed_id, pealkiri, koosseis_tekst, intrumentatsioon)
              VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
              pealkiri         = VALUES(pealkiri),
              koosseis_tekst   = VALUES(koosseis_tekst),
              intrumentatsioon = VALUES(intrumentatsioon)'
    );
    $stmt->execute([$id, $pealkiri, $koosseisTekst, $instrJson]);

    json_response(['ok' => true, 'id' => $id]);
}
