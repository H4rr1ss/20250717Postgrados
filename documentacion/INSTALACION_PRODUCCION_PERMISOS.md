# Guía de Instalación en Producción Real — Debian (Servidor en la Nube)

**Proyecto:** 20250717Postgrados (rama `development` → migrar a `main`)
**Ambiente destino:** Debian 5.10, Apache, PHP 7.4.33 nativo (no Docker)
**Fecha:** 2026-08-22

---

## ⚠️ Nota importante

Este documento **no incluye** pasos de Docker, ya que la producción real utiliza PHP nativo sobre Debian. Solo se listan los paquetes y extensiones que el **código nuevo** de `development` requiere para funcionar correctamente.

---

## 1. Librerías del Sistema Operativo (apt-get)

Estas son librerías escritas en **C** que PHP necesita para manipular imágenes. Son requeridas para compilar o activar la extensión `gd`.

```bash
sudo apt-get update
sudo apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev
```

| Paquete | ¿Qué hace? | ¿Por qué se necesita? |
|---|---|---|
| `libpng-dev` | Permite leer/escribir imágenes PNG | La extensión `gd` de PHP la usa para generar gráficas PNG |
| `libjpeg-dev` | Permite leer/escribir imágenes JPEG | Requerido por `gd` para soporte de imágenes JPEG |
| `libfreetype6-dev` | Renderiza fuentes TrueType (TTF) | Requerido por `gd` para escribir texto con fuentes personalizadas en imágenes |

> **Nota:** `libxml2-dev` y `soap` **NO** se incluyen porque el código nuevo (Evaluación Docente, Admisión, Graduación, Exámenes, Recuperación de Contraseña) **no utiliza** `SoapClient` ni servicios SOAP.

---

## 2. Extensiones PHP

```bash
# Verificar primero qué ya está instalado:
php -m | grep -iE "gd|zip|fileinfo|pdo_mysql"

# Instalar las que falten:
sudo apt-get install -y php7.4-gd php7.4-zip php7.4-fileinfo php7.4-mysql
```

| Extensión | ¿Qué hace? | ¿Quién la usa? | Crítica |
|---|---|---|---|
| `php7.4-gd` | Generación de imágenes (`imagecreate`, `imagepng`, `imagettftext`, etc.) | `EvaluacionDocenteGraficaService.php` — genera gráficas estadísticas en PNG | ✅ **Sí** |
| `php7.4-fileinfo` | Detecta tipo MIME de archivos (`mime_content_type`) | `FormularioAdmisionManager.php` — valida archivos subidos por aspirantes<br>`MailManager.php` — adjunta imágenes inline en correos | ✅ **Sí** |
| `php7.4-zip` | Compresión/descompresión ZIP | Composer la usa internamente; código nuevo no la usa directamente | ⚠️ Recomendada |
| `php7.4-mysql` / `pdo_mysql` | Conexión a base de datos MySQL | Toda la aplicación base | ✅ Ya debería estar |

> **No instalar:** `php7.4-soap` — el código nuevo no la utiliza.

---

## 3. Dependencias de Composer

Después de desplegar el código y antes de levantar la aplicación:

```bash
cd /ruta/del/proyecto
composer install --no-dev --optimize-autoloader
```

Esto instalará automáticamente `zendframework/zend-mail` (y sus dependencias transitivas como `zend-mime`), que es necesaria para el **módulo de Recuperación de Contraseña**.

> **No copiar la carpeta `vendor/` manualmente** desde `dev`. Siempre regenerarla con Composer en producción.

---

## 4. Estructura de Carpetas y Permisos

El código nuevo crea y escribe archivos en disco. Las siguientes carpetas **deben existir** y tener el propietario correcto.

### 4.1 Crear carpetas

```bash
sudo mkdir -p /var/www/data/cache
sudo mkdir -p /var/www/data/sessiones
sudo mkdir -p /var/www/data/fonts
sudo mkdir -p /var/www/data/graficas
sudo mkdir -p /var/www/data/admisiones
sudo mkdir -p /var/www/data/graduacion/procesos
sudo mkdir -p /var/www/data/graduacion/global/cartas-descarga
sudo mkdir -p /var/www/data/graduacion/global/documentos-soporte
sudo mkdir -p /var/www/data/graduacion/global/requisitos-apoyo
sudo mkdir -p /var/www/data/graduacion/plantillas/carta-examinadores
```

### 4.2 Copiar fuente TTF requerida

El servicio de gráficas (`EvaluacionDocenteGraficaService`) espera esta fuente en una ruta **hardcodeada**:

