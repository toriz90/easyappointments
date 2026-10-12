<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Record when the customer accepted the "accessories commitment" checkbox during public
 * booking. NULL means the commitment was never accepted (appointments created before this
 * feature, or appointments not subject to it).
 */
class Migration_Add_accessories_commitment_to_appointments_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('accessories_commitment_at', 'appointments')) {
            $fields = [
                'accessories_commitment_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'custom_fields',
                ],
            ];

            $this->dbforge->add_column('appointments', $fields);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('accessories_commitment_at', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'accessories_commitment_at');
        }
    }
}
