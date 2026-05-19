<?php
// ============================================================
// reportes.php — CRUD tabla "reporte_riesgo" (BD sistema)
// ============================================================

require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// SELECT base usando la vista v_reportes que ya hace todos los JOINs
const SQL_BASE = 'SELECT * FROM v_reportes';

try {
    $pdo = conectar();

    // ── GET ──────────────────────────────────────────────
    if ($metodo === 'GET') {

        if ($id) {
            $stmt = $pdo->prepare(SQL_BASE . ' WHERE id = ?');
            $stmt->execute([$id]);
            $rep = $stmt->fetch();
            if (!$rep) responder('Reporte no encontrado.', false, 404);

            $seg = $pdo->prepare('SELECT * FROM v_seguimientos WHERE reporte_id = ? ORDER BY fecha');
            $seg->execute([$id]);
            $rep['seguimientos'] = $seg->fetchAll();

            responder($rep);
        }

        $sql    = SQL_BASE . ' WHERE 1=1';
        $params = [];

        if (!empty($_GET['estado']))        { $sql .= ' AND estado = ?';         $params[] = $_GET['estado']; }
        if (!empty($_GET['docente_id']))    { $sql .= ' AND id_docente = ?';     $params[] = (int)$_GET['docente_id']; }
        if (!empty($_GET['estudiante_id'])) { $sql .= ' AND id_estudiante = ?';  $params[] = (int)$_GET['estudiante_id']; }
        if (!empty($_GET['motivo']))        { $sql .= ' AND motivo = ?';         $params[] = $_GET['motivo']; }
        if (!empty($_GET['carrera']))       { $sql .= ' AND carrera LIKE ?';     $params[] = '%' . $_GET['carrera'] . '%'; }
        if (!empty($_GET['fecha_ini']))     { $sql .= ' AND fecha_registro >= ?'; $params[] = $_GET['fecha_ini']; }
        if (!empty($_GET['fecha_fin']))     { $sql .= ' AND fecha_registro <= ?'; $params[] = $_GET['fecha_fin']; }

        if (!empty($_GET['buscar'])) {
            $like = '%' . $_GET['buscar'] . '%';
            $sql .= ' AND (estudiante LIKE ? OR materia LIKE ? OR docente LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (!empty($_GET['periodo_id'])) {
            $sql .= ' AND id_periodo = ?';
            $params[] = (int)$_GET['periodo_id'];
        }

        $sql .= ' ORDER BY fecha_registro DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ── POST — Crear reporte ────────────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'estudiante_id', 'docente_id', 'observaciones');

        // Resolver materia_id: puede venir como id o como nombre
        if (empty($d['materia_id']) && !empty($d['materia_nombre'])) {
            $mStmt = $pdo->prepare('SELECT id_materia FROM materia WHERE nombre_materia = ? LIMIT 1');
            $mStmt->execute([trim($d['materia_nombre'])]);
            $m = $mStmt->fetch();
            // Si no existe, la creamos al vuelo
            if (!$m) {
                $pdo->prepare('INSERT INTO materia (nombre_materia, codigo_materia) VALUES (?,?)')->execute([trim($d['materia_nombre']), strtoupper(substr(md5($d['materia_nombre']),0,6))]);
                $d['materia_id'] = (int)$pdo->lastInsertId();
            } else {
                $d['materia_id'] = (int)$m['id_materia'];
            }
        }
        if (empty($d['materia_id'])) $d['materia_id'] = 1;

        // Resolver motivo_id: puede venir como id o como nombre
        if (empty($d['motivo_id']) && !empty($d['motivo_nombre'])) {
            $moStmt = $pdo->prepare('SELECT id_motivo FROM motivo WHERE nombre_motivo = ? LIMIT 1');
            $moStmt->execute([trim($d['motivo_nombre'])]);
            $mo = $moStmt->fetch();
            $d['motivo_id'] = $mo ? (int)$mo['id_motivo'] : 1;
        }
        if (empty($d['motivo_id'])) $d['motivo_id'] = 1;

        // Obtener semestre activo (si existe la tabla)
        $semestreId = 1;
        try {
            $sem = $pdo->query("SELECT id_semestre_academico FROM semestre_academico WHERE activo = 1 LIMIT 1")->fetch();
            if ($sem) $semestreId = $sem['id_semestre_academico'];
        } catch (Exception $e) { /* tabla no existe o nombre distinto, usar 1 */ }

        // Insertar reporte (sin tabla caso — eliminada por ser redundante con reporte_riesgo)
        // Obtener período activo automáticamente
        $periodoId = null;
        try {
            $pStmt = $pdo->query("SELECT id FROM periodo_academico WHERE activo = 1 LIMIT 1");
            $per = $pStmt->fetch();
            if ($per) $periodoId = (int)$per['id'];
        } catch(Exception $e) { /* tabla no existe aún */ }

        $stmt = $pdo->prepare(
            'INSERT INTO reporte_riesgo
               (id_estudiante, id_docente, id_materia, id_motivo,
                id_periodo, observaciones, estado, fecha_registro, hora_registro)
             VALUES (?,?,?,?,?,?,?, CURDATE(), CURTIME())'
        );
        $stmt->execute([
            (int)$d['estudiante_id'],
            (int)$d['docente_id'],
            (int)$d['materia_id'],
            (int)$d['motivo_id'],
            $periodoId,
            trim($d['observaciones']),
            $d['estado'] ?? 'Reportado',
        ]);

        $nuevoId = (int)$pdo->lastInsertId();
        $stmt2 = $pdo->prepare(SQL_BASE . ' WHERE id = ?');
        $stmt2->execute([$nuevoId]);

        responder([
            'id'      => $nuevoId,
            'reporte' => $stmt2->fetch(),
            'mensaje' => 'Reporte registrado correctamente.',
        ], true, 201);
    }

    // ── PUT — Actualizar ────────────────────────────────
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
        if (isset($d['observaciones']))   { $campos[]='observaciones = ?';   $valores[]=trim($d['observaciones']); }
        if (isset($d['remitido_a']))      { $campos[]='remitido_a = ?';      $valores[]=trim($d['remitido_a']); }
        if (isset($d['motivo_remision'])) { $campos[]='motivo_remision = ?'; $valores[]=trim($d['motivo_remision']); }

        if (empty($campos)) responder('No se enviaron campos.', false, 422);

        $valores[] = $id;
        $pdo->prepare('UPDATE reporte_riesgo SET ' . implode(', ', $campos) . ' WHERE id_reporte = ?')
            ->execute($valores);

        $stmt2 = $pdo->prepare(SQL_BASE . ' WHERE id = ?');
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