<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Rename the "Detalles" custom field (id 10) to "Motivo de servicio".
 *
 * Only updates `ea_custom_fields.label`, the field definition. Appointments saved before this
 * migration keep their own copy of the old label inside `ea_appointments.custom_fields` (JSON
 * snapshot taken at booking time) — those are intentionally left untouched. Code that reads the
 * "motivo" value must accept both labels (see `Appointments_model::find_custom_field_value()`).
 */
class Migration_Rename_detalles_custom_field_label extends EA_Migration
{
    private const FIELD_ID = 10;
    private const OLD_LABEL = 'Detalles';
    private const NEW_LABEL = 'Motivo de servicio';

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if ($this->db->table_exists('custom_fields')) {
            $this->db
                ->where('id', self::FIELD_ID)
                ->where('label', self::OLD_LABEL)
                ->update('custom_fields', ['label' => self::NEW_LABEL]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('custom_fields')) {
            $this->db
                ->where('id', self::FIELD_ID)
                ->where('label', self::NEW_LABEL)
                ->update('custom_fields', ['label' => self::OLD_LABEL]);
        }
    }
}
