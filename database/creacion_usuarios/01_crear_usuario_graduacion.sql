-- =====================================================
-- SCRIPT: Crear Usuario para Probar Módulo de Graduación
-- =====================================================
-- Fecha: 2026-05-17
-- Descripción: Crea un usuario estudiante con permisos para acceder 
-- al Módulo de Graduación (Proceso de Graduación del Estudiante)
--
-- INSTRUCCIONES:
-- 1. Ejecutar este script en la base de datos db_postgrados
-- 2. El usuario se crea con rol de Estudiante (cod_rol = 6)
-- 3. El Director puede asignarle el proceso de graduación desde:
--    Módulo de Graduación > Gestión de Exámenes
--
-- CONTRASEÑA POR DEFECTO: graduacion2026
-- =====================================================

-- -----------------------------------------------------
-- PASO 1: Crear el usuario base (Estudiante de Prueba)
-- -----------------------------------------------------
INSERT INTO usuario (
    cui, 
    registro_academico, 
    nombres, 
    apellidos, 
    fecha_nacimiento, 
    telefono, 
    correo, 
    contrasenia, 
    cod_pais, 
    sexo, 
    grado_academico, 
    fecha_creacion, 
    nombre_completo
) VALUES (
    '1000000009999',              -- cui: Código Único de Identificación (único)
    '20259999',                   -- registro_academico: Número de registro académico (único)
    'Estudiante',                 -- nombres
    'Graduacion Prueba',          -- apellidos
    '1995-01-01',                 -- fecha_nacimiento
    '55519999',                   -- telefono
    'estudiante.graduacion@email.com',  -- correo (puede usarse para login)
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- contrasenia: bcrypt hash de 'graduacion2026'
    73,                           -- cod_pais: 73 = Guatemala
    'M',                          -- sexo: 'M' = Mujer, 'H' = Hombre
    'Licenciado(a)',              -- grado_academico
    CURDATE(),                    -- fecha_creacion: fecha actual
    'Estudiante Graduacion Prueba' -- nombre_completo
);

-- Obtener el ID del usuario recién creado
SET @cod_usuario_graduacion = LAST_INSERT_ID();

-- -----------------------------------------------------
-- PASO 2: Asignar rol de Estudiante (cod_rol = 6)
-- -----------------------------------------------------
-- Roles disponibles:
-- 1 = Director, 2 = Asistente, 3 = Tesorero, 4 = Coordinador, 5 = Catedrático
-- 6 = Estudiante, 7 = Programador, 8 = UDICA Programador, 9 = UDICA Jefe, 10 = UDICA Operador
INSERT INTO usuario_rol (
    cod_usuario, 
    cod_rol, 
    fecha_inicio
) VALUES (
    @cod_usuario_graduacion,  -- cod_usuario: ID del usuario creado arriba
    6,                        -- cod_rol: 6 = Estudiante (requerido para Proceso de Graduación)
    CURDATE()                 -- fecha_inicio: fecha actual
);

-- -----------------------------------------------------
-- PASO 3: Verificar la creación del usuario
-- -----------------------------------------------------
SELECT 
    u.cod_usuario,
    u.nombres,
    u.apellidos,
    u.correo,
    u.registro_academico,
    r.cod_rol,
    r.nombre as nombre_rol,
    ur.fecha_inicio
FROM usuario u
JOIN usuario_rol ur ON u.cod_usuario = ur.cod_usuario
JOIN rol r ON ur.cod_rol = r.cod_rol
WHERE u.cod_usuario = @cod_usuario_graduacion;

-- =====================================================
-- DATOS DE ACCESO PARA PRUEBAS:
-- =====================================================
-- Usuario (login): estudiante.graduacion@email.com
--                 o 1000000009999 (CUI)
--                 o 20259999 (Registro Académico)
-- Contraseña: graduacion2026
--
-- PERMISOS DEL USUARIO:
-- - Inscripción de cursos (primer año)
-- - Asignación de cursos
-- - Ver cursos asignados y oficializados
-- - Órdenes de pago
-- - PROCESO DE GRADUACIÓN (Módulo de Graduación > Proceso de Graduación)
--
-- =====================================================
-- PARA EL DIRECTOR: Asignar Proceso de Graduación
-- =====================================================
-- Una vez creado el estudiante, el Director puede:
--
-- 1. Ingresar al sistema como Director
-- 2. Ir a: Módulo de Graduación > Gestión de Exámenes
-- 3. Buscar el estudiante y asignarle:
--    - Tipo de examen (Privado/General)
--    - Fechas de examen
--    - Tribunal examinador (Carta de Examinadores)
--    - Autorización de impresión de tesis/documentos
--
-- NOTA: Para que el estudiante aparezca en el módulo de graduación,
-- debe estar inscrito en el plan de estudios y tener un proceso 
-- de graduación activo asignado por el Director.
--
-- =====================================================
-- CÓMO GENERAR NUEVOS HASH DE CONTRASEÑA (si se necesita cambiar):
-- =====================================================
-- Si necesitas generar un nuevo hash de contraseña, usa PHP:
--
-- <?php
-- use Zend\Crypt\Password\Bcrypt;
-- 
-- $bcrypt = new Bcrypt();
-- $hash = $bcrypt->create('nueva_contraseña');
-- echo $hash;
-- ?>
--
-- O en línea de comandos de PHP:
-- php -r "echo password_hash('nueva_contraseña', PASSWORD_BCRYPT);"
-- =====================================================

-- -----------------------------------------------------
-- PASO 4 (OPCIONAL): Insertar datos adicionales necesarios
-- para el proceso de graduación
-- -----------------------------------------------------
-- Si el módulo de graduación requiere que el estudiante
-- tenga datos de tesis/proyecto asignados, el Director debe:
--
-- 1. Crear/Asignar el proceso de graduación desde la interfaz web
-- 2. Esto creará automáticamente los registros en las tablas:
--    - proceso_graduacion
--    - examen_privado / examen_general
--    - fases_examen (si aplica)
--
-- Las tablas del módulo de graduación están en: database/modulo graduacion/
-- - modulo_graduacion.sql
-- - modulo_graduacion_carta_01_schema.sql
-- - modulo_graduacion_carta_02_seeds.sql
-- - modulo_autorizacion_impresion_schema.sql
