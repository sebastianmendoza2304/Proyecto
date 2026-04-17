-- ============================================================
--  BASE DE DATOS: Sistema de Alertas Académicas - Unilibre
--  Compatible con XAMPP / MySQL 5.7+
--  Importar desde phpMyAdmin o por consola:
--  mysql -u root -p < alertas_academicas.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS alertas_academicas
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE alertas_academicas;

-- ------------------------------------------------------------
-- TABLA: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(80)  NOT NULL,
  password    VARCHAR(255) NOT NULL,   -- guardar hash SHA-256 o bcrypt
  correo      VARCHAR(120),
  rol         ENUM('administrador','docente','bienestar',
                   'director_bienestar','decanatura') NOT NULL,
  activo      TINYINT(1) DEFAULT 1,
  creado_en   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Usuarios de prueba (contraseñas en texto plano solo para desarrollo)
INSERT INTO usuarios (nombre, password, correo, rol) VALUES
  ('admin',              'admin123',  'admin@unilibre.edu.co',       'administrador'),
  ('docente1',           '1234',      'docente1@unilibre.edu.co',    'docente'),
  ('bienestar1',         'xyz',       'bienestar1@unilibre.edu.co',  'bienestar'),
  ('bienestar_director', '12345',     'dir.bienestar@unilibre.edu.co','director_bienestar'),
  ('decano1',            'abcd',      'decano1@unilibre.edu.co',     'decanatura');

-- ------------------------------------------------------------
-- TABLA: estudiantes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estudiantes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  documento   VARCHAR(20)  NOT NULL UNIQUE,
  nombres     VARCHAR(80)  NOT NULL,
  apellidos   VARCHAR(80)  NOT NULL,
  carrera     VARCHAR(100) NOT NULL,
  telefono    VARCHAR(20),
  correo      VARCHAR(120),
  periodo     VARCHAR(20),
  estado      ENUM('Activo','Inactivo','Retirado') DEFAULT 'Activo',
  creado_en   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Estudiantes de prueba
INSERT INTO estudiantes (documento, nombres, apellidos, carrera, telefono, correo, periodo) VALUES
  ('1001234567', 'Ana',    'García',    'Ing. de Sistemas', '3001234567', 'ana.garcia@unilibre.edu.co',  '2025-1'),
  ('1007654321', 'Carlos', 'Martínez',  'Ing. Civil',       '3117654321', 'carlos.m@unilibre.edu.co',    '2025-1'),
  ('1009876543', 'Laura',  'Rodríguez', 'Ing. Industrial',  '3209876543', 'laura.r@unilibre.edu.co',     '2025-1');

-- ------------------------------------------------------------
-- TABLA: reportes
-- Creados por docentes, gestionados por bienestar/director
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reportes (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  estudiante_id   INT          NOT NULL,
  docente_id      INT          NOT NULL,
  materia         VARCHAR(100) NOT NULL,
  motivo          ENUM('Bajo rendimiento','Inasistencia','Seguimiento') NOT NULL,
  observaciones   TEXT,
  estado          ENUM('Reportado','En revisión','En seguimiento',
                       'Remitido','Cerrado') DEFAULT 'Reportado',
  remitido_a      VARCHAR(100),
  motivo_remision TEXT,
  fecha           DATETIME DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id),
  FOREIGN KEY (docente_id)    REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Reportes de prueba
INSERT INTO reportes (estudiante_id, docente_id, materia, motivo, observaciones, estado) VALUES
  (1, 2, 'Algoritmos',      'Bajo rendimiento', 'Tres parciales por debajo de 3.0', 'Reportado'),
  (2, 2, 'Cálculo I',       'Inasistencia',     'Más del 20% de inasistencias',     'En revisión'),
  (3, 2, 'Física I',        'Seguimiento',      'Solicita apoyo adicional',          'En seguimiento');

-- ------------------------------------------------------------
-- TABLA: seguimientos
-- Registrados por el profesional de bienestar
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seguimientos (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  reporte_id      INT  NOT NULL,
  profesional_id  INT  NOT NULL,
  intervencion    VARCHAR(150) NOT NULL,
  observaciones   TEXT,
  recomendaciones TEXT,
  fecha           DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reporte_id)     REFERENCES reportes(id),
  FOREIGN KEY (profesional_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VISTAS útiles para consultas frecuentes
-- ------------------------------------------------------------

-- Vista: reporte completo con nombres
CREATE OR REPLACE VIEW v_reportes AS
SELECT
  r.id,
  CONCAT(e.nombres, ' ', e.apellidos) AS estudiante,
  e.documento,
  e.carrera,
  u.nombre                            AS docente,
  r.materia,
  r.motivo,
  r.observaciones,
  r.estado,
  r.remitido_a,
  r.motivo_remision,
  r.fecha,
  r.actualizado_en
FROM reportes r
JOIN estudiantes e ON r.estudiante_id = e.id
JOIN usuarios   u ON r.docente_id    = u.id;

-- Vista: seguimientos con detalle
CREATE OR REPLACE VIEW v_seguimientos AS
SELECT
  s.id,
  s.reporte_id,
  CONCAT(e.nombres, ' ', e.apellidos) AS estudiante,
  r.materia,
  r.estado,
  u.nombre       AS profesional,
  s.intervencion,
  s.observaciones,
  s.recomendaciones,
  s.fecha
FROM seguimientos s
JOIN reportes    r ON s.reporte_id     = r.id
JOIN estudiantes e ON r.estudiante_id  = e.id
JOIN usuarios    u ON s.profesional_id = u.id;
