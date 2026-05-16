# Aca se registraran los cambios a tener en cuenta al desarrollar las nuevas funcionalidades

Se agrego una nueva acción, "Recupeción de contraseña"
'''
INSERT INTO accion (cod_accion, nombre) VALUES (67, 'Recuperar contraseña');
'''

Módulo de Graduación — Paso 5 "Carta de Examinadores" (2026-05-15).
Modelo: el seguimiento de correcciones se hace por correo externo; en la
plataforma solo se guardan observaciones por ciclo y evidencias (capturas
de correos). No se almacena el trabajo de graduación corregido.
Scripts: `database/modulo_graduacion_carta_01_schema.sql` y
`database/modulo_graduacion_carta_02_seeds.sql`. Acciones agregadas:
```
INSERT INTO accion (cod_accion, nombre) VALUES
  (68, 'Ver paso de carta de examinadores'),
  (69, 'Registrar correcciones al trabajo de graduación'),
  (70, 'Adjuntar evidencia de correo'),
  (71, 'Aprobar trabajo de graduación'),
  (72, 'Descargar carta de examinadores'),
  (73, 'Abrir nuevo ciclo de revisión'),
  (74, 'Eliminar evidencia de correo');
```
