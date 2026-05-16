# scheduledTask

Tareas programadas (cron) del proyecto. Ejecutar desde el contenedor web.

## `updateOrders.php`

Refresca el estado de las órdenes de pago vencidas y sincroniza usuarios
SEEP→SATU. Lanzado por HTTP contra `postgrados.farusac.edu.gt`.

```bash
docker compose exec web php /var/www/scheduledTask/updateOrders.php
```
