App.Pages.EmailSettings = (function () {
    const $saveSettings = $('#save-settings');
    const $testEmail = $('#test-email');
    const $sendTestEmail = $('#send-test-email');
    const $testModal = $('#test-email-modal');

    function deserialize(emailSettings) {
        emailSettings.forEach((setting) => {
            if (setting.name === 'smtp_pass') return; // never pre-fill password
            const $field = $('[data-field="' + setting.name + '"]');
            if ($field.length) $field.val(setting.value || '');
        });
    }

    function serialize() {
        const settings = [];
        $('[data-field]').each((index, field) => {
            const $field = $(field);
            settings.push({name: $field.data('field'), value: $field.val()});
        });
        return settings;
    }

    function isInvalid() {
        $('#email-settings .is-invalid').removeClass('is-invalid');
        let invalid = false;
        $('#email-settings .required').each((i, field) => {
            if (!$(field).val()) {
                $(field).addClass('is-invalid');
                invalid = true;
            }
        });
        if (invalid) {
            App.Layouts.Backend.displayNotification(lang('fields_are_required'));
        }
        return invalid;
    }

    function onSaveClick() {
        if (isInvalid()) return;
        App.Http.EmailSettings.save(serialize()).done(() => {
            App.Layouts.Backend.displayNotification('Configuración de correo guardada correctamente.');
        }).fail(() => {
            App.Layouts.Backend.displayNotification('Error al guardar la configuración.');
        });
    }

    function onTestClick() {
        $testModal.modal('show');
    }

    function onSendTestClick() {
        const recipient = $('#test-email-recipient').val();
        if (!recipient) {
            $('#test-email-recipient').addClass('is-invalid');
            return;
        }
        $sendTestEmail.prop('disabled', true).text('Enviando...');
        App.Http.EmailSettings.test(recipient)
            .done((response) => {
                $testModal.modal('hide');
                App.Layouts.Backend.displayNotification(
                    response.message || 'Correo de prueba enviado correctamente.'
                );
            })
            .fail(() => {
                App.Layouts.Backend.displayNotification('Error al enviar el correo de prueba.');
            })
            .always(() => {
                $sendTestEmail.prop('disabled', false).text('Enviar');
            });
    }

    const STATUS_BADGES = {
        sent:    'bg-success',
        error:   'bg-danger',
        skipped: 'bg-warning text-dark',
        started: 'bg-secondary',
    };

    const EVENT_LABELS = {
        saved:   'Nueva cita',
        deleted: 'Cancelada',
    };

    const RECIPIENT_LABELS = {
        customer:  'Cliente',
        provider:  'Proveedor',
        admin:     'Admin',
        secretary: 'Secretaria',
        system:    'Sistema',
    };

    function loadLog() {
        App.Http.EmailSettings.log().done((response) => {
            const $body = $('#email-log-body');
            $body.empty();
            const entries = response.entries || [];
            if (!entries.length) {
                $body.append('<tr><td colspan="7" class="text-center text-muted py-3">Sin registros aún.</td></tr>');
                return;
            }
            entries.forEach((e) => {
                const badgeClass = STATUS_BADGES[e.status] || 'bg-secondary';
                const eventLabel = EVENT_LABELS[e.event] || e.event;
                const recipientLabel = RECIPIENT_LABELS[e.recipient_type] || e.recipient_type;
                const canResend = e.status === 'error'
                    && e.appointment_id !== '-'
                    && e.recipient_email !== '-'
                    && e.recipient_type !== 'system';
                const retryBtn = canResend
                    ? `<button class="btn btn-outline-danger btn-sm py-0 px-1 ms-1 retry-btn"
                            data-appt="${e.appointment_id}"
                            data-type="${e.recipient_type}"
                            data-email="${e.recipient_email}"
                            title="Reintentar envío">
                            <i class="fas fa-redo fa-xs"></i>
                        </button>`
                    : '';
                $body.append(`<tr>
                    <td class="text-nowrap small">${e.datetime}</td>
                    <td class="text-center">${e.appointment_id !== '-' ? '#' + e.appointment_id : '—'}</td>
                    <td>${eventLabel}</td>
                    <td>${recipientLabel}</td>
                    <td class="small">${e.recipient_email !== '-' ? e.recipient_email : '—'}</td>
                    <td><span class="badge ${badgeClass}">${e.status}</span>${retryBtn}</td>
                    <td class="small text-muted">${e.detail || ''}</td>
                </tr>`);
            });
        }).fail(() => {
            $('#email-log-body').html('<tr><td colspan="7" class="text-center text-danger py-3">Error al cargar el registro.</td></tr>');
        });
    }

    function onRetryClick() {
        const $btn = $(this);
        const apptId = $btn.data('appt');
        const type   = $btn.data('type');
        const email  = $btn.data('email');

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin fa-xs"></i>');

        App.Http.EmailSettings.resend(apptId, type, email)
            .done(() => {
                App.Utils.Message.show('Correo reenviado', 'El correo fue enviado exitosamente a ' + email + '.');
                loadLog();
            })
            .fail((jqXHR) => {
                let msg = 'Error al reenviar el correo.';
                try { msg = JSON.parse(jqXHR.responseText).message || msg; } catch(_) {}
                App.Utils.Message.show('Error', msg);
                $btn.prop('disabled', false).html('<i class="fas fa-redo fa-xs"></i>');
            });
    }

    function initialize() {
        deserialize(vars('email_settings') || []);
        $saveSettings.on('click', onSaveClick);
        $testEmail.on('click', onTestClick);
        $sendTestEmail.on('click', onSendTestClick);
        $('#refresh-log').on('click', loadLog);
        $('#email-log-body').on('click', '.retry-btn', onRetryClick);
        loadLog();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
