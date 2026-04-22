<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function parse_year(?string $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (preg_match('/(18|19|20)\d{2}/', $value, $m) === 1) {
        return (int) $m[0];
    }

    return null;
}

function parse_duration_minutes(?string $value): ?int
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $clean = str_replace(["\\'", "'", 'min', 'm', ','], [' ', ' ', ' ', ' ', '.'], strtolower($value));

    if (preg_match('/\d+(?:\.\d+)?/', $clean, $m) !== 1) {
        return null;
    }

    return (int) round((float) $m[0]);
}

function extract_instrument_ids(array $instrumentation): array
{
    $ids = [];
    $parts = $instrumentation['parts'] ?? [];

    if (!is_array($parts)) {
        return [];
    }

    foreach ($parts as $part) {
        if (!is_array($part)) {
            continue;
        }

        $id = trim((string) ($part['instrument_id'] ?? ''));
        if ($id !== '') {
            $ids[$id] = true;
        }
    }

    return array_keys($ids);
}

function extract_soloists(array $instrumentation): int
{
    $parts = $instrumentation['parts'] ?? [];
    if (!is_array($parts)) {
        return 0;
    }

    $soloCount = 0;
    foreach ($parts as $part) {
        if (!is_array($part)) {
            continue;
        }

        $role = strtolower((string) ($part['role'] ?? ''));
        if ($role !== 'soloist') {
            continue;
        }

        $count = (int) ($part['count'] ?? 1);
        $soloCount += max(1, $count);
    }

    return $soloCount;
}

function extract_player_count(array $instrumentation): int
{
    $total = (int) ($instrumentation['total_player_count'] ?? 0);
    if ($total > 0) {
        return $total;
    }

    $sum = 0;
    $parts = $instrumentation['parts'] ?? [];
    if (!is_array($parts)) {
        return 0;
    }

    foreach ($parts as $part) {
        if (!is_array($part)) {
            continue;
        }

        $sum += max(1, (int) ($part['count'] ?? 1));
    }

    return $sum;
}

$input = read_json_input();
if (!$input) {
    $input = $_POST;
}

$page = max(1, (int) ($input['page'] ?? 1));
$perPage = min(100, max(1, (int) ($input['perPage'] ?? 50)));

$filters = [
    'genreId' => (int) ($input['genreId'] ?? 0),
    'composerId' => (int) ($input['composerId'] ?? 0),
    'title' => trim((string) ($input['title'] ?? '')),
    'keyword' => trim((string) ($input['keyword'] ?? '')),
    'bornYearFrom' => (int) ($input['bornYearFrom'] ?? 0),
    'bornYearTo' => (int) ($input['bornYearTo'] ?? 0),
    'compositionYearFrom' => (int) ($input['compositionYearFrom'] ?? 0),
    'compositionYearTo' => (int) ($input['compositionYearTo'] ?? 0),
    'durationFrom' => (int) ($input['durationFrom'] ?? 0),
    'durationTo' => (int) ($input['durationTo'] ?? 480),
    'performersFrom' => (int) ($input['performersFrom'] ?? 0),
    'performersTo' => (int) ($input['performersTo'] ?? 100),
    'soloistsFrom' => (int) ($input['soloistsFrom'] ?? 0),
    'soloistsTo' => (int) ($input['soloistsTo'] ?? 20),
    'onlySelectedInstruments' => (bool) ($input['onlySelectedInstruments'] ?? false),
    'selectedInstruments' => is_array($input['selectedInstruments'] ?? null) ? $input['selectedInstruments'] : [],
];

$activeInput = is_array($input['activeFilters'] ?? null) ? $input['activeFilters'] : [];
$activeFilters = [
    'bornYear' => (bool) ($activeInput['bornYear'] ?? false),
    'compositionYear' => (bool) ($activeInput['compositionYear'] ?? false),
    'duration' => (bool) ($activeInput['duration'] ?? false),
    'performers' => (bool) ($activeInput['performers'] ?? false),
    'soloists' => (bool) ($activeInput['soloists'] ?? false),
];

$selectedInstruments = [];
foreach ($filters['selectedInstruments'] as $abbr) {
    $abbr = trim((string) $abbr);
    if ($abbr !== '') {
        $selectedInstruments[$abbr] = true;
    }
}
$selectedInstruments = array_keys($selectedInstruments);

