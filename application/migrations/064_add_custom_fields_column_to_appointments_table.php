<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.2
 * ---------------------------------------------------------------------------- */

class Migration_Add_custom_fields_column_to_appointments_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('custom_fields', 'appointments')) {
            $fields = [
                'custom_fields' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                    'after' => 'notes',
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
        if ($this->db->field_exists('custom_fields', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'custom_fields');
        }
    }
}
