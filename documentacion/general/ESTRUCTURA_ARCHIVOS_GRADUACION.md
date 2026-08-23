# Estructura de Archivos del Módulo de Graduación

> **Fecha:** 2026-05-29  
> **Ubicación:** `data/graduacion/`  
> **Propósito:** Centralizar todos los archivos del proceso de examen de graduación en un solo lugar, organizados por tipo y proceso.

---

## Árbol de Carpetas

```
data/graduacion/
│
├── plantillas/
│   └── carta-examinadores/
│       ├── general.docx
│       └── README.md
│
├── procesos/
│   └── {cod_proceso}/
│       ├── a1b2c3d4.pdf
│       ├── e5f6g7h8.jpg
│       └── carta-examinadores.docx
│
└── global/
    ├── documentos-soporte/
│   │   ├── md5abc123.pdf
│   │   └── md5def456.jpg
    └── cartas-descarga/
        ├── md5xyz789.docx
        └── ...
```

---

## Descripción por Carpeta

### 1. `plantillas/`

**Contenido:** Archivos `.docx` base utilizados por el sistema para generar cartas y documentos dinámicos.

**Características:**
- Solo lectura
- Gestionados por el administrador
- No se eliminan al borrar un proceso
- Contienen placeholders como `${nombre_estudiante}`, `${fecha_examen}`, etc.

**Ejemplo:**
```
plantillas/
└── carta-examinadores/
    ├── general.docx
    └── README.md
```

---

### 2. `procesos/{cod_proceso}/`

**Contenido:** Todos los archivos pertenecientes a un estudiante/proceso específico.

**Reglas:**
- **Sin subcarpetas:** Todo se mezcla en la misma carpeta del proceso
- **Identificación:** Se realiza mediante la base de datos (`cod_proceso`)
- **Nomenclatura:**
  - Documentos subidos: `<md5>.<ext>` (ej: `a1b2c3d4.pdf`)
  - Cartas generadas: nombre fijo `carta-examinadores.docx`

**Ejemplo para `cod_proceso = 1`:**
```
procesos/1/
├── 3a06a71b9293634420bdcc16e2e034ef.pdf   ← Recibo de pago (paso 1)
├── 4147fa329a38b04beee5f70b307402be.pdf   ← Constancia de cierre (paso 1)
├── c8555e4536650edcc3bb11a61a6d8125.pdf   ← Ejemplar del trabajo (paso 1)
├── f1e2d3c4b5a6.pdf                        ← Evidencia de corrección (paso 5)
├── foto.jpg                                 ← Foto de corrección (paso 5)
└── carta-examinadores.docx                  ← Carta generada (paso 5)
```

**Ventajas:**
- Aislamiento por proceso
- Eliminación completa al borrar un proceso: `rm -rf procesos/1/`
- Sin riesgo de mezclar archivos de diferentes estudiantes

---

### 3. `global/`

**Contenido:** Recursos compartidos entre todos los procesos del paso 6 (Autorización de Impresión).

**Subcarpetas:**

#### `documentos-soporte/`
- Logos de la universidad
- Escudos institucionales
- Guías visuales de formato
- Archivos de referencia para el estudiante

#### `cartas-descarga/`
- Cartas tipo `.docx` que el estudiante puede descargar
- Formatos preestablecidos para llenar
- Plantillas de autorización

**Características:**
- Compartidos entre todos los procesos
- No se eliminan al borrar un proceso
- Gestionados por el administrador desde el panel

---

## Mapeo con la Base de Datos

| Tabla | Descripción | Ruta Relativa en BD |
|-------|-------------|---------------------|
| `examen_carta_plantilla` | Plantilla para cartas | `data/graduacion/plantillas/carta-examinadores/general.docx` |
| `examen_carta_examinadores` | Carta generada | `data/graduacion/procesos/{cod}/carta-examinadores.docx` |
| `archivo_local` | Documento subido por estudiante | `<md5>.<ext>` (resuelto con `cod_proceso`) |
| `examen_correccion_evidencia` | Evidencia de corrección | `<md5>.<ext>` (resuelto con `cod_proceso`) |
| `examen_autorizacion_documento_soporte` | Documento de soporte | `data/graduacion/global/documentos-soporte/<md5>.<ext>` |
| `examen_carta_descarga` | Carta descargable | `data/graduacion/global/cartas-descarga/<md5>.<ext>` |

---

## Cambios desde la Estructura Anterior

| Antes (Ruta) | Ahora (Ruta) |
|--------------|--------------|
| `public/archivos/cartas-examinadores/proceso-3.docx` | `data/graduacion/procesos/3/carta-examinadores.docx` |
| `public/archivos/autorizacion-impresion/documentos-soporte/` | `data/graduacion/global/documentos-soporte/` |
| `public/archivos/autorizacion-impresion/cartas-descarga/` | `data/graduacion/global/cartas-descarga/` |
| `data/plantillas/carta-examinadores/general.docx` | `data/graduacion/plantillas/carta-examinadores/general.docx` |
| `public/archivos/a1b2c3d4.pdf` (archivos sueltos) | `data/graduacion/procesos/{cod}/a1b2c3d4.pdf` |

---

## Ventajas de la Nueva Estructura

- ✅ **Centralización:** Todo el módulo en un solo lugar (`data/graduacion/`)
- ✅ **Aislamiento por Proceso:** Cada estudiante tiene su carpeta dedicada
- ✅ **Simplicidad:** Sin subcarpetas internas complicadas
- ✅ **Seguridad:** Fuera de `public/`, acceso controlado por PHP
- ✅ **Backup Sencillo:** `tar czf backup-graduacion.tar.gz data/graduacion/`
- ✅ **Limpieza Eficiente:** Borrar un proceso completo con `rm -rf procesos/{cod}/`

---

## Comandos Útiles

### Crear estructura en servidor

```bash
mkdir -p /var/www/data/graduacion/plantillas/carta-examinadores
mkdir -p /var/www/data/graduacion/procesos
mkdir -p /var/www/data/graduacion/global/documentos-soporte
mkdir -p /var/www/data/graduacion/global/cartas-descarga
chown -R www-data:www-data /var/www/data/graduacion
chmod -R 755 /var/www/data/graduacion
```

### Backup completo del módulo

```bash
cd /var/www
tar czf backup-graduacion-$(date +%Y%m%d).tar.gz data/graduacion/
```

### Eliminar un proceso completo

```bash
rm -rf /var/www/data/graduacion/procesos/{cod_proceso}
```

---

## Notas para Desarrolladores

- Las rutas en PHP se resuelven usando `dirname($_SERVER['DOCUMENT_ROOT'])` como base del proyecto.
- Nunca hardcodear rutas absolutas; usar las constantes definidas en los managers.
- Al subir un archivo, siempre verificar que la carpeta del proceso exista con `mkdir` recursivo.

---

*Documento generado automáticamente para referencia del equipo de desarrollo.*
