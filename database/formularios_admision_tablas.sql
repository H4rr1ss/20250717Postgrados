-- Tablas para el módulo de Formularios de Admisión
-- Estructura EXACTA proporcionada por el usuario

CREATE TABLE formulario_admision (
    id_formulario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    fecha_cohorte DATE NOT NULL,
    fecha_creacion datetime DEFAULT current_timestamp(),
    fecha_inicio_admision DATETIME NOT NULL,
    fecha_fin_admision DATETIME NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    creado_por INT,

    CONSTRAINT unq_cohorte_activo UNIQUE (fecha_cohorte, activo),
    FOREIGN KEY (fecha_cohorte) REFERENCES cohorte(fecha_cohorte),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario)
);

CREATE TABLE campo_formulario (
    id_campo INT AUTO_INCREMENT PRIMARY KEY,
    id_formulario INT NOT NULL,
    nombre_campo VARCHAR(100) NOT NULL,
    etiqueta VARCHAR(200) NOT NULL,
    tipo_campo ENUM('texto', 'email', 'telefono', 'select', 'textarea', 'fecha', 'archivo', 'boolean', 'time') NOT NULL,
    opciones TEXT, -- En caso sea un select
    requerido BOOLEAN DEFAULT FALSE,
    orden_campo INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_formulario) REFERENCES formulario_admision(id_formulario)
);

CREATE TABLE aspirante (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cui VARCHAR(20) NOT NULL UNIQUE,
    photo_dpi VARCHAR(255) NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    correo_electronico VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NOT NULL
);

CREATE TABLE respuesta_aspirante (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_formulario INT NOT NULL,
    aspirante_id INT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aspirante_id) REFERENCES aspirante(id),
    FOREIGN KEY (id_formulario) REFERENCES formulario_admision(id_formulario)
);

-- Tabla adicional necesaria para las respuestas individuales por campo
CREATE TABLE respuesta_campo (
    id_respuesta_campo INT AUTO_INCREMENT PRIMARY KEY,
    id_respuesta INT NOT NULL,
    id_campo INT NOT NULL,
    valor_respuesta TEXT,
    archivo_adjunto VARCHAR(255),
    FOREIGN KEY (id_respuesta) REFERENCES respuesta_aspirante(id_respuesta) ON DELETE CASCADE,
    FOREIGN KEY (id_campo) REFERENCES campo_formulario(id_campo) ON DELETE CASCADE,
    UNIQUE KEY unique_respuesta_campo (id_respuesta, id_campo)
);
