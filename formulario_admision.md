no se podra editar el formulario, eso quiere decir que ya habra una platilla definida para el formulario, de base seran estos:
- foto de DPI
- Nombres
- Apellidos
- Correo electrónico
- Teléfono
- CUI
- Fecha de Nacimiento
- Pasaporte
- Sexo
- País de nacionalidad
- Grado académico a ingresar
- Carrera a ingresar
- Grado académico que posee

- labora actualmente?
- Ubicación laboral
- Hora de inicio
- Hora de salida
- Dias que labora

#### La cohorte se toma automaticamente porque el formulario esta relacionado con una. 
#### Los datos de laborar son opcionales en caso no se selecciona que labora
#### Los datos de laborar son obligatorios en caso se selecciona que si labora
#### El campo de pasaporte es opcional


```SQL
CREATE TABLE formulario_admision (
    id_formulario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    id_cohorte INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio_admision DATETIME NOT NULL,
    fecha_fin_admision DATETIME NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    creado_por INT,
    FOREIGN KEY (id_cohorte) REFERENCES cohorte(id_cohorte),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario)
);
```

```SQL
CREATE TABLE campo_formulario (
    id_campo INT AUTO_INCREMENT PRIMARY KEY,
    id_formulario INT NOT NULL,

    nombre_campo VARCHAR(100) NOT NULL,
    etiqueta VARCHAR(200) NOT NULL,
    tipo_campo ENUM('texto', 'email', 'telefono', 'select', 'textarea', 'fecha', 'archivo', 'boolean', 'time') NOT NULL,
    opciones TEXT,-- En caso sea un select
    requerido BOOLEAN DEFAULT FALSE,
    orden_campo INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,

    FOREIGN KEY (id_formulario) REFERENCES formulario_admision(id_formulario)
);
```

```SQL
CREATE TABLE respuesta_aspirante (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_formulario INT NOT NULL,
    aspirante_id INT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (aspirante_id) REFERENCES aspirante(id),
    FOREIGN KEY (id_formulario) REFERENCES formulario_admision(id_formulario),
);
```

```SQL
CREATE TABLE aspirante (
    id INT PRIMARY KEY,
    cui VARCHAR(20) NOT NULL UNIQUE,
    photo_dpi VARCHAR(255) NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    correo_electronico VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NOT NULL, 
);
```
