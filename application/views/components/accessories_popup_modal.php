<?php
/**
 * Informational popup shown on the public booking page, reminding the customer to bring
 * their equipment with all its accessories to the appointment. Only rendered for new
 * bookings (see pages/booking.php) — never on reschedule, cancellation or confirmation.
 *
 * Appearance rules (session/localStorage-based) live in booking.js:
 * initializeAccessoriesPopup().
 */
?>
<div class="modal fade" id="accessories-popup-modal" tabindex="-1" aria-labelledby="accessories-popup-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="btn-close accessories-popup-close" data-bs-dismiss="modal" aria-label="<?= lang('close') ?>"></button>
            <span id="accessories-popup-modal-label" class="visually-hidden"><?= lang('accessories_commitment_popup_alt') ?></span>
            <img src="<?= asset_url('assets/img/popup-accesorios.webp') ?>"
                 alt="<?= e(lang('accessories_commitment_popup_alt')) ?>"
                 class="accessories-popup-image">
        </div>
    </div>
</div>
