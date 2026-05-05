<?php
// ============================================================
// config.php — Configuración de conexión a la base de datos
// Sistema de Alertas por Riesgo Académico - Universidad Libre
// ============================================================

define('DB_HOST',   'localhost');
define('DB_USER',   'root');        // Cambia por tu usuario de MySQL
define('DB_PASS',   '');            // Cambia por tu contraseña de MySQL
define('DB_NAME',   'alertas_academicas');
define('DB_CHARSET','utf8mb4');

// Zona horaria Colombia
date_default_timezone_set('America/Bogota');

/**
 * Retorna una conexión PDO lista para usar.
 * Lanza una excepción si no puede conectar.
 */
function conectar(): PDO {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $opciones);
}

/**
 * Devuelve siempre JSON y termina la ejecución.
 * @param mixed  $datos  Payload de respuesta
 * @param bool   $ok     true = éxito, false = error
 * @param int    $codigo Código HTTP
 */
function responder(mixed $datos, bool $ok = true, int $codigo = 200): never {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    // CORS para desarrollo local (ajusta en producción)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode([
        'ok'   => $ok,
        'data' => $datos,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Lee el cuerpo JSON de la petición y lo devuelve como array.
 */
function leerJSON(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

/**
 * Limpia y valida que el campo exista en el array dado.
 */
function requerir(array $datos, string ...$campos): void {
    foreach ($campos as $c) {
        if (!isset($datos[$c]) || trim((string)$datos[$c]) === '') {
            responder("El campo '$c' es obligatorio.", false, 422);
        }
    }
}

// Preflight OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}