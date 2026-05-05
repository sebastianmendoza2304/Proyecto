<?php
// ============================================================
// reportes.php — CRUD tabla "reporte_riesgo" (BD sistema)
//
// GET    /reportes.php                    → listar todos (con JOINs)
// GET    /reportes.php?id=1               → obtener uno con seguimientos
// GET    /reportes.php?estado=Reportado   → filtrar por estado
// GET    /reportes.php?docente_id=2       → por docente (vía usuario)
// GET    /reportes.php?buscar=ana         → búsqueda libre
// GET    /reportes.php?fecha_ini=...&fecha_fin=...
// POST   /reportes.php                    → crear reporte
// PUT    /reportes.php?id=1               → actualizar estado
// DELETE /reportes.php?id=1               → eliminar
// ============================================================

require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// SELECT base con todos los JOINs para traer nombres en lugar de IDs
const SQL_BASE = "
    SELECT
        r.id_reporte            AS id,
        CONCAT(e.nombres, ' ', e.apellidos) AS estudiante,
        e.documento_identidad   AS documento,
        e.carrera_cursada       AS carrera,
        m.nombre_materia        AS materia,
        mo.nombre_motivo        AS motivo,
        r.observaciones,
        r.estado,
        r.remitido_a,
        r.motivo_remision,
        CONCAT(r.fecha_registro, ' ', TIME_FORMAT(r.hora_registro,'%H:%i')) AS fecha,
        r.id_estudiante,
        r.id_materia,
        r.id_motivo,
        r.id_semestre_académico AS id_semestre
    FROM reporte_riesgo r
    JOIN estudiante    e  ON r.id_estudiante   = e.id_estudiante
    JOIN materia       m  ON r.id_materia       = m.id_materia
    JOIN motivo        mo ON r.id_motivo        = mo.id_motivo
";

