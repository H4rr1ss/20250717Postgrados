# AGENTS.md

Zend Framework 3 MVC app (PHP 7.4) for university postgraduate management. Runs on Apache via Docker. README is the upstream ZF skeleton README — most of it does not reflect this project. Trust this file plus `DOCUMENTACION_PROYECTO.md` and `EXPLICACION_RUTAS_ZF3.md`.

## Modules

Enabled in `config/modules.config.php`: `Application`, `Eep`, `RyE`, `SIIF`.

- **`module/Eep/`** — the real app: users, roles, inscriptions, timetables, grades, treasury, graduation, evaluations, etc. ~17 controllers in `src/Controller/`. Almost all new work lives here.
- `module/Application/` — base/landing only.
- `module/RyE/`, `module/SIIF/` — smaller domain modules (Model-only, no controllers).

PSR-4 (`composer.json`): `Application\\`, `Eep\\`, `RyE\\`, `SIIF\\` → `module/*/src/`. Note `SIIF` maps to `module/SIIF/src` (no trailing slash, also missing `\\` in JSON — leave as-is).

## Dev environment

Use Docker, not the PHP built-in server (sessions, file paths and `data/` perms depend on the container layout).

```bash
./init-docker-env.sh          # first-time setup: build, perms, composer install
docker-compose up -d --build  # subsequent
docker-compose exec web bash
docker-compose exec web composer install
```

- App: http://localhost:8080 (Apache, DocumentRoot `/var/www/public`)
- MySQL 5.7 on host port **3307** (container 3306). DB `db_postgrados`, user `user`/`password`, root `rootpassword`.
- DB is **not** auto-imported. Manually load `database/20250718Postgrados.sql` and `database/modulo_graduacion.sql` into `db_postgrados`. The `./database:/docker-entrypoint-initdb.d` mount in `docker-compose.yml` is commented out.
- Sessions live in `/var/www/data/sessiones` owned by `www-data` with mode `1733` — `init-docker-env.sh` fixes this; recreate it if you wipe `data/`.
- Xdebug: VSCode `launch.json` listens on port 9003.

## Commands

```bash
composer cs-check     # phpcs (PSR2 + short arrays + ZF tweaks) over config/, module/, public/index.php
composer cs-fix       # phpcbf
composer test         # phpunit — only module/Application/test is wired in phpunit.xml.dist
composer development-enable|disable|status
```

There is no lint/typecheck pipeline beyond `phpcs`. No CI workflows. The `Eep` tests in `module/Eep/tests/` are **not** registered in `phpunit.xml.dist`.

## ZF3 routing gotcha (read before adding/renaming an action)

`EXPLICACION_RUTAS_ZF3.md` is required reading. Any new/renamed controller action must be updated in **all four** places or it silently breaks:

1. Method name in the controller (`fooAction`).
2. `module/Eep/config/menus.php` — menu entries reference the action by name.
3. `module/Eep/config/access_filter.php` — role-based ACL list; missing entries deny access.
4. View template at `module/Eep/view/eep/<controller>/<action-in-kebab-case>.phtml`.

Routes are defined in `module/Eep/config/module.config.php`.

## Architecture conventions

- Business logic goes in `module/Eep/src/Service/*Manager` classes, not in controllers.
- Controllers are wired through factories in `module/Eep/src/Controller/Factory/` (constructor DI via ServiceManager).
- Entities in `module/Eep/src/Entity/`, Forms in `module/Eep/src/Form/`, VOs in `module/Eep/src/ValueObject/`.
- `RyE` and `SIIF` only expose `Model/` — they are data-access modules consumed elsewhere.
- New permissions/actions sometimes require a SQL insert into `accion` (see `CAMBIOS.md` for the pattern).

## Style

- PHP 7.4 syntax (Docker image is `php:7.4-apache`). `composer.json` still says `^5.6 || ^7.0` — ignore.
- PSR-2 + **short array syntax** (`[]`, not `array()`) enforced by `phpcs.xml`.
- `public/index.php` is excluded from the `PSR1.Files.SideEffects` rule.

## Things to ignore / not "fix"

- Upstream skeleton files (`README.md`, `TODO.md`, `Vagrantfile`, `CONDUCT.md`, `CONTRIBUTING.md`, `LICENSE.md`) describe `zendframework/skeleton-application`, not this project.
- `.DS_Store`, `nbproject/` are stray.
- `paso3-terna-tasks.json`, `guardados/`, `new-users/`, `*.md` notes at root are scratch/work-in-progress context, not config.
