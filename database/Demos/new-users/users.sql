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



-- DATOS APARTE PARA PRUEBAS    
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('502950001604', '20261604', 'Martín', 'Sánchez', '2001-04-12', '55512604', 'martin.sanchez04@email.com', '$2y$10$SzVQ9Y55am2gf66wxlubPOSJ4mWljOZYqNj3b07fEhOF7lHMxqYA.', 73, 'H', 'Bachiller', CURDATE(), 'Martín Sánchez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('603061001705', '20261705', 'Isabella', 'Rodríguez', '2003-08-27', '55512705', 'isabella.rodriguez05@email.com', '$2y$10$2zkmfTxeA9wVNfkkhqYM7ONQgZyQpnQBfpPZb6A5OZtYdFwjrVyDS', 73, 'M', 'Bachiller', CURDATE(), 'Isabella Rodríguez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('704172001806', '20261806', 'Diego', 'Mendez', '2000-12-05', '55512806', 'diego.mendez06@email.com', '$2y$10$1E8qXYCAjRfunntHpYmuj.Njf9Q9ErnAkz1REciIux12ffX8I7Sdy', 73, 'H', 'Bachiller', CURDATE(), 'Diego Mendez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('704172002206', '20261809', 'Juan', 'Mendez', '2000-12-05', '55532806', 'juan.mendez09@email.com', '$2y$10$FTV3bp1o.LTDL713m94MJOVkuX7U8fbNVt4iRsS8oJXGrxybYwlXq', 73, 'H', 'Bachiller', CURDATE(), 'Juan Mendez');




-- ESTUDIANTES PARA PRUEBAS DE EVALUACION DOCENTE
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('805283001907', '20261907', 'Laura', 'Gutierrez', '2001-03-18', '55512907', 'laura.gutierrez07@email.com', '$2y$10$8FnQpL2bKmRx9VcWjZyK4.N5ObT6PqLwXmYhUqVzRsT3ElKnJ9V2a', 73, 'M', 'Bachiller', CURDATE(), 'Laura Gutierrez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('906394002008', '20262008', 'Miguel', 'Herrera', '2002-07-14', '55513008', 'miguel.herrera08@email.com', '$2y$10$PjQmN8xYzLw2HsKvFtGqO.MaCdBpRnVwXnJvKuLtUpS4DmKjI5Rka', 73, 'H', 'Bachiller', CURDATE(), 'Miguel Herrera');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('007505002109', '20262109', 'Natalia', 'Chavez', '2000-11-22', '55513109', 'natalia.chavez09@email.com', '$2y$10$VdXnRwQmJoP7TsKuYzLpR.NqQrStUvWxYmZaKbCdEfFgHiJlM2NoG', 73, 'M', 'Bachiller', CURDATE(), 'Natalia Chavez');




-- PRUEBAS FINALES PARA PROCESO DE GRADUACION
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('209727002311', '20262311', 'Elena', 'Martinez', '2002-08-15', '55513311', 'elena.martinez11@email.com', '', 73, 'M', 'Bachiller', CURDATE(), 'Elena Martinez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('310838002412', '20262412', 'Roberto', 'Garcia', '2000-02-20', '55513412', 'roberto.garcia12@email.com', '$2y$10$rB0cBZPmitCTZlvJmpaSr.Y6WCTdxtPU4RsAbVnmNVtBfAX8.gVOq', 73, 'H', 'Bachiller', CURDATE(), 'Roberto Garcia');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('411949002513', '20262513', 'Camila', 'Lopez', '2001-11-03', '55513513', 'camila.lopez13@email.com', '$2y$10$E7aXdqx1xjtaPlP7dGpdNu3Lqf1PK7wdLY3GB1hoQAzkBGMKnfj92', 73, 'M', 'Bachiller', CURDATE(), 'Camila Lopez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('513050002614', '20262614', 'Andres', 'Torres', '2003-06-12', '55513614', 'andres.torres14@email.com', '$2y$10$/J5zOV1gj8X/iSV.TN7eKOYyGJqY0szlexeXOUQEIiBVEdbjQi0m.', 73, 'H', 'Bachiller', CURDATE(), 'Andres Torres');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('614161002715', '20262715', 'Valentina', 'Ramirez', '2002-09-28', '55513715', 'valentina.ramirez15@email.com', '$2y$10$0hvAJaUoOvueVZSrCGhbaOZUAlC.zTOthqT1CPyLUgkXboax6tCIe', 73, 'M', 'Bachiller', CURDATE(), 'Valentina Ramirez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('715272002816', '20262816', 'Victor', 'Flores', '2001-03-07', '55513816', 'victor.flores16@email.com', '$2y$10$IgBlDNCaSloyTZEUCqPpDuyBs5i0FwaqvYsyLw7EDnAEWM.ECT4f6', 73, 'H', 'Bachiller', CURDATE(), 'Victor Flores');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('816383002917', '20262917', 'Adriana', 'Cruz', '2000-07-19', '55513917', 'adriana.cruz17@email.com', '$2y$10$dQu2r0F4np/wdP.mhfNDB.1C42OwoSoljdQqwWxuuU5cQCvWYyjmK', 73, 'M', 'Bachiller', CURDATE(), 'Adriana Cruz');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('917494003018', '20263018', 'Guillermo', 'Perez', '2002-01-25', '55514018', 'guillermo.perez18@email.com', '$2y$10$V0NBIPWvZ.ovmb07OXPu4OeQqVvjT.7SLwYamfzT0LkOiNfpPU1lu', 73, 'H', 'Bachiller', CURDATE(), 'Guillermo Perez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('018505003119', '20263119', 'Mariana', 'Salazar', '2001-10-30', '55514119', 'mariana.salazar19@email.com', '$2y$10$0ESpjsfA5o9mwgOyh8nLUObFBVraMaULek2w.hIN/BpUdlUpyYjma', 73, 'M', 'Bachiller', CURDATE(), 'Mariana Salazar');



-- NUEVOS USUARIOS DE PRUEBA
INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('119616003220', '20263220', 'Fernando', 'Aguilar', '2002-04-14', '55514220', 'fernando.aguilar20@email.com', '$2y$10$pty6.OFhHQ/vF0jG7NLYJuIMy06uZj6.Ioe2rF1T4901zyvi31zz6', 73, 'H', 'Bachiller', CURDATE(), 'Fernando Aguilar');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('220727003321', '20263321', 'Daniela', 'Espinoza', '2000-09-08', '55514321', 'daniela.espinoza21@email.com', '$2y$10$.B7v.ufQFkyt5qTxkW9bSO5C0gR/hdNvMvHhcUXyfz9H4k3kdWEAW', 73, 'M', 'Bachiller', CURDATE(), 'Daniela Espinoza');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('321838003422', '20263422', 'Hector', 'Velasquez', '2001-12-16', '55514422', 'hector.velasquez22@email.com', '$2y$10$E1o6V6S5wTq9HCmIsinzhOAsiFyCvUraqslQl8JLi57/PMzB1iFvO', 73, 'H', 'Bachiller', CURDATE(), 'Hector Velasquez');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('422949003523', '20263523', 'Carolina', 'Miranda', '2002-05-22', '55514523', 'carolina.miranda23@email.com', '$2y$10$TnVYXEgRJ4rGparrwfTuA.RemwgObkWjC9nE.HB7tAyBIpQWg5D7e', 73, 'M', 'Bachiller', CURDATE(), 'Carolina Miranda');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('524050003624', '20263624', 'Raul', 'Muniz', '2000-10-11', '55514624', 'raul.muniz24@email.com', '$2y$10$zU6lxYs8LmU42fZg07x/.eMX/udE3d2A.xK3IAEl0LXuFrGVfFWjK', 73, 'H', 'Bachiller', CURDATE(), 'Raul Muniz');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('625161003725', '20263725', 'Patricia', 'Rios', '2001-07-29', '55514725', 'patricia.rios25@email.com', '$2y$10$X04sv/jAuctVL3yr5Sf/See42WnCeycxgJFglK/1daK6h8KwJaqLy', 73, 'M', 'Bachiller', CURDATE(), 'Patricia Rios');

INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('726272003826', '20263826', 'Sebastian', 'Vargas', '2002-11-05', '55514826', 'sebastian.vargas26@email.com', '$2y$10$7gQvR6sT8bU9vW0xYzZaBcCdEfGhIjKlMnOpQrStUvWxYzAbCdEf', 73, 'H', 'Bachiller', CURDATE(), 'Sebastian Vargas');

-- ASIGNACION DE ROLES PARA LOS NUEVOS USUARIOS
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (21,6,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (22,6,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (23,6,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (24,6,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (25,6,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (26,6,CURDATE());
INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (27,6,CURDATE());