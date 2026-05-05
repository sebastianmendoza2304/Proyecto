<?php
// ============================================================
// reportes.php — CRUD de reportes de riesgo académico
// Sistema de Alertas por Riesgo Académico - Universidad Libre
//
// Métodos soportados:
//   GET    /reportes.php                      → listar todos (vista completa)
//   GET    /reportes.php?id=2                 → obtener uno por ID
//   GET    /reportes.php?estudiante_id=1      → por estudiante
//   GET    /reportes.php?docente_id=2         → por docente
//   GET    /reportes.php?estado=Reportado     → por estado
//   GET    /reportes.php?motivo=Inasistencia  → por motivo
//   GET    /reportes.php?carrera=Sistemas     → por carrera del estudiante
//   GET    /reportes.php?periodo=2025-1       → por periodo académico
//   GET    /reportes.php?fecha_ini=2025-01-01&fecha_fin=2025-06-30
//   GET    /reportes.php?buscar=ana           → búsqueda libre
//   POST   /reportes.php                      → crear reporte
//   PUT    /reportes.php?id=2                 → actualizar estado / remisión
//   DELETE /reportes.php?id=2                 → eliminar reporte (solo admin)
// ============================================================

require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conectar();

    // ──────────────────────────────────────────────────────────
    // GET — Listar / Filtrar / Obtener
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'GET') {

        // Obtener uno por ID (con datos completos de vista)
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM v_reportes WHERE id = ?');
            $stmt->execute([$id]);
            $rep = $stmt->fetch();
            if (!$rep) responder('Reporte no encontrado.', false, 404);

            // Adjuntar seguimientos de este reporte
            $seg = $pdo->prepare('SELECT * FROM v_seguimientos WHERE reporte_id = ? ORDER BY fecha');
            $seg->execute([$id]);
            $rep['seguimientos'] = $seg->fetchAll();

            responder($rep);
        }

        // Construir consulta con filtros
        $sql    = 'SELECT * FROM v_reportes WHERE 1=1';
        $params = [];

        if (!empty($_GET['estudiante_id'])) {
            // Buscar por ID de estudiante en la tabla base
            $sql    = 'SELECT r.*, CONCAT(e.nombres," ",e.apellidos) AS estudiante,
                              e.documento, e.carrera, u.nombre AS docente
                       FROM reportes r
                       JOIN estudiantes e ON r.estudiante_id = e.id
                       JOIN usuarios u ON r.docente_id = u.id
                       WHERE r.estudiante_id = ?';
            $params[] = (int)$_GET['estudiante_id'];
        } else {
            // Filtros sobre la vista
            if (!empty($_GET['docente_id'])) {
                // La vista no tiene docente_id; usamos subquery
                $sql .= ' AND id IN (SELECT id FROM reportes WHERE docente_id = ?)';
                $params[] = (int)$_GET['docente_id'];
            }
            if (!empty($_GET['estado'])) {
                $sql .= ' AND estado = ?';
                $params[] = $_GET['estado'];
            }
            if (!empty($_GET['motivo'])) {
                $sql .= ' AND motivo = ?';
                $params[] = $_GET['motivo'];
            }
            if (!empty($_GET['carrera'])) {
                $sql .= ' AND carrera LIKE ?';
                $params[] = '%' . $_GET['carrera'] . '%';
            }
            if (!empty($_GET['periodo'])) {
                $sql .= ' AND id IN (
                    SELECT r2.id FROM reportes r2
                    JOIN estudiantes e2 ON r2.estudiante_id = e2.id
                    WHERE e2.periodo = ?
                )';
                $params[] = $_GET['periodo'];
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
            // Búsqueda libre
            if (!empty($_GET['buscar'])) {
                $like = '%' . $_GET['buscar'] . '%';
                $sql .= ' AND (estudiante LIKE ? OR materia LIKE ? OR docente LIKE ?)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        $sql .= ' ORDER BY fecha DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ──────────────────────────────────────────────────────────
    // POST — Crear reporte
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();

        requerir($d, 'estudiante_id', 'docente_id', 'materia', 'motivo');

        // Verificar que el estudiante existe
        $chkEst = $pdo->prepare('SELECT id FROM estudiantes WHERE id = ?');
        $chkEst->execute([(int)$d['estudiante_id']]);
        if (!$chkEst->fetch()) responder('Estudiante no encontrado.', false, 404);

        // Verificar que el docente existe
        $chkDoc = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'docente'");
        $chkDoc->execute([(int)$d['docente_id']]);
        if (!$chkDoc->fetch()) responder('Docente no encontrado.', false, 404);

        // Motivos válidos
        $motivosValidos = ['Bajo rendimiento', 'Inasistencia', 'Seguimiento'];
        if (!in_array($d['motivo'], $motivosValidos, true)) {
            responder('Motivo no válido. Use: ' . implode(', ', $motivosValidos), false, 422);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO reportes
               (estudiante_id, docente_id, materia, motivo, observaciones, estado)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$d['estudiante_id'],
            (int)$d['docente_id'],
            trim($d['materia']),
            $d['motivo'],
            trim($d['observaciones'] ?? ''),
            $d['estado'] ?? 'Reportado',
        ]);

        $nuevoId = (int)$pdo->lastInsertId();

        // Devolver el reporte recién creado con datos completos
        $stmt2 = $pdo->prepare('SELECT * FROM v_reportes WHERE id = ?');
        $stmt2->execute([$nuevoId]);

        responder([
            'id'      => $nuevoId,
            'reporte' => $stmt2->fetch(),
            'mensaje' => 'Reporte registrado correctamente.',
        ], true, 201);
    }

    // ──────────────────────────────────────────────────────────
    // PUT — Actualizar estado / remisión del reporte
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        $d = leerJSON();

        // Verificar que existe
        $chk = $pdo->prepare('SELECT id, estado FROM reportes WHERE id = ?');
        $chk->execute([$id]);
        $actual = $chk->fetch();
        if (!$actual) responder('Reporte no encontrado.', false, 404);

        $estadosValidos = ['Reportado', 'En revisión', 'En seguimiento', 'Remitido', 'Cerrado'];

        $campos  = [];
        $valores = [];

        if (!empty($d['estado'])) {
            if (!in_array($d['estado'], $estadosValidos, true)) {
                responder('Estado no válido. Use: ' . implode(', ', $estadosValidos), false, 422);
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
        $stmt = $pdo->prepare(
            'UPDATE reportes SET ' . implode(', ', $campos) . ' WHERE id = ?'
        );
        $stmt->execute($valores);

        // Retornar el reporte actualizado
        $stmt2 = $pdo->prepare('SELECT * FROM v_reportes WHERE id = ?');
        $stmt2->execute([$id]);

        responder([
            'reporte' => $stmt2->fetch(),
            'mensaje' => 'Reporte actualizado correctamente.',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // DELETE — Eliminar reporte
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        // Eliminar seguimientos asociados primero (FK)
        $pdo->prepare('DELETE FROM seguimientos WHERE reporte_id = ?')->execute([$id]);

        $stmt = $pdo->prepare('DELETE FROM reportes WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) responder('Reporte no encontrado.', false, 404);
        responder(['mensaje' => 'Reporte eliminado correctamente.']);
    }

    responder('Método no soportado.', false, 405);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}