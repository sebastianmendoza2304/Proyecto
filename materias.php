<?php
// ============================================================
// materias.php — API para materias y asignación docente-materia
// GET  materias.php                    → todas las materias
// GET  materias.php?docente_id=X       → materias del docente
// POST materias.php (admin)            → asignar materia a docente
// DELETE materias.php?id=X&docente_id=Y → desasignar
// ============================================================
require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])         ? (int)$_GET['id']         : null;
$doc_id = isset($_GET['docente_id']) ? (int)$_GET['docente_id'] : null;

try {
    $pdo = conectar();

    // Crear tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS docente_materia (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        id_docente  INT NOT NULL,
        id_materia  INT NOT NULL,
        UNIQUE KEY uq_dm (id_docente, id_materia),
        FOREIGN KEY (id_docente) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
        FOREIGN KEY (id_materia) REFERENCES materia(id_materia) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    if ($metodo === 'GET') {
        if ($doc_id) {
            // Materias asignadas a un docente específico
            $stmt = $pdo->prepare(
                "SELECT m.id_materia AS id, m.nombre_materia AS nombre, m.codigo_materia AS codigo
                 FROM materia m
                 INNER JOIN docente_materia dm ON dm.id_materia = m.id_materia
                 WHERE dm.id_docente = ?
                 ORDER BY m.nombre_materia"
            );
            $stmt->execute([$doc_id]);
            responder($stmt->fetchAll());
        }
        // Todas las materias
        $stmt = $pdo->query(
            "SELECT id_materia AS id, nombre_materia AS nombre, codigo_materia AS codigo,
                    departamento, creditos
             FROM materia ORDER BY nombre_materia"
        );
        responder($stmt->fetchAll());
    }

    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'docente_id', 'materia_id');
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO docente_materia (id_docente, id_materia) VALUES (?,?)"
        );
        $stmt->execute([(int)$d['docente_id'], (int)$d['materia_id']]);
        responder(['mensaje' => 'Materia asignada correctamente.'], true, 201);
    }

    if ($metodo === 'DELETE') {
        if (!$id || !$doc_id) responder('Faltan parámetros id y docente_id.', false, 400);
        $stmt = $pdo->prepare(
            "DELETE FROM docente_materia WHERE id_materia = ? AND id_docente = ?"
        );
        $stmt->execute([$id, $doc_id]);
        responder(['mensaje' => 'Materia desasignada correctamente.']);
    }

    responder('Método no soportado.', false, 405);
} catch (PDOException $e) {
    responder('Error de base de datos: ' . $e->getMessage(), false, 500);
}