```bash
# Copiar el archivo DejaVuSans.ttf desde el repositorio o fuente confiable
sudo cp /ruta/donde/este/DejaVuSans.ttf /var/www/data/fonts/DejaVuSans.ttf
```

> **Ruta esperada por el código:** `/var/www/data/fonts/DejaVuSans.ttf`

### 4.3 Establecer propietario

Apache en Debian corre como usuario `www-data`:

```bash
sudo chown -R www-data:www-data /var/www/data
```

### 4.4 Establecer permisos

| Carpeta | Permiso | ¿Por qué? |
|---|---|---|
| `data/cache` | `755` | Caché de templates y configuración. Solo lectura/escritura interna |
| `data/sessiones` | `755` | Sesiones de PHP en disco. Apache las lee y escribe |
| `data/fonts` | `755` | Fuente TTF estática. Solo lectura |
| `data/graficas` | `755` | Imágenes PNG temporales generadas por Evaluación Docente |
| `data/admisiones` | `775` | **La app sube archivos aquí** (documentos de aspirantes) |
| `data/graduacion/procesos` | `775` | **La app crea subcarpetas y sube archivos** (actas, ternas, documentos) |
| `data/graduacion/global/cartas-descarga` | `775` | **La app genera y guarda cartas .docx** para descarga |
| `data/graduacion/global/documentos-soporte` | `775` | **La app sube archivos de soporte** |
| `data/graduacion/global/requisitos-apoyo` | `775` | **La app sube archivos de requisitos** |
| `data/graduacion/plantillas` | `755` | Plantillas Word estáticas. Solo lectura |

```bash
sudo chmod -R 755 /var/www/data/cache
sudo chmod -R 755 /var/www/data/sessiones
sudo chmod -R 755 /var/www/data/fonts
sudo chmod -R 755 /var/www/data/graficas
sudo chmod -R 755 /var/www/data/graduacion/plantillas

sudo chmod -R 775 /var/www/data/admisiones
sudo chmod -R 775 /var/www/data/graduacion/procesos
sudo chmod -R 775 /var/www/data/graduacion/global/cartas-descarga
sudo chmod -R 775 /var/www/data/graduacion/global/documentos-soporte
sudo chmod -R 775 /var/www/data/graduacion/global/requisitos-apoyo
```

---

## 5. Reiniciar Apache

Después de instalar extensiones PHP:

```bash
sudo systemctl restart apache2
# o
sudo service apache2 restart
```

---

## 6. Verificación rápida

Ejecuta este script PHP de prueba en el servidor para confirmar que todo está listo:

```php
<?php
// guardar como /var/www/public/verificar.php, acceder por navegador, luego eliminar

$extensiones = ['gd', 'zip', 'fileinfo', 'pdo_mysql'];
$faltantes = [];

foreach ($extensiones as $ext) {
    if (!extension_loaded($ext)) {
        $faltantes[] = $ext;
    }
}

$fuente = '/var/www/data/fonts/DejaVuSans.ttf';

echo "PHP: " . phpversion() . "<br>";
echo "GD con FreeType: " . (function_exists('imagettftext') ? 'SI' : 'NO') . "<br>";
echo "Extensiones faltantes: " . (empty($faltantes) ? 'NINGUNA' : implode(', ', $faltantes)) . "<br>";
echo "Fuente TTF: " . (file_exists($fuente) ? 'SI (' . $fuente . ')' : 'NO — ' . $fuente) . "<br>";
echo "Carpeta admisiones escribible: " . (is_writable('/var/www/data/admisiones') ? 'SI' : 'NO') . "<br>";
echo "Carpeta graduacion/procesos escribible: " . (is_writable('/var/www/data/graduacion/procesos') ? 'SI' : 'NO') . "<br>";
```

---

## 7. Resumen de lo que NO se necesita instalar

| Paquete / Extensión | Razón de exclusión |
|---|---|
| `git` | Solo se usa en desarrollo |
| `libxml2-dev` | No se usa SOAP en el código nuevo |
| `php7.4-soap` | No hay `SoapClient` en los nuevos módulos |
| `soap` | ExamenManager y StudentGraduationManager usan puro Zend DB |
| `public/archivos` | **Carpeta fantasma.** El proyecto no la usa. Fue reemplazada por `data/admisiones/` y `data/graduacion/` |

---

*Documento generado a partir del análisis de Dockerfiles (`dev` vs `prod`) y del código fuente de los nuevos módulos funcionales.*
