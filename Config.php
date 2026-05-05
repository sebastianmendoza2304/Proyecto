<?php
// ============================================================
// config.php — Conexión a la BD "sistema"
// ============================================================

define('DB_HOST',   'localhost');
define('DB_USER',   'root');    // Cambia si tu usuario MySQL es otro
define('DB_PASS',   '');        // Cambia si tienes contraseña MySQL
define('DB_NAME',   'sistema'); // Tu BD se llama "sistema"
define('DB_CHARSET','utf8mb4');

date_default_timezone_set('America/Bogota');

function conectar(): PDO {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

function responder(mixed $datos, bool $ok = true, int $codigo = 200): never {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode(['ok' => $ok, 'data' => $datos], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function leerJSON(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

function requerir(array $datos, string ...$campos): void {
    foreach ($campos as $c) {
        if (!isset($datos[$c]) || trim((string)$datos[$c]) === '') {
            responder("El campo '$c' es obligatorio.", false, 422);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }