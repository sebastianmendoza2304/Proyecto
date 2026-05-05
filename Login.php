<?php
// ============================================================
// login.php — Autenticación usando tabla "usuario" de BD sistema
// POST /login.php  →  { nombre_usuario, password }
// ============================================================

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder('Solo se admite POST.', false, 405);

$d = leerJSON();
requerir($d, 'nombre', 'password');

$paneles = [
    'administrador'      => 'administrador.html',
    'docente'            => 'Docentes.html',
    'bienestar'          => 'Bienestar.html',
    'director_bienestar' => 'director_bienestar.html',
    'decanatura'         => 'Decanatura.html',
    'director_programa'  => 'director_programa.html',
];

try {
    $pdo = conectar();

    // Buscar usuario activo por nombre_usuario
    $stmt = $pdo->prepare(
        'SELECT id_usuario, nombre_usuario, contrasena, nombres, apellidos, correo_institucional, rol
         FROM usuario
         WHERE nombre_usuario = ? AND estado = 1'
    );
    $stmt->execute([trim($d['nombre'])]);
    $usuario = $stmt->fetch();

    if (!$usuario) responder('Usuario o contraseña incorrectos.', false, 401);

    // Verificar contraseña (soporta bcrypt Y texto plano para datos de prueba)
    $ok = password_verify($d['password'], $usuario['contrasena'])
       || $d['password'] === $usuario['contrasena'];

    if (!$ok) responder('Usuario o contraseña incorrectos.', false, 401);

    responder([
        'id'     => $usuario['id_usuario'],
        'nombre' => $usuario['nombre_usuario'],
        'correo' => $usuario['correo_institucional'],
        'rol'    => $usuario['rol'],
        'panel'  => $paneles[$usuario['rol']] ?? 'Index.html',
    ]);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}