try {
    $pdo = conectar();

    // ── GET ──────────────────────────────────────────────
    if ($metodo === 'GET') {

        // Obtener uno con sus seguimientos
        if ($id) {
            $stmt = $pdo->prepare(SQL_BASE . ' WHERE r.id_reporte = ?');
            $stmt->execute([$id]);
            $rep = $stmt->fetch();
            if (!$rep) responder('Reporte no encontrado.', false, 404);

            $seg = $pdo->prepare(
                'SELECT s.id_seguimiento AS id, s.id_reporte AS reporte_id,
                        CONCAT(u.nombres," ",u.apellidos) AS profesional,
                        s.tipo_intervención AS intervencion,
                        s.observaciones, s.recomendaciones,
                        CONCAT(s.fecha_intervención," ",TIME_FORMAT(s.hora_intervención,"%H:%i")) AS fecha
                 FROM seguimiento s
                 JOIN usuario u ON s.id_profesional = u.id_usuario
                 WHERE s.id_reporte = ?
                 ORDER BY s.fecha_intervención, s.hora_intervención'
            );
            $seg->execute([$id]);
            $rep['seguimientos'] = $seg->fetchAll();

            responder($rep);
        }

        // Listado con filtros
        $sql    = SQL_BASE . ' WHERE 1=1';
        $params = [];

        if (!empty($_GET['estado'])) {
            $sql .= ' AND r.estado = ?';
            $params[] = $_GET['estado'];
        }
        if (!empty($_GET['motivo'])) {
            $sql .= ' AND mo.nombre_motivo = ?';
            $params[] = $_GET['motivo'];
        }
        if (!empty($_GET['carrera'])) {
            $sql .= ' AND e.carrera_cursada LIKE ?';
            $params[] = '%' . $_GET['carrera'] . '%';
        }
        if (!empty($_GET['fecha_ini'])) {
            $sql .= ' AND r.fecha_registro >= ?';
            $params[] = $_GET['fecha_ini'];
        }
        if (!empty($_GET['fecha_fin'])) {
            $sql .= ' AND r.fecha_registro <= ?';
            $params[] = $_GET['fecha_fin'];
        }
        if (!empty($_GET['buscar'])) {
            $like = '%' . $_GET['buscar'] . '%';
            $sql .= ' AND (e.nombres LIKE ? OR e.apellidos LIKE ? OR m.nombre_materia LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql .= ' ORDER BY r.fecha_registro DESC, r.hora_registro DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ── POST — Crear reporte ────────────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'estudiante_id', 'materia_id', 'motivo_id', 'observaciones');

        // Obtener semestre activo (el más reciente)
        $semestre = $pdo->query(
            'SELECT id_semestre FROM `semestre_acacémico`
             ORDER BY año DESC, periodo DESC LIMIT 1'
        )->fetch();
        $semestreId = $semestre ? $semestre['id_semestre'] : 1;

        // Crear caso
        $pdo->prepare('INSERT INTO caso (descripcion) VALUES (?)')->execute(['Reporte automático']);
        $casoId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO reporte_riesgo
               (observaciones, fecha_registro, hora_registro, estado, caso,
                id_motivo, id_materia, id_semestre_académico, id_estudiante)
             VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($d['observaciones']),
            $d['estado'] ?? 'Reportado',
            $casoId,
            (int)$d['motivo_id'],
            (int)$d['materia_id'],
            $semestreId,
            (int)$d['estudiante_id'],
        ]);

        $nuevoId = (int)$pdo->lastInsertId();

        $stmt2 = $pdo->prepare(SQL_BASE . ' WHERE r.id_reporte = ?');
        $stmt2->execute([$nuevoId]);

        responder([
            'id'      => $nuevoId,
            'reporte' => $stmt2->fetch(),
            'mensaje' => 'Reporte registrado correctamente.',
        ], true, 201);
    }

    // ── PUT — Actualizar estado / remisión ──────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $d = leerJSON();

        $chk = $pdo->prepare('SELECT id_reporte FROM reporte_riesgo WHERE id_reporte = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) responder('Reporte no encontrado.', false, 404);

        $estadosValidos = ['Reportado','En revisión','En seguimiento','Remitido','Cerrado'];
        $campos = []; $valores = [];

        if (!empty($d['estado'])) {
            if (!in_array($d['estado'], $estadosValidos, true)) {
                responder('Estado no válido.', false, 422);
            }
            $campos[]  = 'estado = ?';
            $valores[] = $d['estado'];
        }
        if (isset($d['observaciones'])) {
            $campos[]  = 'observaciones = ?';
            $valores[] = trim($d['observaciones']);
        }
        if (isset($d['remitido_a'])) {
            $campos[]  = 'remitido_a = ?';
            $valores[] = trim($d['remitido_a']);
        }
        if (isset($d['motivo_remision'])) {
            $campos[]  = 'motivo_remision = ?';
            $valores[] = trim($d['motivo_remision']);
        }

        if (empty($campos)) responder('No se enviaron campos para actualizar.', false, 422);

        $valores[] = $id;
        $pdo->prepare('UPDATE reporte_riesgo SET ' . implode(', ', $campos) . ' WHERE id_reporte = ?')
            ->execute($valores);

        $stmt2 = $pdo->prepare(SQL_BASE . ' WHERE r.id_reporte = ?');
        $stmt2->execute([$id]);
        responder(['reporte' => $stmt2->fetch(), 'mensaje' => 'Reporte actualizado correctamente.']);
    }

    // ── DELETE ──────────────────────────────────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $pdo->prepare('DELETE FROM seguimiento WHERE id_reporte = ?')->execute([$id]);
        $stmt = $pdo->prepare('DELETE FROM reporte_riesgo WHERE id_reporte = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) responder('Reporte no encontrado.', false, 404);
        responder(['mensaje' => 'Reporte eliminado correctamente.']);
    }

    responder('Método no soportado.', false, 405);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}