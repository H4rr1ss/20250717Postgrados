-- ============================================
-- MÓDULO: Formulario de Admisión (Estructura v3 + CandidateForm alignment)
-- Script centralizado: crea tablas, índices e inserta campos globales.
-- Ejecutar en entornos nuevos o para reconstruir el módulo desde cero.
-- ============================================

-- ============================================
-- CREAR TABLAS
-- ============================================

-- ------------------------------------------------------------------
-- SECCIONES ACTIVAS DEL FORMULARIO (columna `seccion` en campo_formulario)
-- ------------------------------------------------------------------
-- Valores permitidos actualmente:
--   - 'personal'   => Datos personales del aspirante
--   - 'contacto'   => Información de contacto
--   - 'admin'      => Campos administrativos (visibles solo para admins)
--   - 'laboral'    => Información laboral
--   - 'academico'  => Información académica
--   - 'adicional'  => Campos adicionales / motivación
--
-- ⚠️ REGLA IMPORTANTE:
--    Si se agrega una NUEVA sección en este script, se DEBE agregar
--    también en el FRONTEND (vistas .phtml / JS) para que se renderice
--    correctamente en el formulario público y en el panel admin.
--    De lo contrario, los campos de la nueva sección quedarán ocultos
--    o sin estilo en la interfaz.
-- ------------------------------------------------------------------

CREATE TABLE formulario_admision (
    id_formulario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    fecha_creacion datetime DEFAULT current_timestamp(),
    fecha_inicio_admision DATETIME NOT NULL,
    fecha_fin_admision DATETIME NOT NULL,
    activo tinyint(1) DEFAULT 1,
    creado_por INT,
    FOREIGN KEY (creado_por) REFERENCES usuario(cod_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_formulario_activo ON formulario_admision(activo, fecha_creacion);

CREATE TABLE campo_formulario (
    id_campo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_campo VARCHAR(100) NOT NULL UNIQUE,
    etiqueta VARCHAR(200) NOT NULL,
    tipo_campo ENUM('texto','email','telefono','select','textarea','fecha','archivo','boolean','time','multicheckbox') NOT NULL,
    opciones TEXT,
    requerido BOOLEAN DEFAULT FALSE,
    orden_campo INT DEFAULT 0,
    seccion VARCHAR(20) DEFAULT 'adicional'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_campo_orden ON campo_formulario(orden_campo);

CREATE TABLE respuesta_aspirante (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_formulario INT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_formulario) REFERENCES formulario_admision(id_formulario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_respuesta_campo_respuesta ON respuesta_campo(id_respuesta, id_campo);

-- ============================================
-- INSERTAR CAMPOS GLOBALES
-- ============================================

-- Campos obligatorios: se necesitan para registrar automáticamente al aspirante como estudiante
INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, seccion) VALUES
('nombres', 'Nombres', 'texto', NULL, 1, 1, 'personal'),
('apellidos', 'Apellidos', 'texto', NULL, 1, 2, 'personal'),
('cui', 'DPI / CUI', 'texto', NULL, 0, 3, 'personal'),
('correo_electronico', 'Correo Electrónico', 'email', NULL, 1, 4, 'contacto'),
('telefono', 'Teléfono / Celular', 'telefono', NULL, 1, 5, 'contacto'),
('fecha_nacimiento', 'Fecha de Nacimiento', 'fecha', NULL, 1, 6, 'personal'),
('sexo', 'Sexo', 'select', 'H|M', 1, 7, 'personal'),
('pasaporte', 'Pasaporte', 'texto', NULL, 0, 8, 'personal'),
('grado_academico_posee', 'Grado académico que posee', 'texto', NULL, 1, 9, 'personal'),
('maestria_solicitada', 'Maestría a la cual solicita ingresar', 'select', 'Maestría en Diseño Arquitectónico|Diseño, planificación y manejo Ambiental|Restauración de Monumentos|Gestión para la reducción del riesgo|Desarrollo Urbano y territorio|Mercadeo para el diseño|Patrimonio Cultural para el desarrollo énfasis en Gestión y Conservación|Gerencia de Proyectos arquitectónicos|Enseñanza virtual de la Arquitectura y el Diseño|Diseño interactivo y digital|Especialización de Gestión de Riesgos|DOCTORADO (Con énfasis Diseño Arquitectónico, Conservación del Patrimonio Cultural, Conservación del Medio Ambiente)|Maestría Planificación y Diseño del Paisaje', 1, 10, 'personal'),
('grado_a_ingresar', 'Grado académico a ingresar', 'select', NULL, 0, 50, 'admin'),
('carrera_a_ingresar', 'Carrera a ingresar', 'select', NULL, 0, 51, 'admin'),
('trabaja_actualmente', 'Labora actualmente', 'boolean', NULL, 1, 12, 'laboral'),
('ubicacion_laboral', 'Ubicación laboral', 'texto', NULL, 0, 13, 'laboral'),
('hora_inicio', 'Hora de inicio laboral', 'time', NULL, 0, 14, 'laboral'),
('hora_fin', 'Hora de salida laboral', 'time', NULL, 0, 15, 'laboral'),
('dias_labora', 'Días en que labora', 'multicheckbox', 'lunes|martes|miercoles|jueves|viernes|sabado|domingo', 0, 16, 'laboral'),
('photo_dpi', 'Foto del DPI (archivo)', 'archivo', NULL, 1, 17, 'personal'),
('nacionalidad', 'País de nacionalidad', 'select', NULL, 1, 18, 'contacto');

-- Campos de información académica del aspirante (opcionales)
INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, seccion) VALUES
('estudios_universitarios', 'Estudios universitarios, institución', 'texto', NULL, 0, 20, 'academico'),
('campo_estudio', 'Campo(s) de estudio o profesión', 'texto', NULL, 0, 21, 'academico'),
('titulos_obtenidos', 'Título(s) obtenido(s)', 'texto', NULL, 0, 22, 'academico'),
('años_carrera', 'Indicar los años de inicio y término de la carrera universitaria', 'texto', NULL, 0, 23, 'academico'),
('adjunto_titulos', 'Adjuntar fotocopia de títulos obtenidos o certificación general de cursos', 'archivo', NULL, 0, 24, 'academico');

-- Campos opcionales restantes
INSERT INTO campo_formulario (nombre_campo, etiqueta, tipo_campo, opciones, requerido, orden_campo, seccion) VALUES
('estado_civil', 'Estado Civil', 'select', 'Soltero/a|Casado/a|Divorciado/a|Viudo/a', 0, 30, 'personal'),
('direccion', 'Dirección', 'textarea', NULL, 0, 31, 'contacto'),
('municipio', 'Municipio', 'texto', NULL, 0, 32, 'contacto'),
('departamento', 'Departamento', 'texto', NULL, 0, 33, 'contacto'),
('motivo_estudio', 'Motivación para estudiar el postgrado', 'textarea', NULL, 0, 34, 'adicional');

