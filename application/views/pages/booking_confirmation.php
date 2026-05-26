<?php extend('layouts/message_layout'); ?>

<?php section('content'); ?>

<div>
    <img id="success-icon" class="mt-0 mb-5" src="<?= base_url('assets/img/success.png') ?>" alt="success"/>
</div>

<div class="mb-5">
    <h4 class="mb-5"><?= lang('appointment_registered') ?></h4>

    <p>
        <?= lang('appointment_details_was_sent_to_you') ?>
    </p>

    <p class="mb-5 text-muted">
        <small>
            <?= lang('check_spam_folder') ?>
        </small>
    </p>

    <div class="d-flex flex-column flex-md-row justify-content-center align-items-stretch align-items-md-center flex-wrap gap-2">
        <a href="<?= site_url() ?>" class="btn btn-primary">
            <i class="fas fa-calendar-alt me-2"></i>
            <?= lang('go_to_booking_page') ?>
        </a>

        <a href="<?= vars('add_to_google_url') ?>" id="add-to-google-calendar" class="btn btn-primary" target="_blank">
            <i class="fas fa-plus me-2"></i>
            <?= lang('add_to_google_calendar') ?>
        </a>

        <a href="<?= site_url('confirmation_pdf/download/' . vars('appointment_hash')) ?>"
           id="download-confirmation-pdf" class="btn btn-primary" target="_blank">
            <i class="fas fa-file-pdf me-2"></i>
            Descargar PDF de confirmación
        </a>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<?php component('google_analytics_script', ['google_analytics_code' => vars('google_analytics_code')]); ?>
<?php component('matomo_analytics_script', [
    'matomo_analytics_url' => vars('matomo_analytics_url'),
    'matomo_analytics_site_id' => vars('matomo_analytics_site_id'),
]); ?>

<?php end_section('scripts'); ?>
