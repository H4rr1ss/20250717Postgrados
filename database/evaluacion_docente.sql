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
('Técnico Administrativo', 1, 1),
('Didáctico Andrológico', 2, 1),
('Profesional', 3, 1);

INSERT INTO `evaluacion_pregunta` (`id_seccion`, `texto`, `tipo`, `orden`, `activa`) VALUES
(1, '¿El docente entregó programa de clase en la primera sesión de clase?', 'boolean', 1, 1),
(1, '¿Se cumplió con el horario de clase en todas las sesiones que corresponden al curso?', 'boolean', 2, 1),
(1, '¿Se cumplió con lo planificado en programa de clase?', 'boolean', 3, 1),
(1, '¿Docente conoce y aplica normativas y regulaciones universitarias durante el desarrollo del programa del curso?', 'boolean', 4, 1),
(1, '¿Docente promueve en contenidos del curso la aplicación de principios éticos y normas jurídicas legislativas nacionales?', 'boolean', 5, 1),

(2, '¿Fue oportuna, veraz, útil y clara la retroalimentación de docente a los resultados de evaluación de ejercicios, trabajos y exámenes de estudiantes durante el curso?', 'boolean', 6, 1),
(2, '¿Docente demuestra vocación, interés y actitud de compartir conocimientos e incrementar aprendizajes de estudiantes?', 'boolean', 7, 1),
(2, '¿Considera que el docente estimula la participación de estudiantes en clase?', 'escala10', 8, 1),
(2, '¿Considera que fue adecuado al curso el uso de recursos audiovisuales, técnicas didácticas, herramientas de la enseñanza virtual?', 'escala10', 9, 1),
(2, '¿Considera que se alcanzo objetivos y consolidación de aprendizajes fundamentales de acuerdo al programa de clase?', 'escala10', 10, 1),
(2, '¿Opina usted que la evaluación y retroalimentación fue oportuna y con carácter orientadora respecto contenidos de programa de clase?', 'escala10', 11, 1),
(2, '¿El docente estimulo en los estudiantes su interés por aprendizajes de contenidos de clase?', 'escala10', 12, 1),
(2, '¿Como le parece la metodología de enseñanza aplicadas por el docente en el desarrollo del curso?', 'escala10', 13, 1),
(2, '¿Como considera el aporte del docente en su formación de maestría a nivel de educación universitaria?', 'escala10', 14, 1),

(3, 'Comentario: ¿Qué aspectos considera que son recomendables al docente para mejorar los aprendizajes en curso?', 'texto', 15, 1),
(3, '¿El Docente demostró dominio del tema de contenidos de clase según el programa de clase?', 'escala10', 16, 1),
(3, '¿Considera que la comunicación entre profesor y estudiantes fue adecuada durante el curso?', 'escala10', 17, 1),
(3, '¿Docente durante clases aplico contenidos programáticos de clase a la problemática de la sociedad guatemalteca (ejemplos, casos, etc)?', 'escala10', 18, 1),
(3, '¿El docente compartió con estudiantes archivos de documentos relevantes y actualizados al curso?', 'boolean', 19, 1);

SET FOREIGN_KEY_CHECKS = 1;
