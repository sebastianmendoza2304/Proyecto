<?php
// ============================================================
// periodos.php — CRUD para períodos académicos
// GET  periodos.php             → listar todos
// GET  periodos.php?activo=1    → obtener el período activo
// GET  periodos.php?id=X        → obtener uno
// POST periodos.php             → crear período
// PUT  periodos.php?id=X        → actualizar / activar
// DELETE periodos.php?id=X      → eliminar
// ============================================================
require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conectar();

    // Crear tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS periodo_academico (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        nombre      VARCHAR(20)  NOT NULL UNIQUE COMMENT 'Ej: 2026-1',
        descripcion VARCHAR(150),
        fecha_inicio DATE,
        fecha_fin    DATE,
        activo       TINYINT(1) NOT NULL DEFAULT 0,
        creado_en    DATETIME   DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB COMMENT='Períodos académicos del sistema'");

    // ── GET ──────────────────────────────────────────────
    if ($metodo === 'GET') {
        if (!empty($_GET['activo'])) {
            $stmt = $pdo->query("SELECT * FROM periodo_academico WHERE activo = 1 LIMIT 1");
            $p = $stmt->fetch();
            if (!$p) responder(null); // sin período activo
            responder($p);
        }
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM periodo_academico WHERE id = ?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) responder('Período no encontrado.', false, 404);
            // Contar reportes de ese período
            try {
                $cnt = $pdo->prepare("SELECT COUNT(*) AS total FROM reporte_riesgo WHERE id_periodo = ?");
                $cnt->execute([$id]);
                $p['total_reportes'] = (int)$cnt->fetch()['total'];
            } catch(Exception $e) { $p['total_reportes'] = 0; }
            responder($p);
        }
        $stmt = $pdo->query("SELECT p.*,
            (SELECT COUNT(*) FROM reporte_riesgo r WHERE r.id_periodo = p.id) AS total_reportes
            FROM periodo_academico p ORDER BY p.id DESC");
        responder($stmt->fetchAll());
    }

    // ── POST — Crear período ────────────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'nombre');
        $chk = $pdo->prepare("SELECT id FROM periodo_academico WHERE nombre = ?");
        $chk->execute([trim($d['nombre'])]);
        if ($chk->fetch()) responder('Ya existe un período con ese nombre.', false, 409);

        $stmt = $pdo->prepare(
            "INSERT INTO periodo_academico (nombre, descripcion, fecha_inicio, fecha_fin, activo)
             VALUES (?,?,?,?,?)"
        );
        $stmt->execute([
            trim($d['nombre']),
            trim($d['descripcion'] ?? ''),
            $d['fecha_inicio'] ?: null,
            $d['fecha_fin']    ?: null,
            isset($d['activo']) && $d['activo'] ? 1 : 0,
        ]);
        $newId = (int)$pdo->lastInsertId();
        // Si se crea como activo, desactivar los demás
        if (isset($d['activo']) && $d['activo']) {
            $pdo->prepare("UPDATE periodo_academico SET activo=0 WHERE id != ?")->execute([$newId]);
        }
        responder(['id' => $newId, 'mensaje' => 'Período creado correctamente.'], true, 201);
    }

    // ── PUT — Actualizar / Activar ──────────────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $d = leerJSON();

        // Si se activa este período, desactivar los demás primero
        if (!empty($d['activo'])) {
            $pdo->exec("UPDATE periodo_academico SET activo = 0");
            $pdo->prepare("UPDATE periodo_academico SET activo = 1 WHERE id = ?")->execute([$id]);
            responder(['mensaje' => 'Período activado correctamente.']);
        }

        $campos = []; $vals = [];
        foreach (['nombre','descripcion','fecha_inicio','fecha_fin'] as $f) {
            if (array_key_exists($f, $d)) {
                $campos[] = "$f = ?";
                $vals[]   = $d[$f] ?: null;
            }
        }
        if (empty($campos)) responder('Sin campos para actualizar.', false, 422);
        $vals[] = $id;
        $pdo->prepare("UPDATE periodo_academico SET ".implode(',',$campos)." WHERE id=?")->execute($vals);
        responder(['mensaje' => 'Período actualizado.']);
    }

    // ── DELETE ──────────────────────────────────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta id.', false, 400);
        // No eliminar si tiene reportes
        try {
            $cnt = $pdo->prepare("SELECT COUNT(*) AS n FROM reporte_riesgo WHERE id_periodo = ?");
            $cnt->execute([$id]);
            if ((int)$cnt->fetch()['n'] > 0) {
                responder('No se puede eliminar: el período tiene reportes asociados.', false, 409);
            }
        } catch(Exception $e) {}
        $pdo->prepare("DELETE FROM periodo_academico WHERE id = ?")->execute([$id]);
        responder(['mensaje' => 'Período eliminado.']);
    }

    responder('Método no soportado.', false, 405);
} catch (PDOException $e) {
    responder('Error de base de datos: '.$e->getMessage(), false, 500);
}