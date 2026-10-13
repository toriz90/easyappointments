<?php
/**
 * Local variables.
 *
 * @var bool $manage_mode
 * @var array $appointment_data
 * @var bool $display_delete_personal_information
 * @var bool $can_cancel
 * @var int $cancellation_cutoff_hours
 */

$can_cancel = $can_cancel ?? true;
$cancellation_cutoff_hours = $cancellation_cutoff_hours ?? 24;
?>

<?php if ($manage_mode): ?>
    <div id="cancel-appointment-frame" class="row booking-header-bar py-2 px-3 m-0 align-items-center bg-warning-subtle">
        <div class="col-lg-9">
            <small>
                <?= $can_cancel
                    ? lang('cancel_appointment_hint')
                    : strtr(lang('cancel_appointment_cutoff_hint'), ['{$hours}' => $cancellation_cutoff_hours]) ?>
            </small>
        </div>
        <div class="col-lg-3 text-end">
            <form id="cancel-appointment-form" method="post"
                  action="<?= site_url('booking_cancellation/of/' . $appointment_data['hash']) ?>">

                <input id="hidden-cancellation-reason" name="cancellation_reason" type="hidden">

                <button id="cancel-appointment" class="btn btn-warning btn-sm" <?= $can_cancel ? '' : 'disabled' ?>>
                    <i class="fas fa-trash me-2"></i>
                    <?= lang('cancel_appointment_button') ?>
                </button>
            </form>
        </div>
    </div>
    <?php if ($display_delete_personal_information): ?>
        <div class="booking-header-bar row py-2 px-3 m-0 align-items-center bg-danger-subtle">
            <div class="col-lg-9">
                <small><?= lang('delete_personal_information_hint') ?></small>
            </div>
            <div class="col-lg-3 text-end">
                <button id="delete-personal-information" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash me-2"></i>
                    <?= lang('delete') ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
