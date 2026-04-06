App.Http.EmailSettings = (function () {
    function save(emailSettings) {
        const url = App.Utils.Url.siteUrl('email_settings/save');
        return $.post(url, {
            csrf_token: vars('csrf_token'),
            email_settings: emailSettings,
        });
    }

    function test(recipient) {
        const url = App.Utils.Url.siteUrl('email_settings/test');
        return $.post(url, {
            csrf_token: vars('csrf_token'),
            recipient: recipient,
        });
    }

    function resend(appointmentId, recipientType, recipientEmail) {
        const url = App.Utils.Url.siteUrl('email_settings/resend');
        return $.post(url, {
            csrf_token: vars('csrf_token'),
            appointment_id: appointmentId,
            recipient_type: recipientType,
            recipient_email: recipientEmail,
        });
    }

    function log() {
        const url = App.Utils.Url.siteUrl('email_settings/log');
        return $.ajax({url, method: 'GET', cache: false, data: {csrf_token: vars('csrf_token')}});
    }

    return {save, test, resend, log};
})();
