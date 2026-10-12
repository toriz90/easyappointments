<?php extend('layouts/account_layout'); ?>

<?php section('content'); ?>

<div class="text-center mb-4">
    <?php if (setting('company_logo')): ?>
        <?php if (strpos(setting('company_logo'), 'data:image') === 0): ?>
            <img src="<?= setting('company_logo') ?>" alt="<?= e(setting('company_name') ?: 'Logo') ?>" class="shadow mb-3 account-logo" width="72" height="72">
        <?php else: ?>
            <img src="<?= base_url('storage/uploads/' . setting('company_logo')) ?>" alt="<?= e(setting('company_name') ?: 'Logo') ?>" class="shadow mb-3 account-logo" width="72" height="72">
        <?php endif; ?>
    <?php endif; ?>
    <h4 class="text-primary fw-semibold mb-1"><?= lang('log_out') ?></h4>
    <p class=" small mb-0"><?= lang('logout_success') ?></p>
</div>

<div class="d-grid gap-2 mb-3">
    <a href="<?= site_url() ?>" class="btn btn-primary">
        <i class="fas fa-calendar-alt me-2"></i>
        <?= lang('book_appointment_title') ?>
    </a>
</div>

<div class="text-center">
    <a href="<?= site_url('login') ?>" class="text-decoration-none  small">
        <i class="fas fa-arrow-left me-1"></i>
        <?= lang('backend_section') ?>
    </a>
</div>

<?php end_section('content'); ?>


