<?php
// ============================================================
// periodos.php — Gestión de períodos académicos
// GET    periodos.php              → listar todos
// GET    periodos.php?activo=1     → período activo actual
// POST   periodos.php              → crear nuevo período
// PUT    periodos.php?id=X         → activar / editar período
// ============================================================
require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conectar();

    // Crear tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS periodo_academico (
        id_periodo   INT AUTO_INCREMENT PRIMARY KEY,
        nombre       VARCHAR(20)  NOT NULL UNIQUE,  -- ej: 2026-1
        descripcion  VARCHAR(100),
        fecha_inicio DATE,
        fecha_fin    DATE,
        activo       TINYINT(1) DEFAULT 0,
        creado_en    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Si no existe ningún período activo, crear el actual
    $hay = $pdo->query("SELECT COUNT(*) AS n FROM periodo_academico")->fetch()['n'];
    if ($hay == 0) {
        $anio = date('Y');
        $semestre = (int)date('m') <= 6 ? '1' : '2';
        $pdo->exec("INSERT INTO periodo_academico (nombre, descripcion, activo)
                    VALUES ('{$anio}-{$semestre}', 'Período inicial', 1)");
    }

    if ($metodo === 'GET') {
        if (isset($_GET['activo'])) {
            $p = $pdo->query("SELECT * FROM periodo_academico WHERE activo = 1 LIMIT 1")->fetch();
            responder($p ?: null);
        }
        $stmt = $pdo->query("SELECT * FROM periodo_academico ORDER BY creado_en DESC");
        responder($stmt->fetchAll());
    }

    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'nombre');

        $chk = $pdo->prepare("SELECT id_periodo FROM periodo_academico WHERE nombre = ?");
        $chk->execute([trim($d['nombre'])]);
        if ($chk->fetch()) responder('Ya existe un período con ese nombre.', false, 409);

        // Si se activa este, desactivar los demás
        if (!empty($d['activo'])) {
            $pdo->exec("UPDATE periodo_academico SET activo = 0");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO periodo_academico (nombre, descripcion, fecha_inicio, fecha_fin, activo)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            trim($d['nombre']),
            trim($d['descripcion'] ?? ''),
            $d['fecha_inicio'] ?? null,
            $d['fecha_fin']    ?? null,
            empty($d['activo']) ? 0 : 1,
        ]);
        $newId = (int)$pdo->lastInsertId();
        responder(['id' => $newId, 'mensaje' => 'Período creado correctamente.'], true, 201);
    }

    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $d = leerJSON();

        // Activar este período (desactivar los demás)
        if (isset($d['activo']) && $d['activo']) {
            $pdo->exec("UPDATE periodo_academico SET activo = 0");
            $pdo->prepare("UPDATE periodo_academico SET activo = 1 WHERE id_periodo = ?")
                ->execute([$id]);
            responder(['mensaje' => 'Período activado correctamente.']);
        }

        // Editar datos
        $mapa = ['nombre' => 'nombre = ?', 'descripcion' => 'descripcion = ?',
                 'fecha_inicio' => 'fecha_inicio = ?', 'fecha_fin' => 'fecha_fin = ?'];
        $campos = []; $vals = [];
        foreach ($mapa as $k => $sql) {
            if (array_key_exists($k, $d)) { $campos[] = $sql; $vals[] = $d[$k]; }
        }
        if (empty($campos)) responder('Sin campos para actualizar.', false, 422);
        $vals[] = $id;
        $pdo->prepare("UPDATE periodo_academico SET ".implode(',',$campos)." WHERE id_periodo = ?")
            ->execute($vals);
        responder(['mensaje' => 'Período actualizado.']);
    }

    responder('Método no soportado.', false, 405);
} catch (PDOException $e) {
    responder('Error de base de datos: '.$e->getMessage(), false, 500);
}