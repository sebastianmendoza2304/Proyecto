<?php
require_once 'config.php';
$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conectar();

    $pdo->exec("CREATE TABLE IF NOT EXISTS periodo_academico (
        id_periodo   INT AUTO_INCREMENT PRIMARY KEY,
        nombre       VARCHAR(20)  NOT NULL UNIQUE COMMENT 'Ej: 2026-1',
        descripcion  VARCHAR(150),
        fecha_inicio DATE,
        fecha_fin    DATE,
        activo       TINYINT(1) NOT NULL DEFAULT 0,
        creado_en    DATETIME   DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    if ($metodo === 'GET') {
        if (!empty($_GET['activo'])) {
            $r = $pdo->query("SELECT id_periodo AS id, nombre, descripcion, fecha_inicio, fecha_fin, activo FROM periodo_academico WHERE activo=1 LIMIT 1")->fetch();
            responder($r ?: null);
        }
        if ($id) {
            $stmt = $pdo->prepare("SELECT id_periodo AS id, nombre, descripcion, fecha_inicio, fecha_fin, activo FROM periodo_academico WHERE id_periodo=?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) responder('Período no encontrado.', false, 404);
            try {
                $c = $pdo->prepare("SELECT COUNT(*) AS n FROM reporte_riesgo WHERE id_periodo=?");
                $c->execute([$id]);
                $p['total_reportes'] = (int)$c->fetch()['n'];
            } catch(Exception $e) { $p['total_reportes'] = 0; }
            responder($p);
        }
        $stmt = $pdo->query("SELECT id_periodo AS id, nombre, descripcion, fecha_inicio, fecha_fin, activo,
            (SELECT COUNT(*) FROM reporte_riesgo r WHERE r.id_periodo = p.id_periodo) AS total_reportes
            FROM periodo_academico p ORDER BY id_periodo DESC");
        responder($stmt->fetchAll());
    }

    if ($metodo === 'POST') {
        $d = leerJSON();
        requerir($d, 'nombre');
        $chk = $pdo->prepare("SELECT id_periodo FROM periodo_academico WHERE nombre=?");
        $chk->execute([trim($d['nombre'])]);
        if ($chk->fetch()) responder('Ya existe un período con ese nombre.', false, 409);
        $stmt = $pdo->prepare("INSERT INTO periodo_academico (nombre,descripcion,fecha_inicio,fecha_fin,activo) VALUES(?,?,?,?,?)");
        $activo = isset($d['activo']) && $d['activo'] ? 1 : 0;
        $stmt->execute([trim($d['nombre']), trim($d['descripcion']??''), $d['fecha_inicio']?:null, $d['fecha_fin']?:null, $activo]);
        $newId = (int)$pdo->lastInsertId();
        if ($activo) $pdo->prepare("UPDATE periodo_academico SET activo=0 WHERE id_periodo!=?")->execute([$newId]);
        responder(['id'=>$newId,'mensaje'=>'Período creado correctamente.'], true, 201);
    }

    if ($metodo === 'PUT') {
        if (!$id) responder('Falta id.', false, 400);
        $d = leerJSON();
        if (!empty($d['activo'])) {
            $pdo->exec("UPDATE periodo_academico SET activo=0");
            $pdo->prepare("UPDATE periodo_academico SET activo=1 WHERE id_periodo=?")->execute([$id]);
            responder(['mensaje'=>'Período activado correctamente.']);
        }
        $campos=[]; $vals=[];
        foreach(['nombre','descripcion','fecha_inicio','fecha_fin'] as $f) {
            if(array_key_exists($f,$d)){ $campos[]="$f=?"; $vals[]=$d[$f]?:null; }
        }
        if(empty($campos)) responder('Sin campos.', false, 422);
        $vals[]=$id;
        $pdo->prepare("UPDATE periodo_academico SET ".implode(',',$campos)." WHERE id_periodo=?")->execute($vals);
        responder(['mensaje'=>'Período actualizado.']);
    }

    if ($metodo === 'DELETE') {
        if (!$id) responder('Falta id.', false, 400);
        try {
            $c=$pdo->prepare("SELECT COUNT(*) AS n FROM reporte_riesgo WHERE id_periodo=?");
            $c->execute([$id]);
            if((int)$c->fetch()['n']>0) responder('No se puede eliminar: tiene reportes asociados.', false, 409);
        } catch(Exception $e){}
        $pdo->prepare("DELETE FROM periodo_academico WHERE id_periodo=?")->execute([$id]);
        responder(['mensaje'=>'Período eliminado.']);
    }

    responder('Método no soportado.', false, 405);
} catch(PDOException $e) {
    responder('Error de base de datos: '.$e->getMessage(), false, 500);
}