try {
    $pdo = emic_db();

    $sql = "
        SELECT DISTINCT
            t.id AS teos_id,
            h.nimi AS helilooja,
            h.sunnikuupaev AS helilooja_sunnikuupaev,
            t.aasta AS aasta,
            t.pikkus AS pikkus,
            COALESCE(NULLIF(tt.pealkiri, ''), NULLIF(tk.pealkiri, ''), '(pealkiri puudub)') AS pealkiri,
            COALESCE(NULLIF(tk.koosseis_tekst, ''), '-') AS koosseis_tekst,
            tk.intrumentatsioon AS intrumentatsioon
        FROM teosed t
        JOIN heliloojad_teosed ht ON ht.teosed_id = t.id
        JOIN heliloojad h ON h.id = ht.heliloojad_id
        LEFT JOIN teosed_tekstid tt ON tt.teosed_id = t.id AND tt.keel = 'est'
        LEFT JOIN teosed_koosseisud tk ON tk.teosed_id = t.id
        LEFT JOIN teosed_zanrid tz ON tz.teoseId = t.id
        WHERE 1 = 1
    ";

    $params = [];

    if ($filters['genreId'] > 0) {
        $sql .= " AND tz.zanrId = :genreId";
        $params[':genreId'] = $filters['genreId'];
    }

    if ($filters['composerId'] > 0) {
        $sql .= " AND h.id = :composerId";
        $params[':composerId'] = $filters['composerId'];
    }

    if ($filters['title'] !== '') {
        $sql .= " AND (tt.pealkiri LIKE :title1 OR tk.pealkiri LIKE :title2)";
        $params[':title1'] = '%' . $filters['title'] . '%';
        $params[':title2'] = '%' . $filters['title'] . '%';
    }

    if ($filters['keyword'] !== '') {
        $sql .= " AND (
            tt.pealkiri LIKE :kw1
            OR tt.seletusrida LIKE :kw2
            OR tt.koosseis LIKE :kw3
            OR tt.lisainfo LIKE :kw4
            OR tk.koosseis_tekst LIKE :kw5
        )";
        $needle = '%' . $filters['keyword'] . '%';
        $params[':kw1'] = $needle;
        $params[':kw2'] = $needle;
        $params[':kw3'] = $needle;
        $params[':kw4'] = $needle;
        $params[':kw5'] = $needle;
    }

    $sql = "SELECT * FROM ($sql) AS sub ORDER BY helilooja ASC, pealkiri ASC";
    
    // temporary, for debugging:
    error_log("SQL: " . $sql);
    error_log("PARAMS: " . json_encode($params, JSON_UNESCAPED_UNICODE));    

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();
    $rows = $stmt->fetchAll();

    $filtered = [];
    foreach ($rows as $row) {
        $bornYear = parse_year((string) ($row['helilooja_sunnikuupaev'] ?? ''));
        if ($activeFilters['bornYear']) {
            if ($filters['bornYearFrom'] > 0 && ($bornYear === null || $bornYear < $filters['bornYearFrom'])) {
                continue;
            }
            if ($filters['bornYearTo'] > 0 && ($bornYear === null || $bornYear > $filters['bornYearTo'])) {
                continue;
            }
        }

        $compositionYear = parse_year((string) ($row['aasta'] ?? ''));
        if ($activeFilters['compositionYear']) {
            if ($filters['compositionYearFrom'] > 0 && ($compositionYear === null || $compositionYear < $filters['compositionYearFrom'])) {
                continue;
            }
            if ($filters['compositionYearTo'] > 0 && ($compositionYear === null || $compositionYear > $filters['compositionYearTo'])) {
                continue;
            }
        }

        $duration = parse_duration_minutes((string) ($row['pikkus'] ?? ''));
        if ($activeFilters['duration']) {
            if ($filters['durationFrom'] > 0 && ($duration === null || $duration < $filters['durationFrom'])) {
                continue;
            }
            if ($filters['durationTo'] > 0 && ($duration === null || $duration > $filters['durationTo'])) {
                continue;
            }
        }

        $instrumentationRaw = (string) ($row['intrumentatsioon'] ?? '');
        $instrumentation = [];
        if ($instrumentationRaw !== '' && strtolower($instrumentationRaw) !== 'null') {
            $decoded = json_decode($instrumentationRaw, true);
            if (is_array($decoded)) {
                $instrumentation = $decoded;
            }
        }

        $playerCount = extract_player_count($instrumentation);
        if ($activeFilters['performers'] && ($playerCount < $filters['performersFrom'] || $playerCount > $filters['performersTo'])) {
            continue;
        }

        $soloists = extract_soloists($instrumentation);
        if ($activeFilters['soloists'] && ($soloists < $filters['soloistsFrom'] || $soloists > $filters['soloistsTo'])) {
            continue;
        }

        $instrumentsInWork = extract_instrument_ids($instrumentation);
        if (!empty($selectedInstruments)) {
            $selectedSet = array_fill_keys($selectedInstruments, true);
            $workSet = array_fill_keys($instrumentsInWork, true);

            $hasAllSelected = true;
            foreach ($selectedSet as $abbr => $_) {
                if (!isset($workSet[$abbr])) {
                    $hasAllSelected = false;
                    break;
                }
            }

            if (!$hasAllSelected) {
                continue;
            }

            if ($filters['onlySelectedInstruments']) {
                foreach ($workSet as $abbr => $_) {
                    if (!isset($selectedSet[$abbr])) {
                        $hasAllSelected = false;
                        break;
                    }
                }
                if (!$hasAllSelected) {
                    continue;
                }
            }
        }

        $teosId = (int) $row['teos_id'];
        $filtered[] = [
            'teos_id' => $teosId,
            'helilooja' => (string) $row['helilooja'],
            'pealkiri' => (string) $row['pealkiri'],
            'koosseis_tekst' => strip_tags((string) $row['koosseis_tekst']),
            'aasta' => $compositionYear,
            'pikkus_min' => $duration,
            'esitajaid' => $playerCount,
            'soliste' => $soloists,
            'url' => 'https://www.emic.ee/?sisu=heliloojad&mid=58&id=' . $teosId . '&lang=est&action=view&method=teosed#' . $teosId,
        ];
    }

    $total = count($filtered);
    $offset = ($page - 1) * $perPage;
    $items = array_slice($filtered, $offset, $perPage);

    json_response([
        'ok' => true,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => debug_error($e, 'Otsing ebaonnestus.'),
    ], 500);
}
