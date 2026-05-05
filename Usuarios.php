<?php
// ============================================================
// usuarios.php — CRUD tabla "usuario" (BD sistema)
//
// GET    /usuarios.php            → listar todos
// GET    /usuarios.php?id=2       → obtener uno
// GET    /usuarios.php?rol=docente→ filtrar por rol
// POST   /usuarios.php            → crear usuario
// PUT    /usuarios.php?id=2       → actualizar
// DELETE /usuarios.php?id=2       → desactivar
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
                'SELECT id_usuario AS id, nombre_usuario AS nombre,
                        documento_identidad AS documento, nombres, apellidos,
                        telefono, correo_institucional AS correo, rol, estado
                 FROM usuario WHERE id_usuario = ?'
            );
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            if (!$u) responder('Usuario no encontrado.', false, 404);
            responder($u);
        }

        $sql    = 'SELECT id_usuario AS id, nombre_usuario AS nombre,
                          documento_identidad AS documento, nombres, apellidos,
                          telefono, correo_institucional AS correo, rol, estado
                   FROM usuario WHERE 1=1';
        $params = [];

        if (!empty($_GET['rol'])) {
            $sql .= ' AND rol = ?';
            $params[] = $_GET['rol'];
        }
        if (isset($_GET['activo'])) {
            $sql .= ' AND estado = ?';
            $params[] = (int)$_GET['activo'];
        }
        if (!empty($_GET['buscar'])) {
            $like = '%' . $_GET['buscar'] . '%';
            $sql .= ' AND (nombres LIKE ? OR apellidos LIKE ? OR nombre_usuario LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql .= ' ORDER BY apellidos, nombres';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ── POST — Crear usuario ────────────────────────────
    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'nombre', 'password', 'rol', 'documento', 'nombres', 'apellidos');

        // Nombre de usuario único
        $chk = $pdo->prepare('SELECT id_usuario FROM usuario WHERE nombre_usuario = ?');
        $chk->execute([$d['nombre']]);
        if ($chk->fetch()) responder('Ya existe un usuario con ese nombre.', false, 409);

        // Contraseña con bcrypt
        $hash = password_hash(trim($d['password']), PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO usuario
               (nombre_usuario, contrasena, documento_identidad, nombres, apellidos,
                telefono, correo_institucional, rol, estado)
             VALUES (?,?,?,?,?,?,?,?,1)'
        );
        $stmt->execute([
            trim($d['nombre']),
            $hash,
            trim($d['documento']),
            trim($d['nombres']),
            trim($d['apellidos']),
            trim($d['telefono'] ?? ''),
            trim($d['correo']   ?? ''),
            $d['rol'],
        ]);

        responder(['id' => (int)$pdo->lastInsertId(), 'mensaje' => 'Usuario registrado correctamente.'], true, 201);
    }

    // ── PUT — Actualizar ────────────────────────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $d = leerJSON();

        $chk = $pdo->prepare('SELECT id_usuario FROM usuario WHERE id_usuario = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) responder('Usuario no encontrado.', false, 404);

        $mapa = [
            'nombre'    => 'nombre_usuario = ?',
            'documento' => 'documento_identidad = ?',
            'nombres'   => 'nombres = ?',
            'apellidos' => 'apellidos = ?',
            'telefono'  => 'telefono = ?',
            'correo'    => 'correo_institucional = ?',
            'rol'       => 'rol = ?',
        ];

        $campos = []; $valores = [];
        foreach ($mapa as $key => $sql) {
            if (array_key_exists($key, $d)) {
                $campos[]  = $sql;
                $valores[] = trim((string)$d[$key]);
            }
        }
        if (!empty($d['password'])) {
            $campos[]  = 'contrasena = ?';
            $valores[] = password_hash(trim($d['password']), PASSWORD_BCRYPT);
        }
        if (isset($d['activo'])) {
            $campos[]  = 'estado = ?';
            $valores[] = (int)(bool)$d['activo'];
        }

        if (empty($campos)) responder('No se enviaron campos para actualizar.', false, 422);

        $valores[] = $id;
        $pdo->prepare('UPDATE usuario SET ' . implode(', ', $campos) . ' WHERE id_usuario = ?')
            ->execute($valores);

        responder(['mensaje' => 'Usuario actualizado correctamente.']);
    }

    // ── DELETE — Desactivar (baja lógica) ───────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta el parámetro id.', false, 400);
        $stmt = $pdo->prepare('UPDATE usuario SET estado = 0 WHERE id_usuario = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) responder('Usuario no encontrado.', false, 404);
        responder(['mensaje' => 'Usuario desactivado correctamente.']);
    }

    responder('Método no soportado.', false, 405);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}