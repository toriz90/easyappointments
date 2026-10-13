<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.3.2
 * ---------------------------------------------------------------------------- */

use Jsvrcek\ICS\Exception\CalendarEventException;

require_once __DIR__ . '/Google.php';
require_once __DIR__ . '/Caldav.php';

/**
 * Console controller.
 *
 * Handles all the Console related operations.
 */
class Console extends EA_Controller
{
    /**
     * Console constructor.
     */
    public function __construct()
    {
        if (!is_cli()) {
            exit('No direct script access allowed');
        }

        parent::__construct();

        $this->load->dbutil();

        $this->load->library('instance');
        $this->load->library('cleanup');

        $this->load->model('admins_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
    }

    /**
     * Perform a console installation.
     *
     * Use this method to install Easy!Appointments directly from the terminal.
     *
     * Usage:
     *
     * php index.php console install
     *
     * @throws Exception
     */
    public function install(): void
    {
        $this->instance->migrate('fresh');

        $password = $this->instance->seed();

        response(
            PHP_EOL . '⇾ Installation completed, login with "administrator" / "' . $password . '".' . PHP_EOL . PHP_EOL,
        );
    }

    /**
     * Migrate the database to the latest state.
     *
     * Use this method to upgrade an Easy!Appointments instance to the latest database state.
     *
     * Notice:
     *
     * Do not use this method to install the app as it will not seed the database with the initial entries (admin,
     * provider, service, settings etc.).
     *
     * Usage:
     *
     * php index.php console migrate
     *
     * php index.php console migrate fresh
     *
     * @param string $type
     */
    public function migrate(string $type = ''): void
    {
        $this->instance->migrate($type);
    }

    /**
     * Seed the database with test data.
     *
     * Use this method to add test data to your database
     *
     * Usage:
     *
     * php index.php console seed
     * @throws Exception
     */
    public function seed(): void
    {
        $this->instance->seed();
    }

    /**
     * Create a database backup file.
     *
     * Use this method to back up your Easy!Appointments data.
     *
     * Usage:
     *
     * php index.php console backup
     *
     * php index.php console backup /path/to/backup/folder
     *
     * @throws Exception
     */
    public function backup(): void
    {
        $this->instance->backup($GLOBALS['argv'][3] ?? null);
    }

    /**
     * Trigger the synchronization of all provider calendars with Google Calendar.
     *
     * Use this method in a cronjob to automatically sync events between Easy!Appointments and Google Calendar.
     *
     * Notice:
     *
     * Google syncing must first be enabled for each individual provider from inside the backend calendar page.
     *
     * Usage:
     *
     * php index.php console sync
     *
     * @throws CalendarEventException
     * @throws Exception
     * @throws Throwable
     */
    public function sync(): void
    {
        $providers = $this->providers_model->get();

        foreach ($providers as $provider) {
            if (filter_var($provider['settings']['google_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Google::sync((string) $provider['id']);
            }

            if (filter_var($provider['settings']['caldav_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Caldav::sync((string) $provider['id']);
            }
        }
    }

    /**
     * Clean up old customer data based on data retention settings.
     *
     * Use this method in a cronjob to automatically delete customer data older than the configured retention period.
     *
     * Usage:
     *
     * php index.php console cleanup
     *
     * @throws Exception
     */
    public function cleanup(): void
    {
        $this->cleanup->run();
    }

    /**
     * Show help information about the console capabilities.
     *
     * Use this method to see the available commands.
     *
     * Usage:
     *
     * php index.php console help
     */
    public function help(): void
    {
        $help = [
            '',
            'Easy!Appointments ' . config('version'),
            '',
            'Usage:',
            '',
            '⇾ php index.php console [command] [arguments]',
            '',
            'Commands:',
            '',
            '⇾ php index.php console migrate',
            '⇾ php index.php console migrate fresh',
            '⇾ php index.php console migrate up',
            '⇾ php index.php console migrate down',
            '⇾ php index.php console seed',
            '⇾ php index.php console install',
            '⇾ php index.php console backup',
            '⇾ php index.php console sync',
            '⇾ php index.php console cleanup    (cleans sessions, logs, cache, and customer data)',
            '⇾ php index.php console test_advance_booking',
            '',
            '',
        ];

        response(implode(PHP_EOL, $help));
    }

    /**
     * Print the minimum_advance_booking rule's effect for a fixed set of "now" scenarios.
     *
     * For each scenario, prints what earliest_bookable_datetime() returns and the first calendar
     * day, from that point on, that actually has an available slot for a real service/provider
     * pairing - exercising the exact same Availability::get_available_hours() code path the
     * public booking page uses. Useful to verify that the rule is counted in calendar days
     * (business timezone) and no longer depends on the time of day "now" happens to be.
     *
     * Usage:
     *
     * php index.php console test_advance_booking
     *
     * @throws Exception
     */
    public function test_advance_booking(): void
    {
        $this->load->library('availability');

        $business_timezone = new DateTimeZone(setting('default_timezone') ?: 'America/Mexico_City');

        [$service, $provider] = $this->find_test_service_and_provider();

        $lines = [
            '',
            'minimum_advance_booking = ' . setting('minimum_advance_booking', '0') . ' day(s)',
            'business timezone       = ' . $business_timezone->getName(),
        ];

        if ($service === null || $provider === null) {
            $lines[] = '';
            $lines[] = 'No active service/provider pairing found - cannot test available-hours search.';
            $lines[] = 'earliest_bookable_datetime() itself does not need one, see the dates below.';
        } else {
            $lines[] = 'test service             = ' . $service['name'] . ' (id ' . $service['id'] . ')';
            $lines[] = 'test provider            = ' . $provider['first_name'] . ' ' . $provider['last_name'] .
                ' (id ' . $provider['id'] . ')';
        }

        $lines[] = '';

        // Fixed calendar dates so the output is deterministic and repeatable.
        $scenarios = [
            'Lunes 08:00' => '2026-10-19 08:00:00',
            'Lunes 21:00' => '2026-10-19 21:00:00',
            'Viernes 15:00' => '2026-10-16 15:00:00',
            'Sábado 12:00' => '2026-10-17 12:00:00',
        ];

        foreach ($scenarios as $label => $now_string) {
            $now = new DateTime($now_string, $business_timezone);

            $earliest = earliest_bookable_datetime($now);

            $lines[] = '--- ' . $label . ' (now = ' . $now->format('Y-m-d H:i') . ', ' . $now->format('l') . ') ---';
            $lines[] = '  earliest_bookable_datetime() = ' . $earliest->format('Y-m-d H:i:s') . ' (' . $earliest->format('l') . ')';

            if ($service !== null && $provider !== null) {
                $first_available_day = $this->find_first_available_day($earliest, $service, $provider);

                $lines[] = '  first day with available hours = ' . ($first_available_day ?? 'none found within 14 days');
            }

            $lines[] = '';
        }

        response(implode(PHP_EOL, $lines));
    }

    /**
     * Find the first active service and a provider assigned to it, for test_advance_booking().
     *
     * @return array{0: array|null, 1: array|null}
     */
    private function find_test_service_and_provider(): array
    {
        $services = $this->services_model->get();

        foreach ($services as $service) {
            $providers = $this->providers_model->get_available_providers(true);

            foreach ($providers as $provider) {
                if (in_array((int) $service['id'], $provider['services'], true)) {
                    return [$service, $provider];
                }
            }
        }

        return [null, null];
    }

    /**
     * Scan forward (up to 14 days) from $from_date for the first day with available hours.
     *
     * @throws Exception
     */
    private function find_first_available_day(DateTime $from_date, array $service, array $provider): ?string
    {
        $candidate = clone $from_date;

        for ($i = 0; $i < 14; $i++) {
            $available_hours = $this->availability->get_available_hours(
                $candidate->format('Y-m-d'),
                $service,
                $provider,
            );

            if (!empty($available_hours)) {
                return $candidate->format('Y-m-d') . ' (' . $candidate->format('l') . '), first slot: ' . $available_hours[0];
            }

            $candidate->modify('+1 day');
        }

        return null;
    }
}
