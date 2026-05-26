<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = emic_db();

    $genresStmt = $pdo->query(
        "SELECT id, nimi
         FROM tooted_kategooriad
         WHERE peidetud = '0'
         ORDER BY prioriteet DESC, nimi ASC"
    );
    $genres = $genresStmt->fetchAll();

    $composersStmt = $pdo->query(
        "SELECT id, nimi, sunnikuupaev
         FROM heliloojad
         WHERE staatus = 1
         ORDER BY nimi ASC"
    );
    $composers = $composersStmt->fetchAll();

        $textAuthorsStmt = $pdo->query(
                "SELECT DISTINCT TRIM(tekstiAutor) AS nimi
                 FROM teosed_tekstid
                 WHERE tekstiAutor IS NOT NULL
                     AND TRIM(tekstiAutor) <> ''
                 ORDER BY nimi ASC"
        );
        $textAuthors = $textAuthorsStmt->fetchAll();

    json_response([
        'ok' => true,
        'genres' => $genres,
        'composers' => $composers,
        'textAuthors' => $textAuthors,
        'yearMin' => 1845,
        'yearMax' => (int) date('Y'),
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => debug_error($e, 'Metaandmete laadimine ebaonnestus.'),
    ], 500);
}
