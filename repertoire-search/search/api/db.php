<?php
declare(strict_types=1);

function emic_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PHP laiendus pdo_mysql puudub. Paigalda php-mysql.');
    }

    $dsn = 'mysql:host=127.0.0.1;dbname=emic;charset=utf8mb4';
    $user = 'emic';
    $password = 'tobias';

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    return $pdo;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $parsed = json_decode($raw, true);
    return is_array($parsed) ? $parsed : [];
}

function debug_error(Throwable $e, string $fallback): string
{
    $debug = (string) getenv('EMIC_DEBUG');
    if ($debug === '1' || strtolower($debug) === 'true') {
        return $e->getMessage();
    }

    return $fallback;
}
