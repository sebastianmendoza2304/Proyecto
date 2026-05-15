<?php
// ============================================================
// cleanup_caso.php
// Abre este archivo en el navegador UNA SOLA VEZ:
// http://localhost/Proyecto-main/cleanup_caso.php
// Luego BÓRRALO del servidor por seguridad.
// ============================================================
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo '<style>body{font-family:Arial;max-width:800px;margin:40px auto;padding:20px;}
.ok{color:#2e7d32;background:#e8f5e9;padding:10px;border-radius:6px;margin:8px 0;}
.err{color:#c62828;background:#ffebee;padding:10px;border-radius:6px;margin:8px 0;}
.info{color:#01579b;background:#e1f5fe;padding:10px;border-radius:6px;margin:8px 0;}
h2{color:#b71c1c;}</style>';
echo '<h2>🔧 Limpieza tabla <code>caso</code> — Sistema de Alertas</h2>';

try {
    $pdo = conectar();
    $pasos = [];

    // ── PASO 1: Buscar FKs que apuntan a "caso" desde reporte_riesgo
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME, COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'reporte_riesgo'
          AND REFERENCED_TABLE_NAME = 'caso'
    ");
    $fks = $stmt->fetchAll();

    if ($fks) {
        foreach ($fks as $fk) {
            $fkName  = $fk['CONSTRAINT_NAME'];
            $colName = $fk['COLUMN_NAME'];
            echo "<div class='info'>🔍 Encontrada FK: <strong>{$fkName}</strong> (columna: {$colName})</div>";

            // Eliminar el FK constraint
            $pdo->exec("ALTER TABLE reporte_riesgo DROP FOREIGN KEY `{$fkName}`");
            echo "<div class='ok'>✅ Foreign key <strong>{$fkName}</strong> eliminada.</div>";

            // Eliminar la columna
            // Verificar primero si la columna existe
            $colCheck = $pdo->query("
                SELECT COLUMN_NAME FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'reporte_riesgo'
                  AND COLUMN_NAME = '{$colName}'
            ")->fetch();

            if ($colCheck) {
                $pdo->exec("ALTER TABLE reporte_riesgo DROP COLUMN `{$colName}`");
                echo "<div class='ok'>✅ Columna <strong>{$colName}</strong> eliminada de reporte_riesgo.</div>";
            }
        }
    } else {
        // Puede que el FK ya no exista pero la columna sí
        $colCheck = $pdo->query("
            SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'reporte_riesgo'
              AND COLUMN_NAME = 'caso'
        ")->fetch();

        if ($colCheck) {
            $pdo->exec("ALTER TABLE reporte_riesgo DROP COLUMN `caso`");
            echo "<div class='ok'>✅ Columna <strong>caso</strong> eliminada (sin FK asociada).</div>";
        } else {
            echo "<div class='info'>ℹ️ La columna <strong>caso</strong> ya no existe en reporte_riesgo.</div>";
        }
    }

    // ── PASO 2: Eliminar tabla caso
    $tablaCheck = $pdo->query("
        SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caso'
    ")->fetch();

    if ($tablaCheck) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP TABLE caso");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "<div class='ok'>✅ Tabla <strong>caso</strong> eliminada correctamente.</div>";
    } else {
        echo "<div class='info'>ℹ️ La tabla <strong>caso</strong> ya no existe.</div>";
    }

    // ── PASO 3: Recrear la vista v_reportes sin la columna caso
    $pdo->exec("
        CREATE OR REPLACE VIEW v_reportes AS
        SELECT
          r.id_reporte                              AS id,
          CONCAT(e.nombres,' ',e.apellidos)         AS estudiante,
          e.documento_identidad                     AS documento,
          e.carrera_cursada                         AS carrera,
          CONCAT(u.nombres,' ',u.apellidos)         AS docente,
          m.nombre_materia                          AS materia,
          mo.nombre_motivo                          AS motivo,
          r.observaciones,
          r.estado,
          r.remitido_a,
          r.motivo_remision,
          DATE_FORMAT(r.fecha_registro, '%d/%m/%Y') AS fecha_formateada,
          r.fecha_registro,
          r.hora_registro,
          r.id_estudiante,
          r.id_docente,
          r.id_materia,
          r.id_motivo
        FROM reporte_riesgo r
        JOIN estudiante e  ON r.id_estudiante = e.id_estudiante
        JOIN usuario    u  ON r.id_docente    = u.id_usuario
        JOIN materia    m  ON r.id_materia    = m.id_materia
        JOIN motivo     mo ON r.id_motivo     = mo.id_motivo
    ");
    echo "<div class='ok'>✅ Vista <strong>v_reportes</strong> recreada sin columna <em>caso</em>.</div>";

    // ── Verificación final
    $total = $pdo->query("SELECT COUNT(*) AS n FROM reporte_riesgo")->fetch()['n'];
    echo "<div class='ok'>✅ Todo listo. Reportes en BD: <strong>{$total}</strong></div>";
    echo "<hr><p style='color:#555'>⚠️ <strong>Borra este archivo</strong> del servidor ahora que terminó:
          <code>C:\\xampp\\htdocs\\Proyecto-main\\cleanup_caso.php</code></p>";

} catch (Throwable $e) {
    echo "<div class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'>💡 Si el error menciona un nombre de FK, dímelo y lo soluciono.</div>";
}