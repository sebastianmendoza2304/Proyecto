<?php
// ============================================================
// seguimientos.php — CRUD tabla "seguimiento" (BD sistema)
//
// GET    /seguimientos.php                  → listar todos
// GET    /seguimientos.php?id=1             → obtener uno
// GET    /seguimientos.php?reporte_id=2     → por reporte
// GET    /seguimientos.php?profesional_id=3 → por profesional
// POST   /seguimientos.php                  → registrar seguimiento
// PUT    /seguimientos.php?id=1             → actualizar
// DELETE /seguimientos.php?id=1             → eliminar
// ============================================================

require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conectar();

    // ── GET ──────────────────────────────────────────────
    if ($metodo === 'GET') {

        if ($id) {
            $stmt = $pdo->prepare(
                'SELECT s.id_seguimiento AS id, s.id_reporte AS reporte_id,
                        CONCAT(u.nombres," ",u.apellidos) AS profesional,
                        s.tipo_intervención AS intervencion,
                        s.observaciones, s.recomendaciones,
                        CONCAT(s.fecha_intervención," ",TIME_FORMAT(s.hora_intervención,"%H:%i")) AS fecha
                 FROM seguimiento s
                 JOIN usuario u ON s.id_profesional = u.id_usuario
                 WHERE s.id_seguimiento = ?'
            );
            $stmt->execute([$id]);
            $seg = $stmt->fetch();
            if (!$seg) responder('Seguimiento no encontrado.', false, 404);
            responder($seg);
        }

        $sql    = 'SELECT s.id_seguimiento AS id, s.id_reporte AS reporte_id,
                          CONCAT(u.nombres," ",u.apellidos) AS profesional,
                          s.tipo_intervención AS intervencion,
                          s.observaciones, s.recomendaciones,
                          CONCAT(s.fecha_intervención," ",TIME_FORMAT(s.hora_intervención,"%H:%i")) AS fecha
                   FROM seguimiento s
                   JOIN usuario u ON s.id_profesional = u.id_usuario
                   WHERE 1=1';
        $params = [];

        if (!empty($_GET['reporte_id'])) {
            $sql .= ' AND s.id_reporte = ?';
            $params[] = (int)$_GET['reporte_id'];
        }
        if (!empty($_GET['profesional_id'])) {
            $sql .= ' AND s.id_profesional = ?';
            $params[] = (int)$_GET['profesional_id'];
        }
        if (!empty($_GET['fecha_ini'])) {
            $sql .= ' AND s.fecha_intervención >= ?';
            $params[] = $_GET['fecha_ini'];
        }
        if (!empty($_GET['fecha_fin'])) {
            $sql .= ' AND s.fecha_intervención <= ?';
            $params[] = $_GET['fecha_fin'];
        }

        $sql .= ' ORDER BY s.fecha_intervención DESC, s.hora_intervención DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ── POST — Registrar seguimiento ────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'reporte_id', 'profesional_id', 'intervencion');

        // Verificar que el reporte existe
        $chkRep = $pdo->prepare('SELECT id_reporte FROM reporte_riesgo WHERE id_reporte = ?');
        $chkRep->execute([(int)$d['reporte_id']]);
        if (!$chkRep->fetch()) responder('Reporte no encontrado.', false, 404);

        // Verificar que el profesional existe con rol de bienestar
        $chkProf = $pdo->prepare(
            "SELECT id_usuario FROM usuario WHERE id_usuario = ? AND rol IN ('bienestar','director_bienestar')"
        );
        $chkProf->execute([(int)$d['profesional_id']]);
        if (!$chkProf->fetch()) responder('El usuario no tiene permisos de bienestar.', false, 403);

        // Obtener o crear el id_caso del reporte
        $casoRep = $pdo->prepare('SELECT caso FROM reporte_riesgo WHERE id_reporte = ?');
        $casoRep->execute([(int)$d['reporte_id']]);
        $casoId = $casoRep->fetch()['caso'] ?? 1;

        $stmt = $pdo->prepare(
            'INSERT INTO seguimiento
               (id_caso, fecha_intervención, hora_intervención,
                tipo_intervención, observaciones, recomendaciones,
                id_reporte, id_profesional)
             VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $casoId,
            trim($d['intervencion']),
            trim($d['observaciones']   ?? ''),
            trim($d['recomendaciones'] ?? ''),
            (int)$d['reporte_id'],
            (int)$d['profesional_id'],
        ]);

        $nuevoId = (int)$pdo->lastInsertId();

        // Actualizar estado del reporte
        $nuevoEstado = $d['nuevo_estado'] ?? 'En seguimiento';
        $estadosValidos = ['En revisión','En seguimiento','Remitido','Cerrado'];
        if (in_array($nuevoEstado, $estadosValidos, true)) {
            $pdo->prepare('UPDATE reporte_riesgo SET estado = ? WHERE id_reporte = ?')
                ->execute([$nuevoEstado, (int)$d['reporte_id']]);

            // Si se remite, guardar datos de remisión
            if ($nuevoEstado === 'Remitido' && !empty($d['remitido_a'])) {
                $pdo->prepare(
                    'UPDATE reporte_riesgo SET remitido_a = ?, motivo_remision = ? WHERE id_reporte = ?'
                )->execute([trim($d['remitido_a']), trim($d['motivo_remision'] ?? ''), (int)$d['reporte_id']]);
            }
        }

        responder(['id' => $nuevoId, 'mensaje' => 'Seguimiento registrado correctamente.'], true, 201);
    }

    // ── PUT — Actualizar seguimiento ────────────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $d = leerJSON();

        $chk = $pdo->prepare('SELECT id_seguimiento FROM seguimiento WHERE id_seguimiento = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) responder('Seguimiento no encontrado.', false, 404);

        $mapa = [
            'intervencion'    => '`tipo_intervención` = ?',
            'observaciones'   => 'observaciones = ?',
            'recomendaciones' => 'recomendaciones = ?',
        ];
        $campos = []; $valores = [];
        foreach ($mapa as $key => $sql) {
            if (array_key_exists($key, $d)) {
                $campos[]  = $sql;
                $valores[] = trim((string)$d[$key]);
            }
        }

        if (empty($campos)) responder('No se enviaron campos para actualizar.', false, 422);

        $valores[] = $id;
        $pdo->prepare('UPDATE seguimiento SET ' . implode(', ', $campos) . ' WHERE id_seguimiento = ?')
            ->execute($valores);

        responder(['mensaje' => 'Seguimiento actualizado correctamente.']);
    }

    // ── DELETE ──────────────────────────────────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $stmt = $pdo->prepare('DELETE FROM seguimiento WHERE id_seguimiento = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) responder('Seguimiento no encontrado.', false, 404);
        responder(['mensaje' => 'Seguimiento eliminado correctamente.']);
    }

    responder('Método no soportado.', false, 405);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}