-- Estudiantes de prueba para evaluación docente
-- Contraseña para todos: 123456

-- Estudiante 1
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1234567890011', '20250011', 'María', 'González López', '2000-01-15', '5551001', 'maria.gonzalez@email.com', '$2y$10$uVcqFvcp/QzAFKjmFF.YrOZj/2i/U0BYuJrUo5X9LlMYotf9aoI3G', 73, 'M', 'Bachiller en computacion', CURDATE(), 'María González López');

-- Estudiante 2
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1234567890012', '20250012', 'Carlos', 'Rodríguez Silva', '1999-03-22', '5551002', 'carlos.rodriguez@email.com', '$2y$10$jr9ehVyuU2mrqpDE3rNlhO9wV6k1YT/pqruLACg9.QsIY13Mlrb.y', 73, 'H', 'Bachiller cientifico', CURDATE(), 'Carlos Rodríguez Silva');

-- Estudiante 3
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1234567890013', '20250013', 'Ana', 'Morales Castro', '2001-07-10', '5551003', 'ana.morales@email.com', '$2y$10$xlL40oothjfOPppxafkGQumCL8JhFBZOPsPvIRR1NQox9lQoXGHGu', 73, 'M', 'Bachiller en mecanica', CURDATE(), 'Ana Morales Castro');







-- Estudiante 4
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1234567890014', '20250014', 'Luis', 'Herrera Paz', '2000-11-05', '5551004', 'luis.herrera@email.com', '$2y$10$xwUBRMyrTAYBq1qPMlzIk.X12mdxhjj00TnJAnGBhgeLdJG6miR1a', 73, 'H', 'Bachiller en artes plasticas', CURDATE(), 'Luis Herrera Paz');

-- Estudiante 5
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1234567890015', '20250015', 'Sofía', 'Jiménez Ruiz', '1998-12-18', '5551005', 'sofia.jimenez@email.com', '$2y$10$eVqz0d7AyQ.uVQ9Y.0B8wuJ.cVJa2LlzunQ5mNqh9OmAQJKelFSa.', 73, 'M', 'Bachiller en finanzas', CURDATE(), 'Sofía Jiménez Ruiz');

-- Estudiante 6
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1234567890016', '20250016', 'Alvaro', 'Perez Ruiz', '2000-12-20', '5551006', 'alvaro.perez@email.com', '$2y$10$UyBOW14rRfaoJja28mh52.CRgO.k9UDXWQXm.VG49OBjlV3kxZ3cW', 73, 'H', 'Bachiller disenio grafico', CURDATE(), 'Alvaro Perez Ruiz');

-- Asignar rol de ESTUDIANTE (cod_rol = 6) a todos
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES 
(@maria_id, 6, CURDATE()),
(@carlos_id, 6, CURDATE()),
(@ana_id, 6, CURDATE()),
(@luis_id, 6, CURDATE()),
(@sofia_id, 6, CURDATE()),
(@alvaro_id, 6, CURDATE());

-- Crear algunos cursos de ejemplo para que puedan evaluar
INSERT INTO curso_pensum (nombre_curso, descripcion, creditos, categoria, seccion) VALUES 
('Metodología de la Investigación', 'Curso sobre métodos de investigación científica', 3, 'Obligatorio', 'A'),
('Estadística Aplicada', 'Curso de estadística para ciencias sociales', 4, 'Obligatorio', 'B'),
('Diseño Curricular', 'Principios del diseño curricular moderno', 3, 'Electivo', 'A'),
('Evaluación Educativa', 'Métodos de evaluación en educación', 3, 'Obligatorio', 'C');

-- Crear horarios para estos cursos
INSERT INTO horario (cod_curso_pensum, fecha_inicio, fecha_fin, mes, anio, cod_usuario_coordinador, cod_usuario_catedratico, activo) VALUES 
(LAST_INSERT_ID() - 3, '2025-01-15', '2025-05-30', 1, 2025, 1, 2, 1), -- Metodología
(LAST_INSERT_ID() - 2, '2025-01-15', '2025-05-30', 1, 2025, 1, 3, 1), -- Estadística
(LAST_INSERT_ID() - 1, '2025-01-15', '2025-05-30', 1, 2025, 1, 4, 1), -- Diseño
(LAST_INSERT_ID(), '2025-01-15', '2025-05-30', 1, 2025, 1, 5, 1);     -- Evaluación

