<?php
// ============================================================
// login.php — Autenticación de usuarios
// Sistema de Alertas por Riesgo Académico - Universidad Libre
//
// POST /login.php
//   Body JSON: { "nombre": "...", "password": "..." }
//   Responde:  { ok: true, data: { id, nombre, rol, token } }
// ============================================================

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder('Solo se admite POST.', false, 405);
}

$d = leerJSON();
requerir($d, 'nombre', 'password');

try {
    $pdo = conectar();

    // Buscar usuario activo por nombre
    $stmt = $pdo->prepare(
        'SELECT id, nombre, password, correo, rol
         FROM usuarios
         WHERE nombre = ? AND activo = 1'
    );
    $stmt->execute([trim($d['nombre'])]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        responder('Usuario o contraseña incorrectos.', false, 401);
    }

    // Verificar contraseña
    // Soporta tanto bcrypt (producción) como texto plano (datos de prueba)
    $passwordOk = password_verify($d['password'], $usuario['password'])
               || $d['password'] === $usuario['password']; // compatibilidad datos prueba

    if (!$passwordOk) {
        responder('Usuario o contraseña incorrectos.', false, 401);
    }

    // Mapeo de rol → archivo HTML de destino
    $paneles = [
        'administrador'      => 'administrador.html',
        'docente'            => 'Docentes.html',
        'bienestar'          => 'Bienestar.html',
        'director_bienestar' => 'director_bienestar.html',
        'decanatura'         => 'Decanatura.html',
    ];

    // Generar token de sesión simple (para uso con sesión PHP)
    session_start();
    $_SESSION['usuario_id']  = $usuario['id'];
    $_SESSION['usuario_rol'] = $usuario['rol'];
    $_SESSION['usuario_nom'] = $usuario['nombre'];

    responder([
        'id'     => $usuario['id'],
        'nombre' => $usuario['nombre'],
        'correo' => $usuario['correo'],
        'rol'    => $usuario['rol'],
        'panel'  => $paneles[$usuario['rol']] ?? 'Index.html',
    ]);

} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}