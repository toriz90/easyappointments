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

class Migration_Add_folio_column_to_appointments_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $table = $this->db->dbprefix('appointments');

        if (!$this->db->field_exists('folio', 'appointments')) {
            $fields = [
                'folio' => [
                    'type' => 'VARCHAR',
                    'constraint' => '20',
                    'null' => true,
                    'after' => 'hash',
                ],
            ];

            $this->dbforge->add_column('appointments', $fields);
        }

        // Add a UNIQUE index over `folio`. NULL values are allowed multiple times by MySQL
        // under a UNIQUE constraint, so existing rows without a folio remain valid.
        $existing = $this->db
            ->query("SHOW INDEX FROM {$table} WHERE Key_name = 'appointments_folio_unique'")
            ->result_array();

        if (empty($existing)) {
            $this->db->query("ALTER TABLE {$table} ADD UNIQUE INDEX appointments_folio_unique (folio)");
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $table = $this->db->dbprefix('appointments');

        $existing = $this->db
            ->query("SHOW INDEX FROM {$table} WHERE Key_name = 'appointments_folio_unique'")
            ->result_array();

        if (!empty($existing)) {
            $this->db->query("ALTER TABLE {$table} DROP INDEX appointments_folio_unique");
        }

        if ($this->db->field_exists('folio', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'folio');
        }
    }
}
