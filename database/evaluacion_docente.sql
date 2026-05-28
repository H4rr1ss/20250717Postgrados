SET FOREIGN_KEY_CHECKS = 0;

-- ========================================================
-- Tablas del Módulo Evaluación Docente
-- ========================================================

DROP TABLE IF EXISTS `evaluacion_respuesta_detalle`;
DROP TABLE IF EXISTS `evaluacion_respuesta`;
DROP TABLE IF EXISTS `evaluacion_pregunta`;
DROP TABLE IF EXISTS `evaluacion_seccion`;

CREATE TABLE `evaluacion_seccion` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(200) NOT NULL,
  `orden` INT(11) NOT NULL DEFAULT 0,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_activa_orden` (`activa`,`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `evaluacion_pregunta` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_seccion` INT(11) NOT NULL,
  `texto` TEXT NOT NULL,
  `tipo` ENUM('escala10','boolean','texto') NOT NULL DEFAULT 'escala10',
  `orden` INT(11) NOT NULL DEFAULT 0,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_pregunta_seccion` (`id_seccion`),
  KEY `idx_activa_orden` (`activa`,`orden`),
  CONSTRAINT `fk_pregunta_seccion` FOREIGN KEY (`id_seccion`) REFERENCES `evaluacion_seccion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `evaluacion_respuesta` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cod_horario` INT(11) NOT NULL,
  `cod_usuario_estudiante` INT(11) NOT NULL,
  `fecha_evaluacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_evaluacion` (`cod_horario`,`cod_usuario_estudiante`),
  KEY `fk_evaluacion_horario` (`cod_horario`),
  KEY `fk_evaluacion_usuario` (`cod_usuario_estudiante`),
  CONSTRAINT `fk_evaluacion_horario` FOREIGN KEY (`cod_horario`) REFERENCES `horario` (`cod_horario`),
  CONSTRAINT `fk_evaluacion_usuario` FOREIGN KEY (`cod_usuario_estudiante`) REFERENCES `usuario` (`cod_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `evaluacion_respuesta_detalle` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_evaluacion_respuesta` INT(11) NOT NULL,
  `id_pregunta` INT(11) NOT NULL,
  `respuesta` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_detalle_respuesta` (`id_evaluacion_respuesta`),
  KEY `fk_detalle_pregunta` (`id_pregunta`),
  CONSTRAINT `fk_detalle_respuesta` FOREIGN KEY (`id_evaluacion_respuesta`) REFERENCES `evaluacion_respuesta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_pregunta` FOREIGN KEY (`id_pregunta`) REFERENCES `evaluacion_pregunta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================================
-- Seed inicial: secciones y preguntas
-- ========================================================

INSERT INTO `evaluacion_seccion` (`nombre`, `orden`, `activa`) VALUES
('Desempeño Académico', 1, 1),
('Comunicación y Recursos', 2, 1),
('Comentarios Generales', 3, 1);

INSERT INTO `evaluacion_pregunta` (`id_seccion`, `texto`, `tipo`, `orden`, `activa`) VALUES
(1, '¿El Docente demostró dominio del tema de contenidos de clase según el programa de clase?', 'escala10', 1, 1),
(1, '¿El docente durante clases aplicó contenidos programáticos de clase a la problemática de la sociedad guatemalteca (ejemplos, casos, etc)?', 'escala10', 2, 1),
(2, '¿Considera que la comunicación entre profesor y estudiantes fue adecuada durante el curso?', 'escala10', 1, 1),
(2, '¿El docente compartió con estudiantes archivos de documentos relevantes y actualizados al curso?', 'boolean', 2, 1),
(3, 'Comentario: ¿Qué aspectos considera que son recomendables al docente para mejorar los aprendizajes en curso?', 'texto', 1, 1);

-- ========================================================
-- Acciones del módulo para el control de acceso
-- ========================================================

INSERT INTO `accion` (`cod_accion`, `nombre`) VALUES
(140, 'Ver evaluación docente (pendientes)'),
(141, 'Ver formulario de evaluación docente'),
(142, 'Guardar evaluación docente'),
(144, 'Ver confirmación de evaluación docente'),
(145, 'Ver reporte de evaluación docente'),
(146, 'Descargar reporte de evaluación docente');

SET FOREIGN_KEY_CHECKS = 1;
