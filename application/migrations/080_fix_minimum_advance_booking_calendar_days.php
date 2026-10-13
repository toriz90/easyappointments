<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Sets `minimum_advance_booking` to 2 (calendar days: no same-day, no next-day booking) and
 * keeps `book_advance_timeout` at the equivalent 2880 minutes for consistency, even though
 * Availability::consider_book_advance_timeout() no longer reads it directly (it now uses
 * earliest_bookable_datetime(), see date_helper.php) - the minutes value is kept in sync here,
 * and by the business settings page on every save, purely so the two settings never show
 * contradictory numbers to an admin inspecting the database.
 */
class Migration_Fix_minimum_advance_booking_calendar_days extends CI_Migration
{
    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        $this->set_setting('minimum_advance_booking', '2');
        $this->set_setting('book_advance_timeout', '2880');
    }

    /**
     * Downgrade method.
     *
     * This only restores the pre-migration values; it does not attempt to undo the
     * calendar-day vs. rolling-minutes behavior change in Availability.php.
     *
     * @throws Exception
     */
    public function down(): void
    {
        $this->set_setting('minimum_advance_booking', '0');
        $this->set_setting('book_advance_timeout', '0');
    }

    /**
     * Insert or update a settings row.
     *
     * @throws Exception
     */
    private function set_setting(string $name, string $value): void
    {
        if ($this->db->get_where('settings', ['name' => $name])->num_rows()) {
            $this->db->update('settings', ['value' => $value], ['name' => $name]);
        } else {
            $this->db->insert('settings', ['name' => $name, 'value' => $value]);
        }
    }
}
