-- ============================================================
-- Script: Matriz de Evaluación del Examen Privado — COMPLETO
-- Fecha: 2026-06-03
-- Descripción: Schema + seeds para 20 matrices (una por carrera activa)
-- ============================================================

-- 1. Tablas nuevas
CREATE TABLE IF NOT EXISTS examen_matriz_tipo (
  cod_matriz_tipo TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cod_carrera INT UNSIGNED NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  activo TINYINT(1) DEFAULT 1,
  UNIQUE KEY uk_carrera (cod_carrera)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS examen_matriz_pregunta (
  cod_pregunta SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cod_matriz_tipo TINYINT UNSIGNED NOT NULL,
  numero_orden TINYINT UNSIGNED NOT NULL,
  texto_pregunta VARCHAR(500) NOT NULL,
  tipo_campo ENUM('numero', 'texto') NOT NULL DEFAULT 'numero',
  punteo_maximo VARCHAR(20) DEFAULT '0-10',
  activo TINYINT(1) DEFAULT 1,
  FOREIGN KEY (cod_matriz_tipo) REFERENCES examen_matriz_tipo(cod_matriz_tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS examen_matriz_evaluacion (
  cod_evaluacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cod_proceso INT UNSIGNED NOT NULL,
  posicion_examinador TINYINT UNSIGNED NOT NULL COMMENT '1, 2 o 3',
  evaluado_por INT(11) NOT NULL,
  fecha_evaluacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  observaciones_generales TEXT,
  UNIQUE KEY uq_evaluacion_proceso_examinador (cod_proceso, posicion_examinador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS examen_matriz_respuesta (
  cod_respuesta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cod_evaluacion INT UNSIGNED NOT NULL,
  cod_pregunta SMALLINT UNSIGNED NOT NULL,
  punteo DECIMAL(4,2) NULL,
  respuesta_texto TEXT NULL,
  FOREIGN KEY (cod_evaluacion) REFERENCES examen_matriz_evaluacion(cod_evaluacion),
  FOREIGN KEY (cod_pregunta) REFERENCES examen_matriz_pregunta(cod_pregunta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Alter tabla existente
ALTER TABLE examen_proceso
ADD COLUMN tema_tesis VARCHAR(500) NULL COMMENT 'Tema del trabajo de graduación' AFTER cod_paso_actual;

-- 3. Seeds — 20 matrices con cod_carrera vinculado
INSERT INTO examen_matriz_tipo (cod_matriz_tipo, cod_carrera, nombre) VALUES
(1, 18, 'Maestría en Patrimonio Cultural — Conservación'),
(2, 24, 'Maestría en Gerencia de Proyectos Arquitectónicos'),
(3, 13, 'Maestría en Gestión para la Reducción del Riesgo'),
(4, 22, 'Maestría en Enseñanza Virtual de la Arquitectura y el Diseño'),
(5, 17, 'Maestría en Mercadeo para el Diseño'),
(6, 15, 'Maestría en Arquitectura para la Salud'),
(7, 9,  'Maestría en Planificación de Asentamientos Humanos y Vivienda'),
(8, 10, 'Maestría en Restauración de Monumentos, Especialidad en Bienes Inmuebles y Centros Históricos'),
(9, 11, 'Maestría en Diseño, Planificación y Manejo Ambiental'),
(10, 12, 'Maestría en Diseño Arquitectónico'),
(11, 14, 'Maestría en Desarrollo Urbano y Territorio'),
(12, 16, 'Maestría en Planificación y Diseño del Paisaje'),
(13, 19, 'Maestría en Patrimonio Cultural para el Desarrollo — Gestión'),
(14, 20, 'Especialización en Análisis y Reducción de Riesgo de Desastres'),
(15, 21, 'Especialización en Arquitectura y Construcción Sostenible'),
(16, 23, 'Maestría en Diseño Interactivo Digital'),
(17, 25, 'Maestría en Gestión Integrada: Medio Ambiente, Calidad y Prevención'),
(18, 26, 'Maestría en Diseño y Gestión de Proyectos Tecnológicos'),
(19, 28, 'Especialización en Dirección y Producción de Cine, Video y Televisión'),
(20, 80, 'Doctorado en Arquitectura');

-- 4. Seeds — Preguntas de las matrices (las 6 originales + 14 nuevas)

-- Matriz 1: Patrimonio Cultural — Conservación (5 preguntas reales)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(1, 1, 'Aporte: Originalidad del tema y Estado del Arte, aporte al conocimiento y a la solución de problemas de la sociedad.', 'numero', '0-10'),
(1, 2, 'Valoración y significación del objeto de estudio: Sustentación de los valores y significación, del bien cultural inmueble objeto de estudio.', 'numero', '0-10'),
(1, 3, 'Metodología: Claridad, orden lógico, sustentación teórica, selección y uso adecuado de procedimientos y técnicas de investigación utilizados en la conservación y restauración de bienes culturales inmuebles.', 'numero', '0-10'),
(1, 4, 'Contextualización del objeto de estudio: Histórico, Legal, institucional, político, social, económico, geográfico, entre otros.', 'numero', '0-10'),
(1, 5, 'Diagnóstico: Sistematización y análisis de la información. Sustentación adecuada del diagnóstico de la situación (pasado, evolución y actual) del estado, el potencial y la problemática detectada en el objeto de estudio.', 'numero', '0-20');

-- Matriz 2: Gerencia de Proyectos Arquitectónicos (6 preguntas)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(2, 1, 'Aporte: Originalidad del tema y relevancia en el campo de la gerencia de proyectos, aporte a la solución de problemas organizacionales.', 'numero', '0-10'),
(2, 2, 'Definición del problema y objetivos: Claridad en la identificación del problema, objetivos SMART y alineación con necesidades reales.', 'numero', '0-10'),
(2, 3, 'Marco teórico y estado del arte: Revisión de literatura pertinente, teorías aplicadas y relación con casos reales de proyectos.', 'numero', '0-10'),
(2, 4, 'Metodología y herramientas de gestión: Aplicación de metodologías (PMI, Agile, etc.), uso de herramientas y claridad en la planificación.', 'numero', '0-10'),
(2, 5, 'Análisis de viabilidad y riesgos: Evaluación financiera, técnica, legal y de riesgos del proyecto propuesto.', 'numero', '0-20'),
(2, 6, 'Recomendaciones para implementación: Observaciones sobre la factibilidad real y aspectos a mejorar en la propuesta.', 'texto', 'N/A');

-- Matriz 3: Gestión para la Reducción del Riesgo (6 preguntas)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(3, 1, 'Aporte: Originalidad del tema, relevancia jurídica y contribución al desarrollo de la gestión del riesgo.', 'numero', '0-10'),
(3, 2, 'Análisis doctrinal y normativo: Dominio de la normativa, políticas y construcción de argumentos técnicos sólidos.', 'numero', '0-10'),
(3, 3, 'Metodología de investigación: Claridad en el enfoque, uso de herramientas de análisis de riesgo y técnicas de recolección.', 'numero', '0-10'),
(3, 4, 'Análisis de vulnerabilidad y riesgo: Evaluación de amenazas, vulnerabilidad y capacidades de respuesta.', 'numero', '0-10'),
(3, 5, 'Propuesta de gestión del riesgo: Originalidad, viabilidad y sustentación de la propuesta de reducción de riesgo.', 'numero', '0-20'),
(3, 6, 'Observaciones del examinador: Aspectos a fortalecer o recomendaciones específicas sobre el trabajo.', 'texto', 'N/A');

-- Matriz 4: Enseñanza Virtual de la Arquitectura y el Diseño (6 preguntas)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(4, 1, 'Aporte: Relevancia del tema en el campo de la enseñanza virtual, innovación pedagógica y contribución a la mejora de procesos de enseñanza-aprendizaje.', 'numero', '0-10'),
(4, 2, 'Marco teórico pedagógico: Dominio de teorías del aprendizaje, modelos educativos y relación con la práctica docente virtual.', 'numero', '0-10'),
(4, 3, 'Diseño metodológico: Claridad en el enfoque de investigación educativa, técnicas de recolección y análisis de datos pertinentes.', 'numero', '0-10'),
(4, 4, 'Análisis de la práctica educativa: Descripción contextualizada del problema, evidencia empírica y diagnóstico situacional.', 'numero', '0-10'),
(4, 5, 'Propuesta de innovación virtual: Creatividad, fundamentación teórica, viabilidad de implementación y criterios de evaluación de la innovación propuesta.', 'numero', '0-20'),
(4, 6, 'Reflexiones finales y limitaciones: Comentarios sobre alcances, limitaciones del estudio y líneas futuras de investigación.', 'texto', 'N/A');

-- Matriz 5: Mercadeo para el Diseño (6 preguntas)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(5, 1, 'Aporte: Originalidad del tema y relevancia para el mercadeo y la comunicación del diseño.', 'numero', '0-10'),
(5, 2, 'Análisis del mercado y consumidor: Comprensión del entorno, segmentación, comportamiento del consumidor y estrategias de mercadeo.', 'numero', '0-10'),
(5, 3, 'Fundamentación teórica: Sustentación teórica, modelos de mercadeo aplicados al diseño y alineación con tendencias actuales.', 'numero', '0-10'),
(5, 4, 'Metodología y propuesta de estrategia: Claridad metodológica, propuesta factible, indicadores de gestión y plan de implementación.', 'numero', '0-10'),
(5, 5, 'Análisis de viabilidad comercial: Evaluación de costos, beneficios, sostenibilidad y escalabilidad de la propuesta.', 'numero', '0-20'),
(5, 6, 'Recomendaciones estratégicas: Sugerencias específicas para mejorar la viabilidad o impacto de la propuesta de mercadeo.', 'texto', 'N/A');

-- Matriz 6: Arquitectura para la Salud (6 preguntas)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(6, 1, 'Aporte: Relevancia del problema de salud, originalidad y aporte a la generación de evidencia en arquitectura para la salud.', 'numero', '0-10'),
(6, 2, 'Revisión de literatura: Calidad de la búsqueda, criterios de inclusión/exclusión, síntesis de evidencia científica y normativa sanitaria.', 'numero', '0-10'),
(6, 3, 'Diseño metodológico: Adecuación del diseño, tamaño de muestra, variables e instrumentos de evaluación de espacios de salud.', 'numero', '0-10'),
(6, 4, 'Análisis arquitectónico e interpretación: Adecuación de criterios de diseño, interpretación de resultados y asociación con evidencia previa.', 'numero', '0-10'),
(6, 5, 'Implicaciones en salud pública y diseño: Relevancia para la toma de decisiones en políticas de salud, diseño de espacios y prevención.', 'numero', '0-20'),
(6, 6, 'Comentarios del examinador: Observaciones sobre la metodología, generalizabilidad de resultados o aspectos éticos.', 'texto', 'N/A');

-- Matriz 7: Planificación de Asentamientos Humanos y Vivienda (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(7, 1, 'Aporte: Originalidad del tema y relevancia para la planificación de asentamientos humanos y vivienda.', 'numero', '0-10'),
(7, 2, 'Marco teórico: Revisión de literatura y teorías aplicadas al contexto urbano y habitacional.', 'numero', '0-10'),
(7, 3, 'Metodología: Claridad en el diseño de investigación, técnicas de análisis urbano y recolección de datos.', 'numero', '0-10'),
(7, 4, 'Análisis situacional: Diagnóstico del entorno urbano, habitacional y propuesta de solución.', 'numero', '0-10'),
(7, 5, 'Observaciones del examinador: Recomendaciones generales sobre el trabajo.', 'texto', 'N/A');

-- Matriz 8: Restauración de Monumentos (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(8, 1, 'Aporte: Originalidad del tema y relevancia en el campo de la restauración de monumentos y bienes inmuebles.', 'numero', '0-10'),
(8, 2, 'Estado del arte: Dominio de teorías, técnicas y normativa aplicable a la restauración de bienes culturales.', 'numero', '0-10'),
(8, 3, 'Metodología: Claridad en el diagnóstico patológico, técnicas de intervención y criterios de restauración.', 'numero', '0-10'),
(8, 4, 'Propuesta de intervención: Sustentación técnica, viabilidad y respeto al valor patrimonial del bien.', 'numero', '0-10'),
(8, 5, 'Observaciones del examinador: Aspectos a fortalecer o recomendaciones específicas.', 'texto', 'N/A');

-- Matriz 9: Diseño, Planificación y Manejo Ambiental (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(9, 1, 'Aporte: Originalidad del tema y relevancia para el diseño, planificación y manejo ambiental.', 'numero', '0-10'),
(9, 2, 'Fundamentación teórica: Teorías de diseño ambiental, sostenibilidad y gestión de recursos naturales.', 'numero', '0-10'),
(9, 3, 'Metodología: Claridad en el enfoque de investigación, herramientas de análisis ambiental y planificación.', 'numero', '0-10'),
(9, 4, 'Propuesta de manejo: Creatividad, viabilidad y sustentación de la propuesta de manejo ambiental.', 'numero', '0-10'),
(9, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y alcances del estudio.', 'texto', 'N/A');

-- Matriz 10: Diseño Arquitectónico (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(10, 1, 'Aporte: Originalidad del tema y relevancia para el diseño arquitectónico contemporáneo.', 'numero', '0-10'),
(10, 2, 'Marco teórico: Dominio de teorías del diseño arquitectónico, estética y funcionalidad espacial.', 'numero', '0-10'),
(10, 3, 'Metodología: Claridad en el proceso de diseño, herramientas de representación y técnicas de investigación.', 'numero', '0-10'),
(10, 4, 'Propuesta arquitectónica: Calidad del proyecto, innovación, resolución espacial y técnica constructiva.', 'numero', '0-10'),
(10, 5, 'Observaciones del examinador: Comentarios sobre la propuesta y su factibilidad.', 'texto', 'N/A');

-- Matriz 11: Desarrollo Urbano y Territorio (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(11, 1, 'Aporte: Originalidad del tema y relevancia para el desarrollo urbano y territorial.', 'numero', '0-10'),
(11, 2, 'Marco teórico: Teorías urbanas, planificación territorial y políticas de desarrollo sostenible.', 'numero', '0-10'),
(11, 3, 'Metodología: Claridad en el análisis urbano, herramientas de planificación y diagnóstico territorial.', 'numero', '0-10'),
(11, 4, 'Propuesta de desarrollo: Sustentación, viabilidad y alineación con políticas públicas y normativas.', 'numero', '0-10'),
(11, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su implementación.', 'texto', 'N/A');

-- Matriz 12: Planificación y Diseño del Paisaje (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(12, 1, 'Aporte: Originalidad del tema y relevancia para la planificación y diseño del paisaje.', 'numero', '0-10'),
(12, 2, 'Fundamentación teórica: Teorías del paisaje, ecología, estética y sostenibilidad ambiental.', 'numero', '0-10'),
(12, 3, 'Metodología: Claridad en el diseño de investigación, técnicas de análisis paisajístico y recolección de datos.', 'numero', '0-10'),
(12, 4, 'Propuesta de diseño: Creatividad, sustentación técnica y viabilidad del proyecto paisajístico.', 'numero', '0-10'),
(12, 5, 'Observaciones del examinador: Comentarios sobre la propuesta y su factibilidad.', 'texto', 'N/A');

-- Matriz 13: Patrimonio Cultural — Gestión (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(13, 1, 'Aporte: Originalidad del tema y relevancia para la gestión del patrimonio cultural.', 'numero', '0-10'),
(13, 2, 'Marco normativo y teórico: Dominio de la normativa, políticas públicas y teorías de gestión patrimonial.', 'numero', '0-10'),
(13, 3, 'Metodología: Claridad en el diseño de investigación, técnicas de gestión y análisis de viabilidad.', 'numero', '0-10'),
(13, 4, 'Propuesta de gestión: Sustentación, viabilidad institucional y alineación con políticas culturales.', 'numero', '0-10'),
(13, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta de gestión.', 'texto', 'N/A');

-- Matriz 14: Especialización en Análisis y Reducción de Riesgo (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(14, 1, 'Aporte: Originalidad del tema y relevancia para el análisis y reducción de riesgo de desastres.', 'numero', '0-10'),
(14, 2, 'Fundamentación teórica: Teorías de riesgo, vulnerabilidad y gestión del desastre.', 'numero', '0-10'),
(14, 3, 'Metodología: Claridad en el análisis de riesgo, herramientas de evaluación y técnicas de investigación.', 'numero', '0-10'),
(14, 4, 'Propuesta de reducción de riesgo: Sustentación, viabilidad y alineación con políticas de gestión del riesgo.', 'numero', '0-10'),
(14, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su implementación.', 'texto', 'N/A');

-- Matriz 15: Especialización en Arquitectura y Construcción Sostenible (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(15, 1, 'Aporte: Originalidad del tema y relevancia para la arquitectura y construcción sostenible.', 'numero', '0-10'),
(15, 2, 'Fundamentación teórica: Teorías de sostenibilidad, evaluación verde y normativa ambiental.', 'numero', '0-10'),
(15, 3, 'Metodología: Claridad en el análisis de sostenibilidad, herramientas de evaluación y técnicas de investigación.', 'numero', '0-10'),
(15, 4, 'Propuesta de construcción sostenible: Sustentación técnica, viabilidad y cumplimiento de criterios de evaluación.', 'numero', '0-10'),
(15, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su factibilidad.', 'texto', 'N/A');

-- Matriz 16: Diseño Interactivo Digital (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(16, 1, 'Aporte: Originalidad del tema y relevancia para el diseño interactivo digital.', 'numero', '0-10'),
(16, 2, 'Fundamentación teórica: Teorías del diseño digital, interacción humano-computadora y experiencia de usuario.', 'numero', '0-10'),
(16, 3, 'Metodología: Claridad en el proceso de diseño, prototipado, técnicas de evaluación y recolección de datos.', 'numero', '0-10'),
(16, 4, 'Propuesta de diseño: Creatividad, sustentación técnica, viabilidad y evaluación de la experiencia de usuario.', 'numero', '0-10'),
(16, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su factibilidad.', 'texto', 'N/A');

-- Matriz 17: Gestión Integrada (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(17, 1, 'Aporte: Originalidad del tema y relevancia para la gestión integrada de medio ambiente, calidad y prevención.', 'numero', '0-10'),
(17, 2, 'Fundamentación teórica: Normativas ISO, sistemas de gestión integrada y políticas de sostenibilidad.', 'numero', '0-10'),
(17, 3, 'Metodología: Claridad en el diseño de investigación, auditorías y técnicas de evaluación de gestión.', 'numero', '0-10'),
(17, 4, 'Propuesta de gestión integrada: Sustentación, viabilidad y alineación con normativas y políticas.', 'numero', '0-10'),
(17, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su implementación.', 'texto', 'N/A');

-- Matriz 18: Diseño y Gestión de Proyectos Tecnológicos (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(18, 1, 'Aporte: Originalidad del tema y relevancia para el diseño y gestión de proyectos tecnológicos.', 'numero', '0-10'),
(18, 2, 'Fundamentación teórica: Teorías de gestión de proyectos tecnológicos, innovación y metodologías ágiles.', 'numero', '0-10'),
(18, 3, 'Metodología: Claridad en el diseño de investigación, herramientas de gestión y técnicas de evaluación.', 'numero', '0-10'),
(18, 4, 'Propuesta de proyecto: Sustentación técnica, viabilidad, innovación y planificación de implementación.', 'numero', '0-10'),
(18, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su factibilidad.', 'texto', 'N/A');

-- Matriz 19: Especialización en Dirección y Producción de Cine (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(19, 1, 'Aporte: Originalidad del tema y relevancia para la dirección y producción de cine, video y televisión.', 'numero', '0-10'),
(19, 2, 'Fundamentación teórica: Teorías cinematográficas, narrativa audiovisual y técnicas de producción.', 'numero', '0-10'),
(19, 3, 'Metodología: Claridad en el proceso de producción, guion, dirección y técnicas de investigación.', 'numero', '0-10'),
(19, 4, 'Propuesta de producción: Creatividad, sustentación técnica, viabilidad y planificación de rodaje.', 'numero', '0-10'),
(19, 5, 'Observaciones del examinador: Recomendaciones sobre la propuesta y su factibilidad.', 'texto', 'N/A');

-- Matriz 20: Doctorado en Arquitectura (5 preguntas de prueba)
INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
(20, 1, 'Aporte: Originalidad del tema y relevancia para la investigación doctoral en arquitectura.', 'numero', '0-10'),
(20, 2, 'Marco teórico: Dominio profundo de teorías arquitectónicas, estado del arte y construcción de conocimiento.', 'numero', '0-10'),
(20, 3, 'Metodología: Claridad en el diseño de investigación doctoral, rigor metodológico y técnicas avanzadas.', 'numero', '0-10'),
(20, 4, 'Contribución al conocimiento: Originalidad, impacto académico y relevancia para la disciplina arquitectónica.', 'numero', '0-10'),
(20, 5, 'Observaciones del examinador: Recomendaciones sobre la investigación y su desarrollo.', 'texto', 'N/A');

-- ============================================================
-- NOTA PARA PRODUCCIÓN
-- ============================================================
-- Para reemplazar las preguntas de prueba con las preguntas reales:
-- 1. Identificar la cod_matriz_tipo de la maestría (ej: 7 para Planificación)
-- 2. Ejecutar:
--    DELETE FROM examen_matriz_pregunta WHERE cod_matriz_tipo = 7;
--    INSERT INTO examen_matriz_pregunta (cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo) VALUES
--    (7, 1, 'Pregunta real 1 de esta maestría', 'numero', '0-10'),
--    (7, 2, 'Pregunta real 2', 'texto', 'N/A'),
--    ...;
--
-- O usar UPDATE para modificar texto existente sin borrar:
--    UPDATE examen_matriz_pregunta SET texto_pregunta = 'Nuevo texto' WHERE cod_pregunta = XX;
--
-- 3. Para agregar/eliminar preguntas de una matriz existente:
--    INSERT INTO examen_matriz_pregunta (...) VALUES (...);  -- agregar nueva
--    UPDATE examen_matriz_pregunta SET activo = 0 WHERE cod_pregunta = XX;  -- desactivar
--    UPDATE examen_matriz_pregunta SET activo = 1 WHERE cod_pregunta = XX;  -- reactivar

-- ============================================================
-- 4. Migración: examen_tipo ahora está vinculado a carrera
-- ============================================================

SET @add_column = IF(
    NOT EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'examen_tipo'
        AND column_name = 'cod_carrera'
    ),
    'ALTER TABLE examen_tipo ADD COLUMN cod_carrera INT UNSIGNED NULL, ADD UNIQUE KEY uk_carrera (cod_carrera)',
    'SELECT 1'
);
PREPARE stmt FROM @add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE examen_tipo SET cod_carrera = 18 WHERE cod_tipo_examen = 1;
UPDATE examen_tipo SET cod_carrera = 24 WHERE cod_tipo_examen = 2;

INSERT INTO examen_tipo (cod_carrera, nombre, descripcion, activo)
SELECT
    nc.cod_carrera,
    CONCAT('Privado - ', LEFT(nc.nombre, 89)),
    CONCAT('Examen privado para ', nc.nombre),
    nc.activo
FROM nombre_carrera nc
WHERE nc.activo = 1
  AND nc.cod_carrera NOT IN (999, 18, 24);
