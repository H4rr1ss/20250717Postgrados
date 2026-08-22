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
