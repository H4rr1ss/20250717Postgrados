# 📁 Documentación y Scripts del Módulo de Graduación

Esta carpeta contiene toda la documentación, scripts y herramientas necesarias para inicializar, configurar y verificar el módulo de graduación del sistema de postgrados.

---

## 📑 Contenido de la Carpeta

### 📚 Documentación

1. **[CHECKLIST_MODULO_GRADUACION.md](CHECKLIST_MODULO_GRADUACION.md)** ⭐ **EMPIEZA AQUÍ**
   - Lista de verificación paso a paso
   - Método automático y manual
   - Tiempos estimados: 10-40 minutos
   - Resolución de problemas comunes

2. **[MODULO_GRADUACION_REQUISITOS_INICIALES.md](MODULO_GRADUACION_REQUISITOS_INICIALES.md)**
   - Documentación técnica exhaustiva
   - Estructura de carpetas detallada
   - 27 tablas de base de datos explicadas
   - Configuración PHP y SMTP
   - Arquitectura de servicios

### 🔧 Scripts Ejecutables

3. **[inicializar-modulo-graduacion.sh](inicializar-modulo-graduacion.sh)** 🚀
   - Script interactivo de inicialización
   - Crea estructura de carpetas
   - Instala base de datos
   - Configura permisos
    - **Uso:** `cd .. && ./documentacion/modulo-graduacion/inicializar-modulo-graduacion.sh`

4. **[verificar-modulo-graduacion.sh](verificar-modulo-graduacion.sh)** ✅
   - Verificación automática completa
   - Revisa carpetas, archivos, BD, permisos
   - Reporte detallado con errores/warnings
    - **Uso:** `cd .. && ./documentacion/modulo-graduacion/verificar-modulo-graduacion.sh`

---

## 🚀 Inicio Rápido

> **El script de inicialización SOLO crea carpetas y permisos.**  
> La instalación completa (BD, dependencias, SMTP, archivos, verificación) se realiza **manualmente** siguiendo el `CHECKLIST_MODULO_GRADUACION.md`.

### 1. Crear carpetas y permisos (automático)

```bash
# Desde el directorio raíz del proyecto
cd /home/harris/Escritorio/20250717Postgrados

# Ejecutar script de inicialización (SOLO estructura de carpetas)
./documentacion/modulo-graduacion/inicializar-modulo-graduacion.sh
```

### 2. Completar instalación manual

Seguir paso a paso el archivo **`CHECKLIST_MODULO_GRADUACION.md`**:
- Copiar plantilla `general.docx`
- Ejecutar scripts SQL de base de datos
- Instalar dependencias Composer
- Configurar SMTP
- Reiniciar servicios

### 3. Verificar Instalación

```bash
# Ejecutar verificación automática
./documentacion/modulo-graduacion/verificar-modulo-graduacion.sh
```

---

## 📊 ¿Qué Instalará?

Al ejecutar los scripts, se creará:

- ✅ **6 carpetas** en `data/graduacion/`
- ✅ **27 tablas** en base de datos (23 módulo + 4 matriz)
- ✅ **11 pasos** del proceso de graduación
- ✅ **1 rol nuevo:** Secretario de Examen Privado (cod_rol=11)
- ✅ **1 usuario:** `secretario.examen@farusac.edu.gt` / `PostgradosUsac2024`
- ✅ **20 matrices** de evaluación pre-configuradas
- ✅ **Dependencias PHP:** zend-mail y otros

---

## 📂 Estructura de Carpetas que se Creará

```
data/graduacion/
├── plantillas/
│   └── carta-examinadores/
│       └── general.docx          ← ¡TÚ DEBES COLOCAR ESTE ARCHIVO!
├── procesos/                     ← (se crea dinámicamente por proceso)
└── global/
    ├── documentos-soporte/       ← Logos, guías (paso 6)
    ├── cartas-descarga/          ← Cartas .docx descargables
    └── requisitos-apoyo/         ← Archivos de apoyo
```

---

## ⚠️ Archivo CRÍTICO Requerido

**SIN ESTE ARCHIVO EL MÓDULO NO FUNCIONA:**

📄 **general.docx** — Plantilla de carta de examinadores

**Ubicación:** `data/graduacion/plantillas/carta-examinadores/general.docx`

**Debe contener placeholders:**
- `${estudiante_nombre}`
- `${titulo_trabajo}`
- `${fecha_examen}`
- `${examinador_1_nombre}`
- Y otros (ver documentación completa)

---

## 🎯 Orden de Lectura Recomendado

1. Este README (ya estás aquí ✅)
2. [CHECKLIST_MODULO_GRADUACION.md](CHECKLIST_MODULO_GRADUACION.md) — Sigue el checklist
3. Ejecuta los scripts
4. [MODULO_GRADUACION_REQUISITOS_INICIALES.md](MODULO_GRADUACION_REQUISITOS_INICIALES.md) — Para consultas técnicas

---

## 🔍 Verificación Rápida Manual

```bash
# Verificar carpetas creadas
ls -la data/graduacion/

# Verificar plantilla existe (CRÍTICO)
ls -la data/graduacion/plantillas/carta-examinadores/general.docx

# Verificar tablas en BD
docker-compose exec db mysql -u user -ppassword db_postgrados \
  -e "SHOW TABLES LIKE 'examen_%';"

# Verificar pasos (debe mostrar 11)
docker-compose exec db mysql -u user -ppassword db_postgrados \
  -e "SELECT numero_orden, fase, nombre FROM examen_paso_catalogo ORDER BY cod_paso;"
```

---

## 🎓 Credenciales de Acceso

Después de la instalación:

- **URL:** http://localhost:8080
- **Usuario:** `secretario.examen@farusac.edu.gt`
- **Contraseña:** `PostgradosUsac2024`

---

## 📚 Documentación Adicional del Proyecto

Otros archivos relevantes en el directorio raíz:

- `documentacion/general/ESTRUCTURA_ARCHIVOS_GRADUACION.md` — Estructura detallada de archivos
- `documentacion/general/EXPLICACION_RUTAS_ZF3.md` — Convenciones de desarrollo
- `AGENTS.md` — Arquitectura general del proyecto

---

## ⏱️ Tiempo Estimado

- **Con scripts (automático):** 10-15 minutos
- **Manual (paso a paso):** 30-40 minutos
- **Primera vez (con lectura):** 60 minutos

---

## 🆘 Ayuda

Si encuentras problemas:

1. Revisa la sección "Resolución de Problemas" en [CHECKLIST_MODULO_GRADUACION.md](CHECKLIST_MODULO_GRADUACION.md)
2. Ejecuta `./documentacion/modulo-graduacion/verificar-modulo-graduacion.sh` para diagnóstico
3. Consulta la documentación completa en [MODULO_GRADUACION_REQUISITOS_INICIALES.md](MODULO_GRADUACION_REQUISITOS_INICIALES.md)

---

**Última actualización:** 11 de julio de 2026  
**Estado:** ✅ Documentación completa y organizada
