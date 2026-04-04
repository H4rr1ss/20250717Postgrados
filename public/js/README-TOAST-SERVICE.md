# Toast Service - Servicio de Notificaciones

Servicio JavaScript independiente para mostrar notificaciones temporales (toast) en tu aplicación Zend Framework 3.

## 📁 Ubicación de Archivos

```
/public/js/toast-service.js        - Servicio principal
/public/toast-example.html          - Página de ejemplos y demostración
```

## 🚀 Instalación

### 1. Inclusión en Layout Principal

Agrega el servicio en tu layout principal (normalmente en `/module/Application/view/layout/layout.phtml`):

```php
<?php echo $this->headScript()->appendFile($this->basePath('js/toast-service.js')); ?>
```

### 2. Inclusión en Vista Específica

O inclúyelo solo en vistas específicas:

```php
<?php
$this->headScript()->appendFile($this->basePath('js/toast-service.js'));
?>
```

### 3. Inclusión Directa en HTML/PHTML

```html
<script src="/js/toast-service.js"></script>
```

## 📖 Uso Básico

### Métodos Simples

```javascript
// Mensaje de éxito
ToastService.success('¡Operación completada con éxito!');

// Mensaje de error
ToastService.error('Ha ocurrido un error al procesar la solicitud');

// Mensaje de advertencia
ToastService.warning('Por favor, revise los datos ingresados');

// Mensaje informativo
ToastService.info('El proceso puede tardar algunos minutos');
```

### Método General con Opciones

```javascript
ToastService.show(mensaje, tipo, opciones);
```

#### Parámetros:
- **mensaje** (string): El texto a mostrar
- **tipo** (string): Tipo de alert Bootstrap: `'success'`, `'danger'`, `'warning'`, `'info'`
- **opciones** (object, opcional): Configuración adicional

## ⚙️ Opciones Avanzadas

```javascript
ToastService.show('Mensaje personalizado', 'success', {
    duration: 5000,           // Duración en ms (0 = permanente)
    icon: 'fa-heart',         // Icono de Font Awesome
    dismissible: true         // Mostrar botón de cerrar
});
```

### Ejemplo con Duración Personalizada

```javascript
// Toast que dura 10 segundos
ToastService.success('Operación completada', {
    duration: 10000
});

// Toast permanente (no se cierra automáticamente)
ToastService.warning('Atención requerida', {
    duration: 0,
    dismissible: true
});
```

### Ejemplo con Icono Personalizado

```javascript
ToastService.show('Documento guardado', 'info', {
    icon: 'fa-file-pdf-o'
});
```

## 🔧 Configuración Global

Puedes configurar el comportamiento global del servicio:

```javascript
ToastService.configure({
    position: 'bottom-right',    // Posición de los toasts
    duration: 4000,              // Duración por defecto
    maxToasts: 5,                // Máximo de toasts simultáneos
    fadeOutDuration: 400,        // Duración del fade out
    offset: {
        vertical: 20,            // Distancia vertical
        horizontal: 20,          // Distancia horizontal
        spacing: 10              // Espaciado entre toasts
    }
});
```

### Posiciones Disponibles

- `'top-left'` - Arriba izquierda
- `'top-right'` - Arriba derecha
- `'bottom-left'` - Abajo izquierda
- `'bottom-right'` - Abajo derecha (por defecto)

## 🎯 Ejemplos Prácticos en Zend Framework 3

### 1. Reemplazo en tu Archivo Actual (paso2-documentacion.phtml)

**Antes:**
```javascript
function mostrarToast(mensaje, tipo) {
    const id = 'toast-doc-' + Date.now();
    const html = `<div id="${id}" class="alert alert-${tipo}" role="alert"
        style="position:fixed;bottom:20px;right:20px;z-index:9999;min-width:280px;box-shadow:0 4px 12px rgba(0,0,0,.15);">
        ${mensaje}
    </div>`;
    $('body').append(html);
    setTimeout(function() { $('#' + id).fadeOut(400, function() { $(this).remove(); }); }, 3500);
}
```

**Después:**
```javascript
// Ya no necesitas la función mostrarToast, usa directamente:
ToastService.success('Recepción guardada correctamente.');
ToastService.error('Error: ' + response.message);
ToastService.info('No hay cambios pendientes por guardar.');
```

### 2. Uso en Llamadas AJAX

