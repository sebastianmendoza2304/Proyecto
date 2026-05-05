<?php
// ============================================================
// estudiantes.php — CRUD de estudiantes
// Sistema de Alertas por Riesgo Académico - Universidad Libre
//
// Métodos soportados:
//   GET    /estudiantes.php                    → listar todos
//   GET    /estudiantes.php?id=3               → obtener por ID
//   GET    /estudiantes.php?documento=1001...  → buscar por documento
//   GET    /estudiantes.php?carrera=Sistemas   → filtrar por carrera
//   GET    /estudiantes.php?estado=Activo      → filtrar por estado
//   GET    /estudiantes.php?periodo=2025-1     → filtrar por periodo
//   GET    /estudiantes.php?buscar=ana         → búsqueda libre
//   POST   /estudiantes.php                    → crear estudiante
//   PUT    /estudiantes.php?id=3               → actualizar estudiante
//   DELETE /estudiantes.php?id=3               → eliminar estudiante
// ============================================================

require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conectar();

    // ──────────────────────────────────────────────────────────
    // GET — Listar / Buscar / Obtener
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'GET') {

        // Obtener por ID
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM estudiantes WHERE id = ?');
            $stmt->execute([$id]);
            $est = $stmt->fetch();
            if (!$est) responder('Estudiante no encontrado.', false, 404);
            responder($est);
        }

        // Buscar por documento exacto
        if (!empty($_GET['documento'])) {
            $stmt = $pdo->prepare('SELECT * FROM estudiantes WHERE documento = ?');
            $stmt->execute([$_GET['documento']]);
            $est = $stmt->fetch();
            if (!$est) responder('Estudiante no encontrado.', false, 404);
            responder($est);
        }

        // Filtros combinados
        $sql    = 'SELECT * FROM estudiantes WHERE 1=1';
        $params = [];

        if (!empty($_GET['carrera'])) {
            $sql .= ' AND carrera LIKE ?';
            $params[] = '%' . $_GET['carrera'] . '%';
        }
        if (!empty($_GET['estado'])) {
            $sql .= ' AND estado = ?';
            $params[] = $_GET['estado'];
        }
        if (!empty($_GET['periodo'])) {
            $sql .= ' AND periodo = ?';
            $params[] = $_GET['periodo'];
        }
        // Búsqueda libre en nombre, apellidos o documento
        if (!empty($_GET['buscar'])) {
            $like = '%' . $_GET['buscar'] . '%';
            $sql .= ' AND (nombres LIKE ? OR apellidos LIKE ? OR documento LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY apellidos, nombres';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ──────────────────────────────────────────────────────────
    // POST — Crear estudiante
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();

        requerir($d, 'documento', 'nombres', 'apellidos', 'carrera');

        // Verificar documento único
        $chk = $pdo->prepare('SELECT id FROM estudiantes WHERE documento = ?');
        $chk->execute([$d['documento']]);
        if ($chk->fetch()) {
            responder('Ya existe un estudiante con ese documento.', false, 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO estudiantes
               (documento, nombres, apellidos, carrera, telefono, correo, periodo, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($d['documento']),
            trim($d['nombres']),
            trim($d['apellidos']),
            trim($d['carrera']),
            trim($d['telefono'] ?? ''),
            trim($d['correo']   ?? ''),
            trim($d['periodo']  ?? ''),
            $d['estado'] ?? 'Activo',
        ]);

        responder([
            'id'      => (int)$pdo->lastInsertId(),
            'mensaje' => 'Estudiante registrado correctamente.',
        ], true, 201);
    }

    // ──────────────────────────────────────────────────────────
    // PUT — Actualizar estudiante
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        $d = leerJSON();

        // Verificar que existe
        $chk = $pdo->prepare('SELECT id FROM estudiantes WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) responder('Estudiante no encontrado.', false, 404);

        // Campos actualizables
        $mapa = [
            'nombres'   => 'nombres = ?',
            'apellidos' => 'apellidos = ?',
            'carrera'   => 'carrera = ?',
            'telefono'  => 'telefono = ?',
            'correo'    => 'correo = ?',
            'periodo'   => 'periodo = ?',
            'estado'    => 'estado = ?',
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
            'UPDATE estudiantes SET ' . implode(', ', $campos) . ' WHERE id = ?'
        );
        $stmt->execute($valores);

        responder(['mensaje' => 'Estudiante actualizado correctamente.']);
    }

    // ──────────────────────────────────────────────────────────
    // DELETE — Eliminar estudiante
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        // Verificar que no tenga reportes activos
        $chk = $pdo->prepare(
            "SELECT COUNT(*) AS total FROM reportes
             WHERE estudiante_id = ? AND estado NOT IN ('Cerrado')"
        );
        $chk->execute([$id]);
        $res = $chk->fetch();
        if ($res['total'] > 0) {
            responder(
                'No se puede eliminar: el estudiante tiene reportes activos.',
                false, 409
            );
        }

        $stmt = $pdo->prepare('DELETE FROM estudiantes WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) responder('Estudiante no encontrado.', false, 404);
        responder(['mensaje' => 'Estudiante eliminado correctamente.']);
    }

    responder('Método no soportado.', false, 405);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}