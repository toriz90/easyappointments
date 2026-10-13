<!doctype html>
<html lang="<?= config('language_code') ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="theme-color" content="#1F2A37">
    <meta name="google" content="notranslate">

    <?php slot('meta'); ?>

    <title><?= vars('page_title') ?><?= setting('company_name') ? ' | ' . e(setting('company_name')) : '' ?></title>

    <?php if (setting('company_logo')): ?>
        <?php if (strpos(setting('company_logo'), 'data:image') === 0): ?>
            <link rel="icon" type="image/x-icon" href="<?= setting('company_logo') ?>">
        <?php else: ?>
            <link rel="icon" type="image/x-icon" href="<?= base_url('storage/uploads/' . setting('company_logo')) ?>">
        <?php endif; ?>
    <?php else: ?>
        <link rel="icon" type="image/x-icon" href="<?= asset_url('assets/img/favicon.ico') ?>">
    <?php endif; ?>

    <link rel="stylesheet" type="text/css"
          href="<?= asset_url('assets/css/themes/' . setting('theme', 'default') . '.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/general.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/layouts/booking_layout.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/honeywhale-overrides.css') ?>">

    <?php slot('styles'); ?>
</head>
<body>
<div id="main" class="container min-vh-100">
    <div class="row wrapper min-vh-100 justify-content-center align-items-center py-3">
        <div id="message-frame" class="col-12 col-md-8 col-lg-6 my-auto frame-container text-center bg-body rounded shadow p-4 p-md-5">

            <?php slot('content'); ?>

            <?php if (vars('legal_notice_url') || vars('imprint_url') || vars('display_login_button')): ?>
                <div class="mt-2">
                    <small>
                        <?php if (vars('legal_notice_url')): ?>
                            <a href="<?= e(vars('legal_notice_url')) ?>" target="_blank"><?= lang('legal_notice') ?></a>
                        <?php endif; ?>

                        <?php if (vars('legal_notice_url') && vars('imprint_url')): ?>
                            <span class="mx-1">|</span>
                        <?php endif; ?>

                        <?php if (vars('imprint_url')): ?>
                            <a href="<?= e(vars('imprint_url')) ?>" target="_blank"><?= lang('imprint') ?></a>
                        <?php endif; ?>
                    </small>

                    <?php if (vars('display_login_button')): ?>
                        <div class="mt-3">
                            <a class="backend-link badge bg-primary text-decoration-none px-2 d-inline-block p-1"
                               href="<?= session('user_id') ? site_url('calendar') : site_url('login') ?>"
                               style="min-width: 120px;">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                <?= session('user_id') ? lang('backend_section') : lang('login') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="<?= asset_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@popperjs-core/popper.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment/moment.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment-timezone/moment-timezone-with-data.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.js') ?>"></script>

<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/date.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/file.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/http.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/lang.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/message.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/string.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/url.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/validation.js') ?>"></script>
<script src="<?= asset_url('assets/js/layouts/message_layout.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/localization_http_client.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<?php slot('scripts'); ?>

</body>
</html>
