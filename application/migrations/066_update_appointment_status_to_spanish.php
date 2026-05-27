<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Switch the appointment.status semantics to the Spanish set used by the Gestión de citas
 * module: Confirmada / Atendida / No se presentó / Cancelada.
 *
 * Notes:
 *  - The `status` column itself already exists (added in migration 044, VARCHAR(512) default
 *    ''). This migration only changes its default value to 'Confirmada' and backfills any
 *    existing real bookings whose status is empty or carries one of the legacy upstream
 *    values (Booked / Confirmed / Rescheduled / Cancelled / Draft).
 *  - Unavailability blocks (is_unavailability = 1) are left untouched — they don't carry a
 *    meaningful status.
 *  - Legacy cancellations ('Cancelled', 'Canceled') are preserved as 'Cancelada' BEFORE
 *    the generic backfill runs; otherwise the broad UPDATE would silently lose the
 *    cancellation history by mapping everything not in the Spanish set to 'Confirmada'.
 *  - The configurable `appointment_status_options` setting is updated so the appointment
 *    modal and the new management list use the Spanish values.
 */
class Migration_Update_appointment_status_to_spanish extends EA_Migration
{
    public function up(): void
    {
        $appointments = $this->db->dbprefix('appointments');

        if ($this->db->field_exists('status', 'appointments')) {
            // New default for any future row that does not set status explicitly.
            $this->db->query(
                "ALTER TABLE {$appointments} MODIFY COLUMN status VARCHAR(512) NOT NULL DEFAULT 'Confirmada'"
            );

            // FIRST: preserve legacy cancellations. Anything stored as 'Cancelled' /
            // 'Canceled' under the old upstream taxonomy is a real cancellation and
            // must survive the migration as 'Cancelada'. This MUST run before the
            // generic backfill below; otherwise the broad UPDATE would rewrite these
            // rows to 'Confirmada' and the cancellation history would be lost.
            $this->db->query(
                "UPDATE {$appointments} "
                . "SET status = 'Cancelada' "
                . "WHERE is_unavailability = 0 "
                . "AND status IN ('Cancelled','Canceled')"
            );

            // Backfill remaining real bookings with non-Spanish or empty statuses.
            $this->db->query(
                "UPDATE {$appointments} "
                . "SET status = 'Confirmada' "
                . "WHERE is_unavailability = 0 "
                . "AND (status IS NULL "
                . " OR status = '' "
                . " OR status NOT IN ('Confirmada','Atendida','No se presentó','Cancelada'))"
            );
        }

        $spanish_options = json_encode(
            ['Confirmada', 'Atendida', 'No se presentó', 'Cancelada'],
            JSON_UNESCAPED_UNICODE,
        );

        if ($this->db->get_where('settings', ['name' => 'appointment_status_options'])->num_rows()) {
            $this->db->update(
                'settings',
                ['value' => $spanish_options],
                ['name' => 'appointment_status_options'],
            );
        } else {
            $this->db->insert('settings', [
                'name' => 'appointment_status_options',
                'value' => $spanish_options,
            ]);
        }
    }

    public function down(): void
    {
        $appointments = $this->db->dbprefix('appointments');

        if ($this->db->field_exists('status', 'appointments')) {
            $this->db->query(
                "ALTER TABLE {$appointments} MODIFY COLUMN status VARCHAR(512) NOT NULL DEFAULT ''"
            );
        }

        if ($this->db->get_where('settings', ['name' => 'appointment_status_options'])->num_rows()) {
            $this->db->update(
                'settings',
                ['value' => '["Booked", "Confirmed", "Rescheduled", "Cancelled", "Draft"]'],
                ['name' => 'appointment_status_options'],
            );
        }
    }
}
