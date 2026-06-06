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


-- ESTUDIANTES:
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('100000001231', '20261006', 'Javier', 'Corleto', '2001-09-01', '55510065', 'javiC@email.com', '$2y$10$60Xp29rKjbsBdAlRvTReyeXC9caP5gyUGsnWSTu3r/WAPA9eQDu8m', 73, 'M', 'Bachiller', CURDATE(), 'Javier Corleto');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('100000001232', '20262006', 'Sonia', 'Gomez', '2002-06-04', '5551004', 'sogomez@email.com', '$2y$10$kWRqQMePwGfG13Zj3Gj3yevmxDsVCbFUmyRRNyS4umD.pMS6kvwAe', 73, 'M', 'Bachiller', CURDATE(), 'Sonia Gomez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('100000001233', '20263006', 'Gerber', 'Lopez', '2001-02-06', '5551003', 'geblopexz@email.com', '$2y$10$Rg8vuzcExvLC/UQ6.E.N7OmFcAgKjYAQBnjUJW1imiYtvSE3B8Yty', 73, 'M', 'Bachiller', CURDATE(), 'Gerber Lopez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('100000001234', '20264006', 'Estuardo', 'Utz', '2002-09-01', '55510022', 'utzEste@email.com', '$2y$10$9lIZeRJagds6rp4Ijzyiuu97ZV8Vcr/SCA6vjzzVOa8OQoJUzu2UO', 73, 'M', 'Bachiller', CURDATE(), 'Estuardo Utz');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('100000001235', '20265006', 'Josue', 'Estrada', '2001-02-02', '55510061', 'josueE@email.com', '$2y$10$MLyPKxhPTn4oE.0ejgSLNuxcbIjDWnW3Z.n79RpxFAbMlT3w.Upe6', 73, 'M', 'Bachiller', CURDATE(), 'Josue Estrada');

-- NUEVOS ESTUDIANTES PARA TESTING DE CORREOS

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('100012001233', '20263318', 'Damian', 'Peña', '2001-05-01', '5551003', '3601320810101@ingenieria.usac.edu.gt', '$2y$10$ibFZlD20DLfsxDhpS4OQp.b68v0.832z5RWnRaBS34B9uvXsZcvUy', 73, 'M', 'Bachiller', CURDATE(), 'Damian Peña');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('100140001234', '20264224', 'Pablo', 'Reyes', '2003-01-03', '55510022', 'h4rrissss.gs@email.com', '$2y$10$bDAi2MGqZMNZDl.EaiOstuQvYIrVL9wPnJog28LSJUtd.rdWMXDua', 73, 'M', 'Bachiller', CURDATE(), 'Pablo Reyes');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('100430001235', '20265135', 'Samuel', 'Zea', '2000-01-09', '55510061', 'sisalesa19@gmail.com', '$2y$10$0QJqbCHy1WPvG0bbRN.0ue5VpHfR4hdeZct4u6Qno4F1/gkuThW/y', 73, 'M', 'Bachiller', CURDATE(), 'Samuel Zea');


INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('36013209999', '202109918', 'Harry', 'Zea', '2002-01-09', '55510061', 'sisalesa19@gmail.com', '$2y$10$dGQgiklBYLwQHBd3id9OkOhYNPxH8yxZ9a7pEaCO2hxyqDflMHpWe', 73, 'M', 'Bachiller', CURDATE(), 'Harry Zea');

-- USUARIOS PARA DEMO DE EVALUACION DOCENTE
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('200530001301', '20261301', 'Andrea', 'Morales', '2001-03-15', '55512301', 'andrea.morales01@email.com', '$2y$10$GirD4f21n.TtbA35cUbiA.B.AJ4H7MW.eOJTnEhyEQk97jekOSRo2', 73, 'M', 'Bachiller', CURDATE(), 'Andrea Morales');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('300720001402', '20261402', 'Ricardo', 'Fuentes', '2000-07-22', '55512402', 'ricardo.fuentes02@email.com', '$2y$10$OwWjZ85gbmLcnSmD3NTQHO1WWkx/Lz0PTSw5u5KA156WLuId9lPMa', 73, 'H', 'Bachiller', CURDATE(), 'Ricardo Fuentes');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('401840001503', '20261503', 'Valeria', 'Castillo', '2002-11-08', '55512503', 'valeria.castillo03@email.com', '$2y$10$4jDrcf1XrpXsTwHQpdTgIO4uI0yF7LRJta4Zx8KPsGksMkrRN7Gqu', 73, 'M', 'Bachiller', CURDATE(), 'Valeria Castillo');