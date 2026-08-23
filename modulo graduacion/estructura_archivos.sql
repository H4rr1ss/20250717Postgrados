-- ============================================================
-- ESTRUCTURA CENTRALIZADA DE ARCHIVOS - MÓDULO DE GRADUACIÓN
-- ============================================================
-- Archivo: estructura_archivos.sql
-- Descripción: Documentación de la nueva estructura de carpetas
--              para centralizar todos los archivos del módulo.
-- Fecha: 2026-05-29
-- ============================================================

/*

NUEVA ESTRUCTURA DE CARPETAS:
==============================

data/graduacion/
├── plantillas/
│   └── carta-examinadores/
│       ├── general.docx
│       └── privado-gerencia.docx
├── procesos/
│   └── {cod_proceso}/
│       └── (todos los archivos del proceso mezclados aquí)
└── global/
    ├── documentos-soporte/
│   │   └── <md5>.pdf/.jpg/.png
    └── cartas-descarga/
        └── <md5>.docx


RUTAS EN CÓDIGO PHP:
====================

// Base del proyecto (sin slash final)
$rutaProyecto = dirname($_SERVER['DOCUMENT_ROOT']);

// Ruta base del módulo
$rutaBaseModulo = $rutaProyecto . '/data/graduacion';

// Plantillas
$rutaPlantillas = $rutaBaseModulo . '/plantillas/carta-examinadores';

// Archivos de un proceso específico
$rutaProceso = $rutaBaseModulo . '/procesos/' . $codProceso;

// Recursos globales paso 6
$rutaGlobalDocs = $rutaBaseModulo . '/global/documentos-soporte';
$rutaGlobalCartas = $rutaBaseModulo . '/global/cartas-descarga';


TIPOS DE ARCHIVOS POR UBICACIÓN:
================================

1. PLANTILLAS (data/graduacion/plantillas/)
   - Archivos .docx de plantillas para generar cartas
   - Solo lectura, gestionados por admin
   - No se borran al eliminar un proceso

2. PROCESOS/{cod_proceso}/ (data/graduacion/procesos/)
   - Documentos subidos por estudiantes (PDF, JPG, PNG)
   - Cartas generadas .docx
   - Evidencias de corrección (imágenes, PDFs)
   - Todo mezclado en la misma carpeta, sin subcarpetas
   - Se borra completamente al eliminar el proceso

3. GLOBAL/ (data/graduacion/global/)
   - Documentos de soporte: logos, escudos, guías (paso 6)
   - Cartas tipo .docx para descarga (paso 6)
   - Recursos compartidos entre todos los procesos
   - No se borran al eliminar un proceso


VENTAJAS DE ESTA ESTRUCTURA:
==============================

✓ Todo el módulo en un solo lugar (data/graduacion/)
✓ Archivos por proceso separados por carpeta
✓ Sin subcarpetas dentro del proceso (simple)
✓ Fácil backup: solo respaldar data/graduacion/
✓ Seguridad: fuera de public/, acceso controlado por PHP
✓ Limpieza automática: borrar carpeta = borrar todo del proceso
✓ Plantillas protegidas (no accesibles web directo)


COMANDOS PARA CREAR ESTRUCTURA EN SERVIDOR:
============================================

mkdir -p /var/www/data/graduacion/plantillas/carta-examinadores
mkdir -p /var/www/data/graduacion/procesos
mkdir -p /var/www/data/graduacion/global/documentos-soporte
mkdir -p /var/www/data/graduacion/global/cartas-descarga
chown -R www-data:www-data /var/www/data/graduacion
chmod -R 755 /var/www/data/graduacion

*/

-- ============================================================
-- FIN DE DOCUMENTACIÓN
-- ============================================================
