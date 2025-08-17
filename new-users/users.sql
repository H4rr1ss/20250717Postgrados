-- -- Director
-- INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
-- VALUES ('1000000000001', '20250001', 'Juan', 'Director', '1980-01-01', '5551001', 'director@email.com', '', 73, 'H', 'Licenciado', CURDATE(), 'Juan Director');

-- Asistente
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000002', '20250002', 'Ana', 'Asistente', '1985-02-02', '5551002', 'asistente@email.com', '$2y$10$unJDDkwLcQvySXO7yvvY2uKnVIsAFGnkwPCSk1pM1Lti1Go1LIUAW', 73, 'M', 'Licenciada', CURDATE(), 'Ana Asistente');

-- Tesorero
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000003', '20250003', 'Luis', 'Tesorero', '1975-03-03', '5551003', 'tesorero@email.com', '$2y$10$RTzz5675pcIHaYNxYJU8k.6Ivl3mCQ8wQNlVsDxphxoPUrC1CgPsW', 73, 'H', 'Contador', CURDATE(), 'Luis Tesorero');

-- Coordinador
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000004', '20250004', 'Marta', 'Coordinador', '1982-04-04', '5551004', 'coordinador@email.com', '$2y$10$X8wv0Rcxj57tRrMNZX5XN.d8kibB/AFa/rrQZ7xJeXNQvmz1fXJHK', 73, 'M', 'Licenciada', CURDATE(), 'Marta Coordinador');

-- Catedrático
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000005', '20250005', 'Carlos', 'Catedratico', '1978-05-05', '5551005', 'catedratico@email.com', '$2y$10$U/nxzLRUtRJWHeSUddjPr.LuJt.BGC5n/5KeCvUhEDbM10maiAQj2', 73, 'H', 'Doctor', CURDATE(), 'Carlos Catedratico');

-- Estudiante
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000006', '20250006', 'Sofia', 'Estudiante', '2000-06-06', '5551006', 'estudiante@email.com', '$2y$10$pGh1zOsBFimCppkS.CF3KO4w2u7ECH8rj/F9NqlKfWVXxeOVbwZzq', 73, 'M', 'Bachiller', CURDATE(), 'Sofia Estudiante');

-- Programador
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000007', '20250007', 'Pedro', 'Programador', '1992-07-07', '5551007', 'programador@email.com', '$2y$10$y3X2A8eF6VbGvZTR6eQsmOnjfrm9TcCC8NxqQ0oLPuOlR.D.9HR8m', 73, 'H', 'Ingeniero', CURDATE(), 'Pedro Programador');

-- UDICA Programador
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000008', '20250008', 'Lucia', 'UDICA Programador', '1993-08-08', '5551008', 'udica.programador@email.com', '$2y$10$gwM/f9JWgJdjZWN5dFhRF.C1I7veQLCOQQr8.4/OIywJHJ4.dJXOS', 73, 'M', 'Ingeniera', CURDATE(), 'Lucia UDICA Programador');

-- UDICA Jefe
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000009', '20250009', 'Jorge', 'UDICA Jefe', '1970-09-09', '5551009', 'udica.jefe@email.com', '$2y$10$D0vsUaXGnfrDuevN4eoXJulm0cqP/Kw1km8gNiqfc5ddX4IcdU0qi', 73, 'H', 'Licenciado', CURDATE(), 'Jorge UDICA Jefe');

-- UDICA Operador
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000010', '20250010', 'Paula', 'UDICA Operador', '1995-10-10', '5551010', 'udica.operador@email.com', '$2y$10$sWqbcayP0TZO95Xoppbn6euAIZ3ZYF9JaHphQGPIbeLNhVx7sPLK2', 73, 'M', 'Técnica', CURDATE(), 'Paula UDICA Operador');




-- ASIGNACION DE ROLES PARA LOS USUARIOS
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (1,1,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (2,2,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (3,3,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (4,4,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (5,5,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (6,6,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (7,7,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (8,8,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (9,9,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (10,10,CURDATE());