/**
 * Servicio de Notificaciones Toast
 * Muestra mensajes temporales en la esquina inferior derecha de la pantalla
 * 
 * Uso:
 *   ToastService.show('Mensaje de éxito', 'success');
 *   ToastService.success('Operación completada');
 *   ToastService.error('Ha ocurrido un error');
 *   ToastService.warning('Advertencia');
 *   ToastService.info('Información');
 */

var ToastService = (function() {
    'use strict';

    // Configuración por defecto
    var config = {
        position: 'bottom-right',     // Posición: top-left, top-right, bottom-left, bottom-right
        duration: 5000,                // Duración en milisegundos (aumentado para mejor lectura)
        fadeOutDuration: 400,          // Duración del efecto fade out
        maxToasts: 5,                  // Máximo número de toasts simultáneos
        offset: {
            vertical: 20,              // Distancia vertical desde el borde
            horizontal: 20,            // Distancia horizontal desde el borde
            spacing: 15                // Espaciado entre toasts
        }
    };

    // Contenedor de toasts
    var toastContainer = null;
    var activeToasts = [];

    /**
     * Inicializa el contenedor de toasts
     */
    function initContainer() {
        if (toastContainer) return;

        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = getContainerStyles();
        document.body.appendChild(toastContainer);
    }

    /**
     * Obtiene los estilos CSS para el contenedor según la posición
     */
    function getContainerStyles() {
        var styles = 'position: fixed; z-index: 9999; pointer-events: none;';
        var pos = config.position.split('-');
        
        if (pos[0] === 'top') {
            styles += 'top: ' + config.offset.vertical + 'px;';
        } else {
            styles += 'bottom: ' + config.offset.vertical + 'px;';
        }
        
        if (pos[1] === 'right') {
            styles += 'right: ' + config.offset.horizontal + 'px;';
        } else {
            styles += 'left: ' + config.offset.horizontal + 'px;';
        }
        
        return styles;
    }

    /**
     * Muestra un toast
     * @param {string} mensaje - Mensaje a mostrar
     * @param {string} tipo - Tipo de alerta: success, danger, warning, info
     * @param {object} opciones - Opciones adicionales (duración, icono, etc.)
     */
    function show(mensaje, tipo, opciones) {
        if (!mensaje) return;

        initContainer();

        // Opciones por defecto
        tipo = tipo || 'info';
        opciones = opciones || {};
        
        var duration = opciones.duration || config.duration;
        var icon = opciones.icon || getDefaultIcon(tipo);
        var dismissible = opciones.dismissible !== false;

        // Limitar número de toasts
        if (activeToasts.length >= config.maxToasts) {
            removeToast(activeToasts[0]);
        }

        // Crear elemento toast
        var toastId = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        var toastElement = createToastElement(toastId, mensaje, tipo, icon, dismissible);

        // Agregar al contenedor
        if (config.position.indexOf('top') !== -1) {
            toastContainer.appendChild(toastElement);
        } else {
            toastContainer.insertBefore(toastElement, toastContainer.firstChild);
        }

        activeToasts.push(toastId);

        // Animar entrada
        setTimeout(function() {
            toastElement.style.opacity = '1';
            toastElement.style.transform = 'translateX(0)';
        }, 10);

        // Auto-cerrar después de la duración especificada
        if (duration > 0) {
            setTimeout(function() {
                removeToast(toastId);
            }, duration);
        }

        return toastId;
    }

    /**
     * Crea el elemento HTML del toast
     */
    function createToastElement(id, mensaje, tipo, icon, dismissible) {
        var toast = document.createElement('div');
        toast.id = id;
        toast.className = 'toast-notification alert alert-' + tipo;
        toast.setAttribute('role', 'alert');
        toast.style.cssText = getToastStyles();

        var content = '';
        
        // Agregar icono si existe
        if (icon) {
            content += '<i class="fa ' + icon + '" style="margin-right: 20px; font-size: 28px;"></i>';
        }

        content += '<span class="toast-message" style="flex: 1;">' + mensaje + '</span>';

        // Botón de cerrar
        if (dismissible) {
            content += '<button type="button" class="close" style="margin-left: auto; opacity: 0.8; font-size: 32px; padding: 0; line-height: 1;" onclick="ToastService.remove(\'' + id + '\')">' +
                      '<span aria-hidden="true">&times;</span>' +
                      '</button>';
        }

        toast.innerHTML = content;
        toast.style.pointerEvents = 'auto';

        return toast;
    }

    /**
     * Obtiene los estilos CSS para cada toast
     */
    function getToastStyles() {
        return 'min-width: 560px; max-width: 800px; margin-bottom: ' + config.offset.spacing + 'px; ' +
               'box-shadow: 0 6px 20px rgba(0,0,0,.25); display: flex; align-items: center; ' +
               'opacity: 0; transition: all 0.3s ease; border-radius: 6px; padding: 30px 50px; ' +
               'font-size: 18px; font-weight: 500; line-height: 1.5; ' +
               'transform: translateX(' + (config.position.indexOf('right') !== -1 ? '100px' : '-100px') + ');';
    }

    /**
     * Obtiene el icono por defecto según el tipo
     */
    function getDefaultIcon(tipo) {
        var icons = {
            'success': 'fa-check-circle',
            'danger': 'fa-times-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        return icons[tipo] || icons.info;
    }

    /**
     * Remueve un toast
     */
    function removeToast(toastId) {
        var toast = document.getElementById(toastId);
        if (!toast) return;

        toast.style.opacity = '0';
        toast.style.transform = 'translateX(' + 
            (config.position.indexOf('right') !== -1 ? '100px' : '-100px') + ')';

        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            var index = activeToasts.indexOf(toastId);
            if (index > -1) {
                activeToasts.splice(index, 1);
            }
        }, config.fadeOutDuration);
    }

    /**
     * Métodos de conveniencia para cada tipo
     */
    function success(mensaje, opciones) {
        return show(mensaje, 'success', opciones);
    }

    function error(mensaje, opciones) {
        return show(mensaje, 'danger', opciones);
    }

    function warning(mensaje, opciones) {
        return show(mensaje, 'warning', opciones);
    }

    function info(mensaje, opciones) {
        return show(mensaje, 'info', opciones);
    }

    /**
     * Remueve todos los toasts
     */
    function clear() {
        activeToasts.slice().forEach(function(toastId) {
            removeToast(toastId);
        });
    }

    /**
     * Configura el servicio
     */
    function configure(newConfig) {
        Object.keys(newConfig).forEach(function(key) {
            if (config.hasOwnProperty(key)) {
                if (typeof config[key] === 'object' && !Array.isArray(config[key])) {
                    Object.assign(config[key], newConfig[key]);
                } else {
                    config[key] = newConfig[key];
                }
            }
        });

        // Actualizar estilos del contenedor si existe
        if (toastContainer) {
            toastContainer.style.cssText = getContainerStyles();
        }
    }

    // API pública
    return {
        show: show,
        success: success,
        error: error,
        warning: warning,
        info: info,
        remove: removeToast,
        clear: clear,
        configure: configure
    };
})();

// Compatibilidad con jQuery (opcional)
if (typeof jQuery !== 'undefined') {
    jQuery.toast = function(mensaje, tipo, opciones) {
        return ToastService.show(mensaje, tipo, opciones);
    };
}
