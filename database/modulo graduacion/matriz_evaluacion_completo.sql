-- ============================================================
-- Script: Matriz de Evaluación del Examen Privado
-- Fecha: 2026-06-06
-- Descripción: Definición de tablas (si no existen) + seeds.
--
-- NOTA: Las tablas base del módulo (examen_proceso, etc.)
-- deben existir previamente (ver modulo_graduacion_schema.sql).
-- Este script es idempotente.
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 1. Tablas de matriz de evaluación (CREATE IF NOT EXISTS)
-- ============================================================

CREATE TABLE IF NOT EXISTS `examen_matriz_pregunta` (
  `cod_pregunta`    smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `cod_carrera`     int(11) NOT NULL,
  `numero_orden`    tinyint(3) unsigned NOT NULL,
  `texto_pregunta`  varchar(500) NOT NULL,
  `tipo_campo`      enum('numero','texto') NOT NULL DEFAULT 'numero',
  `punteo_maximo`   varchar(20) DEFAULT '0-10',
  `activo`          tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`cod_pregunta`),
  CONSTRAINT `examen_matriz_pregunta_carrera_fk`
    FOREIGN KEY (`cod_carrera`) REFERENCES `carrera` (`cod_carrera`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `examen_matriz_evaluacion` (
  `cod_evaluacion`    int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_proceso`       int(11) unsigned NOT NULL,
  `posicion_examinador` tinyint(3) unsigned NOT NULL COMMENT '1, 2 o 3',
  `evaluado_por`      int(11) NOT NULL,
  `fecha_evaluacion`  timestamp NOT NULL DEFAULT current_timestamp(),
  `observaciones_generales` text DEFAULT NULL,
  PRIMARY KEY (`cod_evaluacion`),
  UNIQUE KEY `uq_evaluacion_proceso_examinador` (`cod_proceso`, `posicion_examinador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `examen_matriz_respuesta` (
  `cod_respuesta`   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cod_evaluacion`  int(11) unsigned NOT NULL,
  `cod_pregunta`    smallint(5) unsigned NOT NULL,
  `punteo`          decimal(4,2) DEFAULT NULL,
  `respuesta_texto` text DEFAULT NULL,
  PRIMARY KEY (`cod_respuesta`),
  CONSTRAINT `examen_matriz_respuesta_evaluacion_fk`
    FOREIGN KEY (`cod_evaluacion`) REFERENCES `examen_matriz_evaluacion` (`cod_evaluacion`),
  CONSTRAINT `examen_matriz_respuesta_pregunta_fk`
    FOREIGN KEY (`cod_pregunta`) REFERENCES `examen_matriz_pregunta` (`cod_pregunta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 2. Seeds — Preguntas por carrera (20 carreras)
-- ============================================================
-- Todas las preguntas son numéricas y suman 100 por carrera.

-- Carrera 18: Patrimonio Cultural — Conservación (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(18, 1, 'Aporte: Originalidad del tema y Estado del Arte, aporte al conocimiento y a la solución de problemas de la sociedad.', 'numero', '0-20'),
(18, 2, 'Valoración y significación del objeto de estudio: Sustentación de los valores y significación, del bien cultural inmueble objeto de estudio.', 'numero', '0-20'),
(18, 3, 'Metodología: Claridad, orden lógico, sustentación teórica, selección y uso adecuado de procedimientos y técnicas de investigación utilizados en la conservación y restauración de bienes culturales inmuebles.', 'numero', '0-20'),
(18, 4, 'Contextualización del objeto de estudio: Histórico, Legal, institucional, político, social, económico, geográfico, entre otros.', 'numero', '0-20'),
(18, 5, 'Diagnóstico: Sistematización y análisis de la información. Sustentación adecuada del diagnóstico de la situación (pasado, evolución y actual) del estado, el potencial y la problemática detectada en el objeto de estudio.', 'numero', '0-20');

-- Carrera 24: Gerencia de Proyectos Arquitectónicos (6 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(24, 1, 'Aporte: Originalidad del tema y relevancia en el campo de la gerencia de proyectos, aporte a la solución de problemas organizacionales.', 'numero', '0-15'),
(24, 2, 'Definición del problema y objetivos: Claridad en la identificación del problema, objetivos SMART y alineación con necesidades reales.', 'numero', '0-15'),
(24, 3, 'Marco teórico y estado del arte: Revisión de literatura pertinente, teorías aplicadas y relación con casos reales de proyectos.', 'numero', '0-15'),
(24, 4, 'Metodología y herramientas de gestión: Aplicación de metodologías (PMI, Agile, etc.), uso de herramientas y claridad en la planificación.', 'numero', '0-15'),
(24, 5, 'Análisis de viabilidad y riesgos: Evaluación financiera, técnica, legal y de riesgos del proyecto propuesto.', 'numero', '0-20'),
(24, 6, 'Recomendaciones para implementación: Observaciones sobre la factibilidad real y aspectos a mejorar en la propuesta.', 'numero', '0-20');

-- Carrera 13: Gestión para la Reducción del Riesgo (6 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(13, 1, 'Aporte: Originalidad del tema, relevancia jurídica y contribución al desarrollo de la gestión del riesgo.', 'numero', '0-15'),
(13, 2, 'Análisis doctrinal y normativo: Dominio de la normativa, políticas y construcción de argumentos técnicos sólidos.', 'numero', '0-15'),
(13, 3, 'Metodología de investigación: Claridad en el enfoque, uso de herramientas de análisis de riesgo y técnicas de recolección.', 'numero', '0-15'),
(13, 4, 'Análisis de vulnerabilidad y riesgo: Evaluación de amenazas, vulnerabilidad y capacidades de respuesta.', 'numero', '0-15'),
(13, 5, 'Propuesta de gestión del riesgo: Originalidad, viabilidad y sustentación de la propuesta de reducción de riesgo.', 'numero', '0-20'),
(13, 6, 'Observaciones del examinador: Aspectos a fortalecer o recomendaciones específicas sobre el trabajo.', 'numero', '0-20');

-- Carrera 22: Enseñanza Virtual de la Arquitectura y el Diseño (6 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(22, 1, 'Aporte: Relevancia del tema en el campo de la enseñanza virtual, innovación pedagógica y contribución a la mejora de procesos de enseñanza-aprendizaje.', 'numero', '0-15'),
(22, 2, 'Marco teórico pedagógico: Dominio de teorías del aprendizaje, modelos educativos y relación con la práctica docente virtual.', 'numero', '0-15'),
(22, 3, 'Diseño metodológico: Claridad en el enfoque de investigación educativa, técnicas de recolección y análisis de datos pertinentes.', 'numero', '0-15'),
(22, 4, 'Análisis de la práctica educativa: Descripción contextualizada del problema, evidencia empírica y diagnóstico situacional.', 'numero', '0-15'),
(22, 5, 'Propuesta de innovación virtual: Creatividad, fundamentación teórica, viabilidad de implementación y criterios de evaluación de la innovación propuesta.', 'numero', '0-20'),
(22, 6, 'Reflexiones finales y limitaciones: Comentarios sobre alcances, limitaciones del estudio y líneas futuras de investigación.', 'numero', '0-20');

-- Carrera 17: Mercadeo para el Diseño (6 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(17, 1, 'Aporte: Originalidad del tema y relevancia para el mercadeo y la comunicación del diseño.', 'numero', '0-15'),
(17, 2, 'Análisis del mercado y consumidor: Comprensión del entorno, segmentación, comportamiento del consumidor y estrategias de mercadeo.', 'numero', '0-15'),
(17, 3, 'Fundamentación teórica: Sustentación teórica, modelos de mercadeo aplicados al diseño y alineación con tendencias actuales.', 'numero', '0-15'),
(17, 4, 'Metodología y propuesta de estrategia: Claridad metodológica, propuesta factible, indicadores de gestión y plan de implementación.', 'numero', '0-15'),
(17, 5, 'Análisis de viabilidad comercial: Evaluación de costos, beneficios, sostenibilidad y escalabilidad de la propuesta.', 'numero', '0-20'),
(17, 6, 'Recomendaciones estratégicas: Sugerencias específicas para mejorar la viabilidad o impacto de la propuesta de mercadeo.', 'numero', '0-20');

-- Carrera 15: Arquitectura para la Salud (6 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(15, 1, 'Aporte: Relevancia del problema de salud, originalidad y aporte a la generación de evidencia en arquitectura para la salud.', 'numero', '0-15'),
(15, 2, 'Revisión de literatura: Calidad de la búsqueda, criterios de inclusión/exclusión, síntesis de evidencia científica y normativa sanitaria.', 'numero', '0-15'),
(15, 3, 'Diseño metodológico: Adecuación del diseño, tamaño de muestra, variables e instrumentos de evaluación de espacios de salud.', 'numero', '0-15'),
(15, 4, 'Análisis arquitectónico e interpretación: Adecuación de criterios de diseño, interpretación de resultados y asociación con evidencia previa.', 'numero', '0-15'),
(15, 5, 'Implicaciones en salud pública y diseño: Relevancia para la toma de decisiones en políticas de salud, diseño de espacios y prevención.', 'numero', '0-20'),
(15, 6, 'Comentarios del examinador: Observaciones sobre la metodología, generalizabilidad de resultados o aspectos éticos.', 'numero', '0-20');

-- Carrera 9: Planificación de Asentamientos Humanos y Vivienda (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(9, 1, 'Aporte: Originalidad del tema y relevancia para la planificación de asentamientos humanos y vivienda.', 'numero', '0-20'),
(9, 2, 'Marco teórico: Revisión de literatura y teorías aplicadas al contexto urbano y habitacional.', 'numero', '0-20'),
(9, 3, 'Metodología: Claridad en el diseño de investigación, técnicas de análisis urbano y recolección de datos.', 'numero', '0-20'),
(9, 4, 'Análisis situacional: Diagnóstico del entorno urbano, habitacional y propuesta de solución.', 'numero', '0-20'),
(9, 5, 'Observaciones del examinador: Recomendaciones generales sobre el trabajo.', 'numero', '0-20');

-- Carrera 10: Restauración de Monumentos (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(10, 1, 'Aporte: Originalidad del tema y relevancia en el campo de la restauración de monumentos y bienes inmuebles.', 'numero', '0-20'),
(10, 2, 'Estado del arte: Dominio de teorías, técnicas y normativa aplicable a la restauración de bienes culturales.', 'numero', '0-20'),
(10, 3, 'Metodología: Claridad en el diagnóstico patológico, técnicas de intervención y criterios de restauración.', 'numero', '0-20'),
(10, 4, 'Propuesta de intervención: Sustentación técnica, viabilidad y respeto al valor patrimonial del bien.', 'numero', '0-20'),
(10, 5, 'Observaciones del examinador: Aspectos a fortalecer o recomendaciones específicas.', 'numero', '0-20');

-- Carrera 11: Diseño, Planificación y Manejo Ambiental (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(11, 1, 'Aporte: Originalidad del tema y relevancia para el diseño, planificación y manejo ambiental.', 'numero', '0-20'),
(11, 2, 'Fundamentación teórica: Teorías de diseño ambiental, sostenibilidad y gestión de recursos naturales.', 'numero', '0-20'),
(11, 3, 'Metodología: Claridad en el enfoque de investigación, herramientas de análisis ambiental y planificación.', 'numero', '0-20'),
(11, 4, 'Propuesta de manejo: Creatividad, viabilidad y sustentación de la propuesta de manejo ambiental.', 'numero', '0-20'),
(11, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y alcances del estudio.', 'numero', '0-20');

-- Carrera 12: Diseño Arquitectónico (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(12, 1, 'Aporte: Originalidad del tema y relevancia para el diseño arquitectónico contemporáneo.', 'numero', '0-20'),
(12, 2, 'Marco teórico: Dominio de teorías del diseño arquitectónico, estética y funcionalidad espacial.', 'numero', '0-20'),
(12, 3, 'Metodología: Claridad en el proceso de diseño, herramientas de representación y técnicas de investigación.', 'numero', '0-20'),
(12, 4, 'Propuesta arquitectónica: Calidad del proyecto, innovación, resolución espacial y técnica constructiva.', 'numero', '0-20'),
(12, 5, 'Observaciones del examinador: Comentarios sobre la propuesta y su factibilidad.', 'numero', '0-20');

-- Carrera 14: Desarrollo Urbano y Territorio (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(14, 1, 'Aporte: Originalidad del tema y relevancia para el desarrollo urbano y territorial.', 'numero', '0-20'),
(14, 2, 'Marco teórico: Teorías urbanas, planificación territorial y políticas de desarrollo sostenible.', 'numero', '0-20'),
(14, 3, 'Metodología: Claridad en el análisis urbano, herramientas de planificación y diagnóstico territorial.', 'numero', '0-20'),
(14, 4, 'Propuesta de desarrollo: Sustentación, viabilidad y alineación con políticas públicas y normativas.', 'numero', '0-20'),
(14, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su implementación.', 'numero', '0-20');

-- Carrera 16: Planificación y Diseño del Paisaje (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(16, 1, 'Aporte: Originalidad del tema y relevancia para la planificación y diseño del paisaje.', 'numero', '0-20'),
(16, 2, 'Fundamentación teórica: Teorías del paisaje, ecología, estética y sostenibilidad ambiental.', 'numero', '0-20'),
(16, 3, 'Metodología: Claridad en el diseño de investigación, técnicas de análisis paisajístico y recolección de datos.', 'numero', '0-20'),
(16, 4, 'Propuesta de diseño: Creatividad, sustentación técnica y viabilidad del proyecto paisajístico.', 'numero', '0-20'),
(16, 5, 'Observaciones del examinador: Comentarios sobre la propuesta y su factibilidad.', 'numero', '0-20');

-- Carrera 19: Patrimonio Cultural — Gestión (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(19, 1, 'Aporte: Originalidad del tema y relevancia para la gestión del patrimonio cultural.', 'numero', '0-20'),
(19, 2, 'Marco normativo y teórico: Dominio de la normativa, políticas públicas y teorías de gestión patrimonial.', 'numero', '0-20'),
(19, 3, 'Metodología: Claridad en el diseño de investigación, técnicas de gestión y análisis de viabilidad.', 'numero', '0-20'),
(19, 4, 'Propuesta de gestión: Sustentación, viabilidad institucional y alineación con políticas culturales.', 'numero', '0-20'),
(19, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta de gestión.', 'numero', '0-20');

-- Carrera 20: Especialización en Análisis y Reducción de Riesgo (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(20, 1, 'Aporte: Originalidad del tema y relevancia para el análisis y reducción de riesgo de desastres.', 'numero', '0-20'),
(20, 2, 'Fundamentación teórica: Teorías de riesgo, vulnerabilidad y gestión del desastre.', 'numero', '0-20'),
(20, 3, 'Metodología: Claridad en el análisis de riesgo, herramientas de evaluación y técnicas de investigación.', 'numero', '0-20'),
(20, 4, 'Propuesta de reducción de riesgo: Sustentación, viabilidad y alineación con políticas de gestión del riesgo.', 'numero', '0-20'),
(20, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su implementación.', 'numero', '0-20');

-- Carrera 21: Especialización en Arquitectura y Construcción Sostenible (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(21, 1, 'Aporte: Originalidad del tema y relevancia para la arquitectura y construcción sostenible.', 'numero', '0-20'),
(21, 2, 'Fundamentación teórica: Teorías de sostenibilidad, evaluación verde y normativa ambiental.', 'numero', '0-20'),
(21, 3, 'Metodología: Claridad en el análisis de sostenibilidad, herramientas de evaluación y técnicas de investigación.', 'numero', '0-20'),
(21, 4, 'Propuesta de construcción sostenible: Sustentación técnica, viabilidad y cumplimiento de criterios de evaluación.', 'numero', '0-20'),
(21, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su factibilidad.', 'numero', '0-20');

-- Carrera 23: Diseño Interactivo Digital (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(23, 1, 'Aporte: Originalidad del tema y relevancia para el diseño interactivo digital.', 'numero', '0-20'),
(23, 2, 'Fundamentación teórica: Teorías del diseño digital, interacción humano-computadora y experiencia de usuario.', 'numero', '0-20'),
(23, 3, 'Metodología: Claridad en el proceso de diseño, prototipado, técnicas de evaluación y recolección de datos.', 'numero', '0-20'),
(23, 4, 'Propuesta de diseño: Creatividad, sustentación técnica, viabilidad y evaluación de la experiencia de usuario.', 'numero', '0-20'),
(23, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su factibilidad.', 'numero', '0-20');

-- Carrera 25: Gestión Integrada (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(25, 1, 'Aporte: Originalidad del tema y relevancia para la gestión integrada de medio ambiente, calidad y prevención.', 'numero', '0-20'),
(25, 2, 'Fundamentación teórica: Normativas ISO, sistemas de gestión integrada y políticas de sostenibilidad.', 'numero', '0-20'),
(25, 3, 'Metodología: Claridad en el diseño de investigación, auditorías y técnicas de evaluación de gestión.', 'numero', '0-20'),
(25, 4, 'Propuesta de gestión integrada: Sustentación, viabilidad y alineación con normativas y políticas.', 'numero', '0-20'),
(25, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su implementación.', 'numero', '0-20');

-- Carrera 26: Diseño y Gestión de Proyectos Tecnológicos (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(26, 1, 'Aporte: Originalidad del tema y relevancia para el diseño y gestión de proyectos tecnológicos.', 'numero', '0-20'),
(26, 2, 'Fundamentación teórica: Teorías de gestión de proyectos tecnológicos, innovación y metodologías ágiles.', 'numero', '0-20'),
(26, 3, 'Metodología: Claridad en el diseño de investigación, herramientas de gestión y técnicas de evaluación.', 'numero', '0-20'),
(26, 4, 'Propuesta de proyecto: Sustentación técnica, viabilidad, innovación y planificación de implementación.', 'numero', '0-20'),
(26, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su factibilidad.', 'numero', '0-20');

-- Carrera 28: Especialización en Dirección y Producción de Cine (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(28, 1, 'Aporte: Originalidad del tema y relevancia para la dirección y producción de cine, video y televisión.', 'numero', '0-20'),
(28, 2, 'Fundamentación teórica: Teorías cinematográficas, narrativa audiovisual y técnicas de producción.', 'numero', '0-20'),
(28, 3, 'Metodología: Claridad en el proceso de producción, guion, dirección y técnicas de investigación.', 'numero', '0-20'),
(28, 4, 'Propuesta de producción: Creatividad, sustentación técnica, viabilidad y planificación de rodaje.', 'numero', '0-20'),
(28, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su factibilidad.', 'numero', '0-20');

-- Carrera 80: Doctorado en Arquitectura (5 preguntas numéricas, total 100)
INSERT INTO examen_matriz_pregunta (cod_carrera, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(80, 1, 'Aporte: Originalidad del tema y relevancia para la investigación doctoral en arquitectura.', 'numero', '0-20'),
(80, 2, 'Marco teórico: Dominio profundo de teorías arquitectónicas, estado del arte y construcción de conocimiento.', 'numero', '0-20'),
(80, 3, 'Metodología: Claridad en el diseño de investigación doctoral, rigor metodológico y técnicas avanzadas.', 'numero', '0-20'),
(80, 4, 'Contribución al conocimiento: Originalidad, impacto académico y relevancia para la disciplina arquitectónica.', 'numero', '0-20'),
(80, 5, 'Observaciones del examinador: Recomendaciones sobre la investigación y su desarrollo.', 'numero', '0-20');
