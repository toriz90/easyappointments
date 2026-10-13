<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Adds `required_in_backend` to `custom_fields`: lets a field be required on the public booking
 * form without also being required when staff create/edit an appointment from the admin panel.
 * Defaults to 1 (required everywhere, i.e. today's behavior) for every existing field, except
 * field 10 ("Motivo de servicio"), which only needs to be required on the public side.
 */
class Migration_Add_required_in_backend_to_custom_fields extends EA_Migration
{
    private const FIELD_ID = 10;

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('custom_fields')) {
            return;
        }

        if (!$this->db->field_exists('required_in_backend', 'custom_fields')) {
            $this->dbforge->add_column('custom_fields', [
                'required_in_backend' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                    'null' => false,
                    'after' => 'required',
                ],
            ]);
        }

        $this->db
            ->where('id', self::FIELD_ID)
            ->update('custom_fields', ['required_in_backend' => 0]);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if (!$this->db->table_exists('custom_fields')) {
            return;
        }

        if ($this->db->field_exists('required_in_backend', 'custom_fields')) {
            $this->dbforge->drop_column('custom_fields', 'required_in_backend');
        }
    }
}