```javascript
$.ajax({
    url: '<?= $this->url("examen", ["action" => "guardarDocFisico"]) ?>',
    type: 'POST',
    dataType: 'json',
    data: {
        cod_proceso: codProceso,
        documentos: documentos
    },
    success: function(response) {
        if (response.status === 'success') {
            ToastService.success('Recepción guardada correctamente.');
        } else {
            ToastService.error('Error: ' + (response.message || 'No se pudo guardar.'));
        }
    },
    error: function(xhr, status, error) {
        ToastService.error('Error de comunicación con el servidor.');
    }
});
```

### 3. Validaciones de Formulario

```javascript
function validarFormulario() {
    if (!$('#campo').val()) {
        ToastService.warning('El campo es requerido');
        return false;
    }
    
    if ($('#email').val().indexOf('@') === -1) {
        ToastService.error('Email inválido');
        return false;
    }
    
    ToastService.success('Formulario válido');
    return true;
}
```

### 4. Procesos con Múltiples Pasos

```javascript
function procesarDocumentos() {
    ToastService.info('Iniciando proceso de validación...');
    
    setTimeout(() => {
        ToastService.success('Paso 1 completado');
    }, 1000);
    
    setTimeout(() => {
        ToastService.success('Paso 2 completado');
    }, 2000);
    
    setTimeout(() => {
        ToastService.success('Proceso finalizado correctamente', {
            duration: 5000
        });
    }, 3000);
}
```

## 🎨 Personalización de Estilos

Los toasts usan las clases de Bootstrap, por lo que heredan los estilos de tu tema:

```css
/* Personalizar toasts globalmente */
.toast-notification {
    border-radius: 8px !important;
    font-weight: 500;
}

/* Personalizar toast de éxito */
.toast-notification.alert-success {
    background-color: #28a745 !important;
    color: white !important;
    border: none !important;
}
```

## 📋 API Completa

### Métodos Principales

| Método | Descripción | Parámetros |
|--------|-------------|------------|
| `ToastService.show(mensaje, tipo, opciones)` | Muestra un toast genérico | mensaje, tipo, opciones |
| `ToastService.success(mensaje, opciones)` | Toast de éxito | mensaje, opciones |
| `ToastService.error(mensaje, opciones)` | Toast de error | mensaje, opciones |
| `ToastService.warning(mensaje, opciones)` | Toast de advertencia | mensaje, opciones |
| `ToastService.info(mensaje, opciones)` | Toast informativo | mensaje, opciones |
| `ToastService.remove(toastId)` | Remueve un toast específico | toastId |
| `ToastService.clear()` | Remueve todos los toasts | - |
| `ToastService.configure(config)` | Configura el servicio | objeto config |

### Opciones del Toast

```javascript
{
    duration: 3500,        // Duración en ms (0 = permanente)
    icon: 'fa-icon-name',  // Icono de Font Awesome
    dismissible: true      // Mostrar botón X para cerrar
}
```

### Configuración Global

```javascript
{
    position: 'bottom-right',     // Posición del contenedor
    duration: 3500,               // Duración por defecto
    fadeOutDuration: 400,         // Duración del fade
    maxToasts: 5,                 // Máximo simultáneo
    offset: {
        vertical: 20,             // Offset vertical
        horizontal: 20,           // Offset horizontal
        spacing: 10               // Espacio entre toasts
    }
}
```

## 🔍 Compatibilidad

- ✅ Funciona con jQuery (opcional)
- ✅ Compatible con Bootstrap 3.x
- ✅ Usa Font Awesome para iconos
- ✅ JavaScript puro (no requiere jQuery obligatoriamente)
- ✅ Compatible con todos los navegadores modernos

## 🆘 Solución de Problemas

### Los toasts no se muestran

1. Verifica que Bootstrap CSS esté incluido
2. Verifica que Font Awesome esté incluido (para los iconos)
3. Revisa la consola del navegador por errores

### Los iconos no aparecen

Asegúrate de que Font Awesome esté cargado:
```html
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
```

### Los toasts no se cierran automáticamente

Verifica que estés usando una duración válida:
```javascript
ToastService.success('Mensaje', { duration: 3500 }); // Correcto
```

## 📝 Ejemplos Completos

Visita `/toast-example.html` en tu navegador para ver ejemplos interactivos del servicio.

## 📄 Licencia

Este servicio está disponible para uso libre en tu proyecto.

---

**Creado para el proyecto de Postgrados - Universidad Mayor de San Simón**
