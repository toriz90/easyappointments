<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Adds the `cancellation_cutoff_hours` setting: how many hours before an appointment's start
 * time a customer is still allowed to cancel it from their own booking link. This is a separate
 * rule from `minimum_advance_booking` (which only governs how far ahead a date can be booked).
 *
 * @property CI_DB_query_builder $db
 */
class Migration_Add_cancellation_cutoff_hours_setting extends CI_Migration
{
    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'cancellation_cutoff_hours'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'cancellation_cutoff_hours',
                'value' => '24', // hours
            ]);
        }
    }

    /**
     * Downgrade method.
     *
     * @throws Exception
     */
    public function down(): void
    {
        if ($this->db->get_where('settings', ['name' => 'cancellation_cutoff_hours'])->num_rows()) {
            $this->db->delete('settings', ['name' => 'cancellation_cutoff_hours']);
        }
    }
}
