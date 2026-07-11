/**
 * Servicio de Confirmaciones y Alertas
 * Reemplaza confirm() y alert() nativos de JS con un modal Bootstrap personalizado.
 *
 * Uso:
 *   ConfirmService.confirm('¿Está seguro?', function() { ... });
 *   ConfirmService.alert('Operación completada', function() { ... });
 */
var ConfirmService = (function() {
    'use strict';

    function initModal() {
        if (document.getElementById('confirm-service-modal')) {
            return;
        }

        var html = '<div class="modal fade" id="confirm-service-modal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog modal-lg" role="document" style="width: 720px; max-width: 95%; margin-top: 8%;">' +
            '<div class="modal-content" style="border-radius: 8px; overflow: hidden;">' +
            '<div class="modal-header" style="background-color: #1a3c6b; color: #fff; padding: 15px 20px; border-bottom: none;">' +
            '<h5 class="modal-title" style="font-size: 16px; font-weight: 600; margin: 0;">' +
            '<i class="fa fa-question-circle" style="margin-right: 8px;"></i>' +
            '<span id="confirm-service-title">Confirmar</span></h5>' +
            '</div>' +
            '<div class="modal-body" style="padding: 28px 24px; font-size: 14px; color: #333; line-height: 1.5;">' +
            '<p id="confirm-service-message" style="margin: 0;"></p>' +
            '</div>' +
            '<div class="modal-footer" style="padding: 12px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">' +
            '<button type="button" id="confirm-service-cancel" class="btn btn-default" style="font-size: 13px; font-weight: 600; padding: 6px 16px;" data-dismiss="modal">Cancelar</button>' +
            '<button type="button" id="confirm-service-ok" class="btn btn-primary" style="background-color: #1a3c6b; border-color: #1a3c6b; font-size: 13px; font-weight: 600; padding: 6px 16px;">Aceptar</button>' +
            '</div>' +
            '</div></div></div>';

        var div = document.createElement('div');
        div.innerHTML = html;
        document.body.appendChild(div.firstChild);
    }

    function show(options) {
        initModal();

        var type = options.type || 'confirm';
        var message = options.message || '';
        var title = options.title || (type === 'confirm' ? 'Confirmar acción' : 'Información');
        var onConfirm = options.onConfirm || function() {};
        var onCancel = options.onCancel || function() {};

        var modal = jQuery('#confirm-service-modal');
        var okBtn = jQuery('#confirm-service-ok');
        var cancelBtn = jQuery('#confirm-service-cancel');

        jQuery('#confirm-service-message').text(message);
        jQuery('#confirm-service-title').text(title);

        if (type === 'alert') {
            cancelBtn.hide();
            okBtn.text('Aceptar');
        } else {
            cancelBtn.show();
            okBtn.text('Aceptar');
        }

        okBtn.off('click.confirm');
        cancelBtn.off('click.confirm');

        okBtn.on('click.confirm', function() {
            modal.modal('hide');
            onConfirm();
        });

        cancelBtn.on('click.confirm', function() {
            modal.modal('hide');
            onCancel();
        });

        modal.modal('show');
    }

    function confirm(message, onConfirm, onCancel, options) {
        options = options || {};
        show({
            type: 'confirm',
            message: message,
            onConfirm: onConfirm || function() {},
            onCancel: onCancel || function() {},
            title: options.title || 'Confirmar acción'
        });
    }

    function alert(message, onOk, options) {
        options = options || {};
        show({
            type: 'alert',
            message: message,
            onConfirm: onOk || function() {},
            title: options.title || 'Información'
        });
    }

    return {
        confirm: confirm,
        alert: alert
    };
})();
