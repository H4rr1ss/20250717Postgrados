INSERT INTO usuario (cui, registro_academico, nombres, apellidos, fecha_nacimiento, telefono, correo, contrasenia, cod_pais, sexo, grado_academico, fecha_creacion, nombre_completo)
VALUES ('1000000000002', '20250002', 'Ana', 'Director', '1985-02-02', '5551002', 'asistente@email.com', '$2y$10$unJDDkwLcQvySXO7yvvY2uKnVIsAFGnkwPCSk1pM1Lti1Go1LIUAW', 73, 'M', 'Licenciada', CURDATE(), 'Ana Director');


INSERT INTO usuario_rol (cod_usuario, cod_rol, fecha_inicio) VALUES (3524,1,CURDATE());