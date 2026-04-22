<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$q = trim((string) ($_GET['q'] ?? ''));
$limit = 20;

try {
    $pdo = emic_db();

    if ($q === '') {
        $stmt = $pdo->prepare(
            "SELECT lyhend, nimi, nimi_eng, teised_nimed
             FROM instrumendid
             ORDER BY nimi ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $needle = '%' . $q . '%';
        $prefix = $q . '%';
        $stmt = $pdo->prepare(
            "SELECT lyhend, nimi, nimi_eng, teised_nimed
             FROM instrumendid
             WHERE lyhend LIKE :needle1
                OR nimi LIKE :needle2
                OR nimi_eng LIKE :needle3
                OR teised_nimed LIKE :needle4
             ORDER BY
                CASE
                    WHEN lyhend = :exact THEN 0
                    WHEN lyhend LIKE :prefix1 THEN 1
                    WHEN nimi LIKE :prefix2 THEN 2
                    ELSE 3
                END,
                nimi ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':needle1', $needle, PDO::PARAM_STR);
        $stmt->bindValue(':needle2', $needle, PDO::PARAM_STR);
        $stmt->bindValue(':needle3', $needle, PDO::PARAM_STR);
        $stmt->bindValue(':needle4', $needle, PDO::PARAM_STR);
        $stmt->bindValue(':exact', $q, PDO::PARAM_STR);
        $stmt->bindValue(':prefix1', $prefix, PDO::PARAM_STR);
        $stmt->bindValue(':prefix2', $prefix, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $decoded = json_decode((string) ($item['teised_nimed'] ?? '[]'), true);
        $item['teised_nimed'] = is_array($decoded) ? $decoded : [];
    }

    json_response([
        'ok' => true,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => debug_error($e, 'Instrumentide laadimine ebaonnestus.'),
    ], 500);
}
