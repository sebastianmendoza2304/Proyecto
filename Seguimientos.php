<?php
// ============================================================
// seguimientos.php — CRUD de seguimientos de casos
// Sistema de Alertas por Riesgo Académico - Universidad Libre
//
// Métodos soportados:
//   GET    /seguimientos.php                  → listar todos
//   GET    /seguimientos.php?id=1             → obtener uno
//   GET    /seguimientos.php?reporte_id=2     → seguimientos de un reporte
//   GET    /seguimientos.php?profesional_id=3 → por profesional
//   POST   /seguimientos.php                  → registrar seguimiento
//   PUT    /seguimientos.php?id=1             → actualizar seguimiento
//   DELETE /seguimientos.php?id=1             → eliminar seguimiento
// ============================================================

require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conectar();

    // ──────────────────────────────────────────────────────────
    // GET — Listar / Obtener
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'GET') {

        // Obtener uno por ID
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM v_seguimientos WHERE id = ?');
            $stmt->execute([$id]);
            $seg = $stmt->fetch();
            if (!$seg) responder('Seguimiento no encontrado.', false, 404);
            responder($seg);
        }

        $sql    = 'SELECT * FROM v_seguimientos WHERE 1=1';
        $params = [];

        // Filtrar por reporte
        if (!empty($_GET['reporte_id'])) {
            $sql .= ' AND reporte_id = ?';
            $params[] = (int)$_GET['reporte_id'];
        }
        // Filtrar por profesional
        if (!empty($_GET['profesional_id'])) {
            $sql .= ' AND id IN (SELECT id FROM seguimientos WHERE profesional_id = ?)';
            $params[] = (int)$_GET['profesional_id'];
        }
        // Rango de fechas
        if (!empty($_GET['fecha_ini'])) {
            $sql .= ' AND fecha >= ?';
            $params[] = $_GET['fecha_ini'] . ' 00:00:00';
        }
        if (!empty($_GET['fecha_fin'])) {
            $sql .= ' AND fecha <= ?';
            $params[] = $_GET['fecha_fin'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY fecha DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ──────────────────────────────────────────────────────────
    // POST — Registrar seguimiento
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();

        requerir($d, 'reporte_id', 'profesional_id', 'intervencion');

        // Verificar que el reporte existe
        $chkRep = $pdo->prepare('SELECT id, estado FROM reportes WHERE id = ?');
        $chkRep->execute([(int)$d['reporte_id']]);
        $reporte = $chkRep->fetch();
        if (!$reporte) responder('Reporte no encontrado.', false, 404);

        // Verificar que el profesional existe y tiene rol adecuado
        $chkProf = $pdo->prepare(
            "SELECT id FROM usuarios WHERE id = ? AND rol IN ('bienestar','director_bienestar')"
        );
        $chkProf->execute([(int)$d['profesional_id']]);
        if (!$chkProf->fetch()) {
            responder('Profesional no encontrado o sin permisos de bienestar.', false, 403);
        }

        // Insertar seguimiento
        $stmt = $pdo->prepare(
            'INSERT INTO seguimientos
               (reporte_id, profesional_id, intervencion, observaciones, recomendaciones)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$d['reporte_id'],
            (int)$d['profesional_id'],
            trim($d['intervencion']),
            trim($d['observaciones']   ?? ''),
            trim($d['recomendaciones'] ?? ''),
        ]);

        $nuevoId = (int)$pdo->lastInsertId();

        // Actualizar estado del reporte según lo indicado
        $nuevoEstado = $d['nuevo_estado'] ?? 'En seguimiento';
        $estadosValidos = ['En revisión', 'En seguimiento', 'Remitido', 'Cerrado'];

        if (in_array($nuevoEstado, $estadosValidos, true)) {
            $updRep = $pdo->prepare('UPDATE reportes SET estado = ? WHERE id = ?');
            $updRep->execute([$nuevoEstado, (int)$d['reporte_id']]);

            // Si se remite, guardar datos de remisión
            if ($nuevoEstado === 'Remitido' && !empty($d['remitido_a'])) {
                $updRem = $pdo->prepare(
                    'UPDATE reportes SET remitido_a = ?, motivo_remision = ? WHERE id = ?'
                );
                $updRem->execute([
                    trim($d['remitido_a']),
                    trim($d['motivo_remision'] ?? ''),
                    (int)$d['reporte_id'],
                ]);
            }
        }

        // Devolver el seguimiento recién creado con datos completos
        $stmt2 = $pdo->prepare('SELECT * FROM v_seguimientos WHERE id = ?');
        $stmt2->execute([$nuevoId]);

        responder([
            'id'          => $nuevoId,
            'seguimiento' => $stmt2->fetch(),
            'mensaje'     => 'Seguimiento registrado correctamente.',
        ], true, 201);
    }

    // ──────────────────────────────────────────────────────────
    // PUT — Actualizar seguimiento
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        $d = leerJSON();

        $chk = $pdo->prepare('SELECT id FROM seguimientos WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) responder('Seguimiento no encontrado.', false, 404);

        $mapa = [
            'intervencion'    => 'intervencion = ?',
            'observaciones'   => 'observaciones = ?',
            'recomendaciones' => 'recomendaciones = ?',
        ];

        $campos  = [];
        $valores = [];

        foreach ($mapa as $campo => $sql) {
            if (array_key_exists($campo, $d)) {
                $campos[]  = $sql;
                $valores[] = trim((string)$d[$campo]);
            }
        }

        if (empty($campos)) responder('No se enviaron campos para actualizar.', false, 422);

        $valores[] = $id;
        $stmt = $pdo->prepare(
            'UPDATE seguimientos SET ' . implode(', ', $campos) . ' WHERE id = ?'
        );
        $stmt->execute($valores);

        responder(['mensaje' => 'Seguimiento actualizado correctamente.']);
    }

    // ──────────────────────────────────────────────────────────
    // DELETE — Eliminar seguimiento
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        $stmt = $pdo->prepare('DELETE FROM seguimientos WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) responder('Seguimiento no encontrado.', false, 404);
        responder(['mensaje' => 'Seguimiento eliminado correctamente.']);
    }

    responder('Método no soportado.', false, 405);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}