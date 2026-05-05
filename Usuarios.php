<?php
// ============================================================
// usuarios.php — CRUD de usuarios del sistema
// Sistema de Alertas por Riesgo Académico - Universidad Libre
//
// Métodos soportados:
//   GET    /usuarios.php              → listar todos
//   GET    /usuarios.php?id=5         → obtener uno por ID
//   GET    /usuarios.php?rol=docente  → filtrar por rol
//   POST   /usuarios.php              → crear usuario
//   PUT    /usuarios.php?id=5         → actualizar usuario
//   DELETE /usuarios.php?id=5         → eliminar (desactivar) usuario
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
            $stmt = $pdo->prepare(
                'SELECT id, nombre, correo, rol, activo, creado_en
                 FROM usuarios WHERE id = ?'
            );
            $stmt->execute([$id]);
            $usuario = $stmt->fetch();

            if (!$usuario) {
                responder('Usuario no encontrado.', false, 404);
            }
            responder($usuario);
        }

        // Filtrar por rol
        $rol    = $_GET['rol']    ?? null;
        $activo = $_GET['activo'] ?? null;   // "1" o "0"

        $sql    = 'SELECT id, nombre, correo, rol, activo, creado_en FROM usuarios WHERE 1=1';
        $params = [];

        if ($rol) {
            $sql .= ' AND rol = ?';
            $params[] = $rol;
        }
        if ($activo !== null) {
            $sql .= ' AND activo = ?';
            $params[] = (int)$activo;
        }

        $sql .= ' ORDER BY creado_en DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        responder($stmt->fetchAll());
    }

    // ──────────────────────────────────────────────────────────
    // POST — Crear usuario
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'POST') {
        $datos = leerJSON();

        requerir($datos, 'nombre', 'password', 'rol');

        // Verificar nombre único
        $chk = $pdo->prepare('SELECT id FROM usuarios WHERE nombre = ?');
        $chk->execute([$datos['nombre']]);
        if ($chk->fetch()) {
            responder('Ya existe un usuario con ese nombre.', false, 409);
        }

        // Roles válidos
        $rolesValidos = ['administrador', 'docente', 'bienestar', 'director_bienestar', 'decanatura'];
        if (!in_array($datos['rol'], $rolesValidos, true)) {
            responder('Rol no válido.', false, 422);
        }

        // Hash de contraseña con bcrypt
        $hash = password_hash(trim($datos['password']), PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre, password, correo, rol, activo)
             VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            trim($datos['nombre']),
            $hash,
            trim($datos['correo'] ?? ''),
            $datos['rol'],
        ]);

        responder([
            'id'      => (int)$pdo->lastInsertId(),
            'mensaje' => 'Usuario registrado correctamente.',
        ], true, 201);
    }

    // ──────────────────────────────────────────────────────────
    // PUT — Actualizar usuario
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'PUT') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        $datos = leerJSON();

        // Verificar que existe
        $chk = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            responder('Usuario no encontrado.', false, 404);
        }

        // Construir UPDATE dinámico (solo campos enviados)
        $campos  = [];
        $valores = [];

        if (!empty($datos['nombre'])) {
            $campos[]  = 'nombre = ?';
            $valores[] = trim($datos['nombre']);
        }
        if (!empty($datos['password'])) {
            $campos[]  = 'password = ?';
            $valores[] = password_hash(trim($datos['password']), PASSWORD_BCRYPT);
        }
        if (isset($datos['correo'])) {
            $campos[]  = 'correo = ?';
            $valores[] = trim($datos['correo']);
        }
        if (!empty($datos['rol'])) {
            $campos[]  = 'rol = ?';
            $valores[] = $datos['rol'];
        }
        if (isset($datos['activo'])) {
            $campos[]  = 'activo = ?';
            $valores[] = (int)(bool)$datos['activo'];
        }

        if (empty($campos)) {
            responder('No se enviaron campos para actualizar.', false, 422);
        }

        $valores[] = $id;
        $stmt = $pdo->prepare(
            'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = ?'
        );
        $stmt->execute($valores);

        responder(['mensaje' => 'Usuario actualizado correctamente.']);
    }

    // ──────────────────────────────────────────────────────────
    // DELETE — Desactivar usuario (baja lógica)
    // ──────────────────────────────────────────────────────────
    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta el parámetro id.', false, 400);

        $stmt = $pdo->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            responder('Usuario no encontrado.', false, 404);
        }
        responder(['mensaje' => 'Usuario desactivado correctamente.']);
    }

    responder('Método no soportado.', false, 405);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}