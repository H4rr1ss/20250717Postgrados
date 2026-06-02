-- ============================================
-- MIGRACION: Formulario de Admision v2 -> v3 (Campos Globales)
-- Ejecutar este script si ya tenias las tablas creadas con la estructura anterior
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Eliminar tablas antiguas
DROP TABLE IF EXISTS respuesta_campo;
DROP TABLE IF EXISTS respuesta_aspirante;
DROP TABLE IF EXISTS campo_formulario;
DROP TABLE IF EXISTS formulario_admision;
DROP TABLE IF EXISTS aspirante;
DROP PROCEDURE IF EXISTS CrearCamposPredefinidos;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- CREAR NUEVAS TABLAS (Estructura v3)
-- ============================================

CREATE TABLE formulario_admision (
    id_formulario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    fecha_creacion datetime DEFAULT current_timestamp(),
    fecha_inicio_admision DATETIME NOT NULL,
    fecha_fin_admision DATETIME NOT NULL,
    activo tinyint(1) DEFAULT 1,
    creado_por INT,
    FOREIGN KEY (creado_por) REFERENCES usuario(cod_usuario)
);

CREATE INDEX idx_formulario_activo ON formulario_admision(activo, fecha_creacion);

-- Tabla global de campos predefinidos (compartidos por todos los formularios)
CREATE TABLE campo_formulario (
    id_campo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_campo VARCHAR(100) NOT NULL UNIQUE,
    etiqueta VARCHAR(200) NOT NULL,
    tipo_campo ENUM('texto', 'email', 'telefono', 'select', 'textarea', 'fecha', 'archivo', 'boolean') NOT NULL,
    opciones TEXT,
    requerido BOOLEAN DEFAULT FALSE,
    orden_campo INT DEFAULT 0
);

CREATE INDEX idx_campo_orden ON campo_formulario(orden_campo);

CREATE TABLE respuesta_aspirante (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_formulario INT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_formulario) REFERENCES formulario_admision(id_formulario) ON DELETE CASCADE
);

CREATE INDEX idx_respuesta_formulario ON respuesta_aspirante(id_formulario, fecha_envio);

CREATE TABLE respuesta_campo (
    id_respuesta_campo INT AUTO_INCREMENT PRIMARY KEY,
    id_respuesta INT NOT NULL,
    id_campo INT NOT NULL,
    valor_respuesta TEXT,
    archivo_adjunto VARCHAR(255),
    FOREIGN KEY (id_respuesta) REFERENCES respuesta_aspirante(id_respuesta) ON DELETE CASCADE,
    FOREIGN KEY (id_campo) REFERENCES campo_formulario(id_campo) ON DELETE RESTRICT,
    UNIQUE KEY unique_respuesta_campo (id_respuesta, id_campo)
);

CREATE INDEX idx_respuesta_campo_respuesta ON respuesta_campo(id_respuesta, id_campo);

-- ============================================
-- INSERTAR CAMPOS GLOBALES (Una sola vez)
-- ============================================

INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo) VALUES
('nombres', 'Nombres', 'texto', NULL, 1, 1),
('apellidos', 'Apellidos', 'texto', NULL, 1, 2),
('cui', 'DPI/CUI', 'texto', NULL, 1, 3),
('correo_electronico', 'Correo Electrónico', 'email', NULL, 1, 4),
('telefono', 'Teléfono', 'telefono', NULL, 1, 5),
('fecha_nacimiento', 'Fecha de Nacimiento', 'fecha', NULL, 1, 6),
('genero', 'Género', 'select', 'Masculino,Femenino', 1, 7),
('estado_civil', 'Estado Civil', 'select', 'Soltero/a,Casado/a,Divorciado/a,Viudo/a', 0, 8);

INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo) VALUES
('direccion', 'Dirección', 'textarea', NULL, 1, 9),
('municipio', 'Municipio', 'texto', NULL, 1, 10),
('departamento', 'Departamento', 'texto', NULL, 1, 11);

INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo) VALUES
('universidad_pregrado', 'Universidad de Pregrado', 'texto', NULL, 1, 12),
('carrera_pregrado', 'Carrera de Pregrado', 'texto', NULL, 1, 13),
('año_graduacion', 'Año de Graduación', 'texto', NULL, 1, 14),
('colegiado_profesional', 'Número de Colegiado Profesional', 'texto', NULL, 0, 15);

INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo) VALUES
('photo_dpi', 'Foto del DPI (archivo)', 'archivo', NULL, 1, 16),
('pasaporte', 'Número de Pasaporte', 'texto', NULL, 0, 17);

INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo) VALUES
('motivo_estudio', 'Motivación para estudiar el postgrado', 'textarea', NULL, 1, 18);

INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo) VALUES
('maestria_solicitada', 'Maestría a la cual solicitar ingresar', 'select', 'Maestría en Diseño Arquitectónico,Diseño, planificación y manejo Ambiental,Restauración de Monumentos,Gestión para la reducción del riesgo,Desarrollo Urbano y territorio,Mercadeo para el diseño,Patrimonio Cultural para el desarrollo énfasis en Gestión y Conservación,Gerencia de Proyectos arquitectónicos,Enseñanza virtual de la Arquitectura y el Diseño,Diseño interactivo y digital,Especialización de Gestión de Riesgos,DOCTORADO (Con énfasis *Diseño Arquitectónico *Conservación del Patrimonio Cultural *Conservación del Medio Ambiente),Maestría Planificación y Diseño del Paisaje', 1, 19);