-- Asignar estudiantes a cursos (crear asignaciones válidas)
SET @horario1 = LAST_INSERT_ID() - 3;
SET @horario2 = LAST_INSERT_ID() - 2;
SET @horario3 = LAST_INSERT_ID() - 1;
SET @horario4 = LAST_INSERT_ID();

-- María toma 2 cursos
INSERT INTO asignacion (cod_usuario, cod_horario, fecha_asignacion, valida, tipo_asignacion) VALUES 
(@maria_id, @horario1, CURDATE(), 1, 'regular'),
(@maria_id, @horario2, CURDATE(), 1, 'regular');

-- Carlos toma 3 cursos
INSERT INTO asignacion (cod_usuario, cod_horario, fecha_asignacion, valida, tipo_asignacion) VALUES 
(@carlos_id, @horario1, CURDATE(), 1, 'regular'),
(@carlos_id, @horario3, CURDATE(), 1, 'regular'),
(@carlos_id, @horario4, CURDATE(), 1, 'regular');

-- Ana toma 1 curso
INSERT INTO asignacion (cod_usuario, cod_horario, fecha_asignacion, valida, tipo_asignacion) VALUES 
(@ana_id, @horario2, CURDATE(), 1, 'regular');

-- Luis toma 2 cursos
INSERT INTO asignacion (cod_usuario, cod_horario, fecha_asignacion, valida, tipo_asignacion) VALUES 
(@luis_id, @horario3, CURDATE(), 1, 'regular'),
(@luis_id, @horario4, CURDATE(), 1, 'regular');

-- Sofía toma 1 curso
INSERT INTO asignacion (cod_usuario, cod_horario, fecha_asignacion, valida, tipo_asignacion) VALUES 
(@sofia_id, @horario1, CURDATE(), 1, 'regular');

-- Crear algunos docentes de ejemplo (coordinadores y catedráticos)
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo) VALUES 
('9876543210001', 'DOC001', 'Dr. Juan Carlos', 'Pérez López', '1975-05-15', '5559001', 'jperez@email.com', '$2y$10$pGh1zOsBFimCppkS.CF3KO4w2u7ECH8rj/F9NqlKfWVXxeOVbwZzq', 73, 'H', 'Doctor', CURDATE(), 'Dr. Juan Carlos Pérez López'),
('9876543210002', 'DOC002', 'Dra. María Fernanda', 'García Ruiz', '1980-08-22', '5559002', 'mgarcia@email.com', '$2y$10$pGh1zOsBFimCppkS.CF3KO4w2u7ECH8rj/F9NqlKfWVXxeOVbwZzq', 73, 'M', 'Doctora', CURDATE(), 'Dra. María Fernanda García Ruiz'),
('9876543210003', 'DOC003', 'Mtro. Roberto Antonio', 'Morales Castro', '1978-12-03', '5559003', 'rmorales@email.com', '$2y$10$pGh1zOsBFimCppkS.CF3KO4w2u7ECH8rj/F9NqlKfWVXxeOVbwZzq', 73, 'H', 'Maestro', CURDATE(), 'Mtro. Roberto Antonio Morales Castro'),
('9876543210004', 'DOC004', 'Mtra. Carmen Elena', 'Vásquez Torres', '1982-04-18', '5559004', 'cvasquez@email.com', '$2y$10$pGh1zOsBFimCppkS.CF3KO4w2u7ECH8rj/F9NqlKfWVXxeOVbwZzq', 73, 'M', 'Maestra', CURDATE(), 'Mtra. Carmen Elena Vásquez Torres'),
('9876543210005', 'DOC005', 'Dr. Alberto', 'Sandoval Mejía', '1970-09-10', '5559005', 'asandoval@email.com', '$2y$10$pGh1zOsBFimCppkS.CF3KO4w2u7ECH8rj/F9NqlKfWVXxeOVbwZzq', 73, 'H', 'Doctor', CURDATE(), 'Dr. Alberto Sandoval Mejía');

-- Información de login para las pruebas:
-- Estudiantes pueden loguearse con:
-- maria.gonzalez@email.com / 123456
-- carlos.rodriguez@email.com / 123456  
-- ana.morales@email.com / 123456
-- luis.herrera@email.com / 123456
-- sofia.jimenez@email.com / 123456