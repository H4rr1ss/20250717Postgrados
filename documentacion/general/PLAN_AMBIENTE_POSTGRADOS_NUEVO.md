# Plan: levantar `postgrados_nuevo` como ambiente Docker independiente

> Alcance: **solo se toca `postgrados_nuevo/`**. El repo principal (raíz del proyecto) no se modifica. No se agregan permisos, extensiones ni ajustes al `Dockerfile` — se levanta el ambiente tal como está actualmente, únicamente con otro puerto y otra base de datos para no chocar con el proyecto actual.

## 1. Objetivo

Levantar el proyecto de `postgrados_nuevo/` en su propio contenedor Docker, en paralelo al proyecto actual, usando:

- Otro puerto web (no `8080`).
- Otra base de datos/puerto de MySQL (no `3307`), completamente separada de la BD del proyecto actual.

Esto es para usarlo como base de una guía de paso a producción, probando el proyecto "tal cual está" sin intervenciones adicionales.

## 2. Cambio aplicado en `postgrados_nuevo/docker-compose.yml`

Se agregó un nombre de proyecto Compose explícito (para que contenedores/red/volúmenes no choquen por nombre con el proyecto actual) y se remapearon los puertos:

| | Antes | Ahora |
|---|---|---|
| Proyecto Compose | (implícito, nombre de carpeta) | `postgrados_nuevo` |
| Web | `8080:80` | `8081:80` |
| MySQL | `3307:3306` | `3308:3306` |

El resto del archivo (imagen, variables de entorno, volúmenes) queda igual que estaba.

## 3. Base de datos: ya está separada

`postgrados_nuevo` tiene su **propio servicio `db`** (contenedor y volumen `db_data` distintos a los del proyecto actual, por estar en un stack Compose separado). No comparte datos con la BD del proyecto actual.

`postgrados_nuevo/config/autoload/local.php` **ya existe** y ya apunta correctamente a ese servicio (`host=db` en `global.php`, usuario `root`/`rootpassword` que coincide con `MYSQL_ROOT_PASSWORD` del `docker-compose.yml`). No requiere cambios.

## 4. Pasos para levantar el ambiente

```bash
cd postgrados_nuevo

# Construir y levantar los contenedores (usa docker-compose.yml de esta carpeta)
docker-compose up -d --build

# Instalar dependencias PHP dentro del contenedor
docker-compose exec web composer install

# Importar el dump de base de datos inicial en el nuevo contenedor MySQL
docker-compose exec -T db mysql -u root -prootpassword db_postgrados < database/20250718Postgrados.sql
```

App disponible en **http://localhost:8081**, base de datos en el puerto **3308** — corriendo en paralelo al proyecto actual (`http://localhost:8080` / puerto `3307`), sin interferir entre sí.

## 5. Verificación

- [ ] `docker-compose ps` (dentro de `postgrados_nuevo/`) muestra `web` y `db` levantados sin error de puerto ocupado.
- [ ] El proyecto actual (`http://localhost:8080`) sigue funcionando sin cambios mientras el nuevo está levantado.
- [ ] `http://localhost:8081` carga el login del proyecto nuevo.
- [ ] La conexión a BD funciona (si falla por driver/extensión de PHP faltante, es un tema propio del `Dockerfile` de `postgrados_nuevo` tal como está actualmente — no se corrige aquí porque el alcance es solo levantar el ambiente sin modificarlo).

## 6. Notas

- No se modificó nada dentro del repo principal (raíz del proyecto).
- No se tocó el `Dockerfile` de `postgrados_nuevo` (extensiones/permisos quedan exactamente como están).
- `postgrados_nuevo` no está bajo git (aparece como no rastreado en el repo principal), así que estos cambios solo existen en esa carpeta local.
- Para detener este ambiente sin afectar el actual: `cd postgrados_nuevo && docker-compose down` (agregar `-v` si además se quiere borrar el volumen de BD).
