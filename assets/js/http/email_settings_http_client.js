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

    return {save, test};
})();
