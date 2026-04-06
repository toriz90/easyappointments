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

    function initialize() {
        deserialize(vars('email_settings') || []);
        $saveSettings.on('click', onSaveClick);
        $testEmail.on('click', onTestClick);
        $sendTestEmail.on('click', onSendTestClick);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
