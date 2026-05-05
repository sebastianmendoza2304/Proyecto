<?php
// ============================================================
// estudiantes.php — CRUD tabla "estudiante" (BD sistema)
//
// GET    /estudiantes.php                   → listar todos
// GET    /estudiantes.php?id=1              → obtener uno
// GET    /estudiantes.php?documento=100...  → buscar por documento
// GET    /estudiantes.php?buscar=ana        → búsqueda libre
// GET    /estudiantes.php?carrera=Sistemas  → filtrar por carrera
// GET    /estudiantes.php?estado=Activo     → filtrar por estado
// POST   /estudiantes.php                   → crear estudiante
// PUT    /estudiantes.php?id=1              → actualizar
// DELETE /estudiantes.php?id=1              → eliminar
// ============================================================

require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conectar();

    // ── GET ──────────────────────────────────────────────
    if ($metodo === 'GET') {

        // Obtener por ID
        if ($id) {
            $stmt = $pdo->prepare(
                'SELECT id_estudiante AS id,
                        documento_identidad AS documento,
                        nombres, apellidos,
                        carrera_cursada AS carrera,
                        telefono,
                        correo_institucional AS correo,
                        `estado_académico` AS estado
                 FROM estudiante WHERE id_estudiante = ?'
            );
            $stmt->execute([$id]);
            $est = $stmt->fetch();
            if (!$est) responder('Estudiante no encontrado.', false, 404);
            responder($est);
        }

        // Buscar por documento exacto
        if (!empty($_GET['documento'])) {
            $stmt = $pdo->prepare(
                'SELECT id_estudiante AS id, documento_identidad AS documento,
                        nombres, apellidos, carrera_cursada AS carrera,
                        telefono, correo_institucional AS correo, `estado_académico` AS estado
                 FROM estudiante WHERE documento_identidad = ?'
            );
            $stmt->execute([$_GET['documento']]);
            $est = $stmt->fetch();
            if (!$est) responder('Estudiante no encontrado.', false, 404);
            responder($est);
        }

        // Filtros combinados
        $sql = 'SELECT id_estudiante AS id, documento_identidad AS documento,
                       nombres, apellidos, carrera_cursada AS carrera,
                       telefono, correo_institucional AS correo, `estado_académico` AS estado
                FROM estudiante WHERE 1=1';
        $params = [];

        if (!empty($_GET['carrera'])) {
            $sql .= ' AND carrera_cursada LIKE ?';
            $params[] = '%' . $_GET['carrera'] . '%';
        }
        if (!empty($_GET['estado'])) {
            $sql .= ' AND `estado_académico` = ?';
            $params[] = $_GET['estado'];
        }
        if (!empty($_GET['buscar'])) {
            $like = '%' . $_GET['buscar'] . '%';
            $sql .= ' AND (nombres LIKE ? OR apellidos LIKE ? OR documento_identidad LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql .= ' ORDER BY apellidos, nombres';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ── POST — Crear estudiante ─────────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'documento', 'nombres', 'apellidos', 'carrera');

        // Verificar documento único
        $chk = $pdo->prepare('SELECT id_estudiante FROM estudiante WHERE documento_identidad = ?');
        $chk->execute([$d['documento']]);
        if ($chk->fetch()) responder('Ya existe un estudiante con ese documento.', false, 409);

        // Necesitamos un usuario_rol; usamos el primero disponible o 1 por defecto
        $ur = $pdo->query('SELECT id_usuario_rol FROM usuario_rol LIMIT 1')->fetch();
        $urId = $ur ? $ur['id_usuario_rol'] : 1;

        $stmt = $pdo->prepare(
            'INSERT INTO estudiante
               (documento_identidad, nombres, apellidos, carrera_cursada,
                telefono, correo_institucional, `estado_académico`, usuario_rol)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            trim($d['documento']),
            trim($d['nombres']),
            trim($d['apellidos']),
            trim($d['carrera']),
            trim($d['telefono'] ?? ''),
            trim($d['correo']   ?? ''),
            $d['estado'] ?? 'Activo',
            $urId,
        ]);

        responder(['id' => (int)$pdo->lastInsertId(), 'mensaje' => 'Estudiante registrado correctamente.'], true, 201);
    }

    // ── PUT — Actualizar ────────────────────────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $d = leerJSON();

        $chk = $pdo->prepare('SELECT id_estudiante FROM estudiante WHERE id_estudiante = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) responder('Estudiante no encontrado.', false, 404);

        $mapa = [
            'nombres'   => 'nombres = ?',
            'apellidos' => 'apellidos = ?',
            'carrera'   => 'carrera_cursada = ?',
            'telefono'  => 'telefono = ?',
            'correo'    => 'correo_institucional = ?',
            'estado'    => '`estado_académico` = ?',
            'documento' => 'documento_identidad = ?',
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
        $pdo->prepare('UPDATE estudiante SET ' . implode(', ', $campos) . ' WHERE id_estudiante = ?')
            ->execute($valores);

        responder(['mensaje' => 'Estudiante actualizado correctamente.']);
    }

    // ── DELETE — Eliminar ───────────────────────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        // Verificar que no tenga reportes activos
        $chk = $pdo->prepare(
            "SELECT COUNT(*) AS total FROM reporte_riesgo
             WHERE id_estudiante = ? AND estado NOT IN ('Cerrado')"
        );
        $chk->execute([$id]);
        if ($chk->fetch()['total'] > 0) {
            responder('No se puede eliminar: el estudiante tiene reportes activos.', false, 409);
        }

        $stmt = $pdo->prepare('DELETE FROM estudiante WHERE id_estudiante = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) responder('Estudiante no encontrado.', false, 404);
        responder(['mensaje' => 'Estudiante eliminado correctamente.']);
    }

    responder('Método no soportado.', false, 405);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}