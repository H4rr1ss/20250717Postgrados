# Cambios en el Proyecto — Rama `main`

**Comparación:** Primer commit (`ee5962b`) vs. HEAD actual (`d7baef8`).

---

## Resumen

| Estado | Archivo / Carpeta | Descripción |
|---|---|---|
| **M** | `.gitignore` | Modificado |
| **R100** | `20250718Postgrados` → `20250718Postgrados.sql` | Renombrado (100% igual) |
| **M** | `Dockerfile` | Modificado |
| **A** | `borrar.sql` | **Agregado** |
| **M** | `config/autoload/global.php` | Modificado |
| **M** | `docker-compose.yml` | Modificado |

---

## Árbol Visual

```
.prod/20250717Postgrados
├── [M]  .gitignore
├── [R]  20250718Postgrados.sql  (antes 20250718Postgrados)
├── [M]  Dockerfile
├── [A]  borrar.sql              ← NUEVO
├── [M]  config/autoload/global.php
└── [M]  docker-compose.yml
└── [M]  TimetableManager.php
└── [A]  CAMBIOS_MAIN.md
```

---

## Notas

- El único archivo **nuevo** (`A`) desde el primer commit es `borrar.sql`.
- El resto son modificaciones o el renombrado del dump SQL inicial.
- Los archivos temporales de sesión (`data/sessiones/sess_*`) fueron excluidos del listado.


# Archivos a revisar al migrar modulos a MAIN
## ARCHIVOS QUE NO SE DEBEN ENVIAR CAMBIOS A PROD
- config/autoload/local.php
- TimetableManager.php
- docker-compose.yml
- config/autoload/global.php

## ARCHIVOS/CARPETAS QUE SE COPIAN MANUAL
- config/autoload/local.php

### Carpetas de datos en `.gitignore` — Referencia para revisión

Estas carpetas contienen archivos de runtime subidos por usuarios o generados por la plataforma. No deben versionarse, pero se listan para que al desplegar en producción se verifique que:

1. Los permisos de escritura estén correctos (`www-data` o equivalente).
2. El `.gitkeep` de `data/graduacion/procesos/` se respete para que la carpeta exista al clonar.

| Carpeta | Ignorada | Contenido tipo | Revisar al desplegar |
|---------|----------|----------------|----------------------|
| `data/graduacion/procesos/*` | ✅ Sí | Documentos de estudiantes (PDF, JPG, PNG) por proceso de graduación | Permisos de escritura y existencia del directorio raíz |
| `data/admisiones/*` | ✅ Sí | Archivos adjuntos de formularios de admisión | Permisos de escritura y existencia del directorio raíz |
| `data/sessiones/` | ✅ Sí | Cache de sesiones PHP | Ignorar (se regenera) |
| `data/cache/` | ✅ Sí | Cache de la aplicación | Ignorar (se regenera) |
| `data/logs/` | ✅ Sí | Logs de la aplicación | Ignorar (se regenera) |
| `data/tmp/` | ✅ Sí | Archivos temporales | Ignorar (se regenera) |

> **⚠️ Acción requerida:** `data/graduacion/procesos/.gitkeep` está en ambos entornos y debe seguir estando en `main` para que Git cree la carpeta vacía al clonar. Si se pierde, la subida de documentos de graduación fallará.

# Cosas a preguntar
- que carreras o de donde se obtienen las carreras que salen a elegir por el aspirante a la cual quieren ingresar