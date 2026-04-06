<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

/**
 * Notifications library.
 *
 * Handles the notifications related functionality.
 *
 * @package Libraries
 */
class Notifications
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Notifications constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('settings_model');

        $this->CI->load->library('email_messages');
        $this->CI->load->library('ics_file');
        $this->CI->load->library('timezones');
    }

    /**
     * Send the required notifications, related to an appointment creation/modification.
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     * @param bool|false $manage_mode Manage mode.
     */
    public function notify_appointment_saved(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
        bool $manage_mode = false,
    ): void {
        $appointment_id = $appointment['id'] ?? null;

        try {
            $current_language = config('language');

            $this->log_email($appointment_id, 'saved', 'system', '', 'started',
                'manage_mode=' . ($manage_mode ? 'yes' : 'no') .
                ' customer=' . ($customer['email'] ?? '-') .
                ' customer_notifications=' . setting('customer_notifications'));

            $customer_link = site_url('booking/reschedule/' . $appointment['hash']);
            $provider_link = site_url('calendar/reschedule/' . $appointment['hash']);
            $ics_stream = $this->CI->ics_file->get_stream($appointment, $service, $provider, $customer);

            // Notify customer.
            $send_customer =
                !empty($customer['email']) && filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN);

            if ($send_customer === true) {
                config(['language' => $customer['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_booked');
                $message = $manage_mode ? '' : lang('thank_you_for_appointment');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment, $provider, $service, $customer, $settings,
                        $subject, $message, $customer_link,
                        $customer['email'], $ics_stream, $customer['timezone'],
                    );
                    $this->log_email($appointment_id, 'saved', 'customer', $customer['email'], 'sent');
                } catch (Throwable $e) {
                    $this->log_email($appointment_id, 'saved', 'customer', $customer['email'], 'error', $e->getMessage());
                    $this->log_exception($e, 'appointment-saved to customer', $appointment_id);
                }
            } else {
                $this->log_email($appointment_id, 'saved', 'customer', $customer['email'] ?? '', 'skipped',
                    'customer_notifications=' . setting('customer_notifications') . ' email=' . ($customer['email'] ?? 'empty'));
            }

            // Notify provider.
            $send_provider = filter_var(
                $this->CI->providers_model->get_setting($provider['id'], 'notifications'),
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($send_provider === true) {
                config(['language' => $provider['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment, $provider, $service, $customer, $settings,
                        $subject, $message, $provider_link,
                        $provider['email'], $ics_stream, $provider['timezone'],
                    );
                    $this->log_email($appointment_id, 'saved', 'provider', $provider['email'], 'sent');
                } catch (Throwable $e) {
                    $this->log_email($appointment_id, 'saved', 'provider', $provider['email'], 'error', $e->getMessage());
                    $this->log_exception($e, 'appointment-saved to provider', $appointment_id);
                }
            } else {
                $this->log_email($appointment_id, 'saved', 'provider', $provider['email'] ?? '', 'skipped',
                    'provider_notifications=off');
            }

            // Notify admins.
            $admins = $this->CI->admins_model->get();

            foreach ($admins as $admin) {
                if ($admin['settings']['notifications'] === '0') {
                    $this->log_email($appointment_id, 'saved', 'admin', $admin['email'], 'skipped', 'notifications=off');
                    continue;
                }

                config(['language' => $admin['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment, $provider, $service, $customer, $settings,
                        $subject, $message, $provider_link,
                        $admin['email'], $ics_stream, $admin['timezone'],
                    );
                    $this->log_email($appointment_id, 'saved', 'admin', $admin['email'], 'sent');
                } catch (Throwable $e) {
                    $this->log_email($appointment_id, 'saved', 'admin', $admin['email'], 'error', $e->getMessage());
                    $this->log_exception($e, 'appointment-saved to admin', $appointment_id);
                }
            }

            // Notify secretaries.
            $secretaries = $this->CI->secretaries_model->get();

            foreach ($secretaries as $secretary) {
                if ($secretary['settings']['notifications'] === '0') {
                    $this->log_email($appointment_id, 'saved', 'secretary', $secretary['email'], 'skipped', 'notifications=off');
                    continue;
                }

                if (!in_array($provider['id'], $secretary['providers'])) {
                    continue;
                }

                config(['language' => $secretary['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment, $provider, $service, $customer, $settings,
                        $subject, $message, $provider_link,
                        $secretary['email'], $ics_stream, $secretary['timezone'],
                    );
                    $this->log_email($appointment_id, 'saved', 'secretary', $secretary['email'], 'sent');
                } catch (Throwable $e) {
                    $this->log_email($appointment_id, 'saved', 'secretary', $secretary['email'], 'error', $e->getMessage());
                    $this->log_exception($e, 'appointment-saved to secretary', $appointment_id);
                }
            }
        } catch (Throwable $e) {
            $this->log_email($appointment_id, 'saved', 'system', '', 'error', 'General exception: ' . $e->getMessage());
            $this->log_exception($e, 'appointment-saved (general exception)', $appointment_id);
        } finally {
            config(['language' => $current_language ?? 'english']);
            $this->CI->lang->load('translations');
        }
    }

    /**
     * Send the required notifications, related to an appointment removal.
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     */
    public function notify_appointment_deleted(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
        string $cancellation_reason = '',
    ): void {
        $appointment_id = $appointment['id'] ?? null;

        try {
            $current_language = config('language');

            $this->log_email($appointment_id, 'deleted', 'system', '', 'started',
                'reason=' . ($cancellation_reason ?: 'none') . ' customer=' . ($customer['email'] ?? '-'));

            // Notify provider.
            $send_provider = filter_var(
                $this->CI->providers_model->get_setting($provider['id'], 'notifications'),
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($send_provider === true) {
                config(['language' => $provider['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment, $provider, $service, $customer, $settings,
                        $provider['email'], $cancellation_reason, $provider['timezone'],
                    );
                    $this->log_email($appointment_id, 'deleted', 'provider', $provider['email'], 'sent');
                } catch (Throwable $e) {
                    $this->log_email($appointment_id, 'deleted', 'provider', $provider['email'], 'error', $e->getMessage());
                    $this->log_exception($e, 'appointment-deleted to provider', $appointment_id);
                }
            } else {
                $this->log_email($appointment_id, 'deleted', 'provider', $provider['email'] ?? '', 'skipped', 'notifications=off');
            }

            // Notify customer.
            $send_customer =
                !empty($customer['email']) && filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN);

            if ($send_customer === true) {
                config(['language' => $customer['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment, $provider, $service, $customer, $settings,
                        $customer['email'], $cancellation_reason, $customer['timezone'],
                    );
                    $this->log_email($appointment_id, 'deleted', 'customer', $customer['email'], 'sent');
                } catch (Throwable $e) {
                    $this->log_email($appointment_id, 'deleted', 'customer', $customer['email'], 'error', $e->getMessage());
                    $this->log_exception($e, 'appointment-deleted to customer', $appointment_id);
                }
            } else {
                $this->log_email($appointment_id, 'deleted', 'customer', $customer['email'] ?? '', 'skipped',
                    'customer_notifications=' . setting('customer_notifications') . ' email=' . ($customer['email'] ?? 'empty'));
            }

            // Notify admins.
            $admins = $this->CI->admins_model->get();

            foreach ($admins as $admin) {
                if ($admin['settings']['notifications'] === '0') {
                    $this->log_email($appointment_id, 'deleted', 'admin', $admin['email'], 'skipped', 'notifications=off');
                    continue;
                }

                config(['language' => $admin['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment, $provider, $service, $customer, $settings,
                        $admin['email'], $cancellation_reason, $admin['timezone'],
                    );
                    $this->log_email($appointment_id, 'deleted', 'admin', $admin['email'], 'sent');
                } catch (Throwable $e) {
                    $this->log_email($appointment_id, 'deleted', 'admin', $admin['email'], 'error', $e->getMessage());
                    $this->log_exception($e, 'appointment-deleted to admin', $appointment_id);
                }
            }

            // Notify secretaries.
            $secretaries = $this->CI->secretaries_model->get();

            foreach ($secretaries as $secretary) {
                if ($secretary['settings']['notifications'] === '0') {
                    $this->log_email($appointment_id, 'deleted', 'secretary', $secretary['email'], 'skipped', 'notifications=off');
                    continue;
                }

                if (!in_array($provider['id'], $secretary['providers'])) {
                    continue;
                }

                config(['language' => $secretary['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment, $provider, $service, $customer, $settings,
                        $secretary['email'], $cancellation_reason, $secretary['timezone'],
                    );
                    $this->log_email($appointment_id, 'deleted', 'secretary', $secretary['email'], 'sent');
                } catch (Throwable $e) {
                    $this->log_email($appointment_id, 'deleted', 'secretary', $secretary['email'], 'error', $e->getMessage());
                    $this->log_exception($e, 'appointment-deleted to secretary', $appointment_id);
                }
            }
        } catch (Throwable $e) {
            $this->log_email($appointment_id, 'deleted', 'system', '', 'error', 'General exception: ' . $e->getMessage());
            log_message('error', 'Notifications - Could not email cancellation details of appointment (' .
                ($appointment_id ?? '-') . ') : ' . $e->getMessage());
            log_message('error', $e->getTraceAsString());
        } finally {
            config(['language' => $current_language ?? 'english']);
            $this->CI->lang->load('translations');
        }
    }

    private function log_exception(Throwable $e, string $message, ?int $appointment_id): void
    {
        log_message(
            'error',
            'Notifications - Could not email ' . $message . ' (' . ($appointment_id ?? '-') . ') : ' . $e->getMessage(),
        );
        log_message('error', $e->getTraceAsString());
    }

    /**
     * Write a structured entry to the dedicated email log file.
     *
     * @param int|null    $appointment_id
     * @param string      $event            'saved' | 'deleted'
     * @param string      $recipient_type   'customer' | 'provider' | 'admin' | 'secretary'
     * @param string      $recipient_email
     * @param string      $status           'sent' | 'error' | 'skipped'
     * @param string      $detail           Error message or skip reason
     */
    private function log_email(
        ?int $appointment_id,
        string $event,
        string $recipient_type,
        string $recipient_email,
        string $status,
        string $detail = '',
    ): void {
        // Resolve storage/logs path robustly for Docker volume mounts
        $base = realpath(APPPATH . '..') ?: realpath(APPPATH . '../') ?: dirname(APPPATH);
        $log_path = rtrim($base, '/') . '/storage/logs/email_log.csv';
        $new_file = !file_exists($log_path);

        $line = implode(',', [
            date('Y-m-d H:i:s'),
            $appointment_id ?? '-',
            $event,
            $recipient_type,
            $recipient_email ?: '-',
            $status,
            '"' . str_replace('"', '""', $detail) . '"',
        ]) . PHP_EOL;

        if ($new_file) {
            file_put_contents($log_path, "datetime,appointment_id,event,recipient_type,recipient_email,status,detail\n");
        }

        $written = file_put_contents($log_path, $line, FILE_APPEND | LOCK_EX);

        // Always mirror to docker stderr (visible via docker logs) and CI log.
        $log_entry = "[EMAIL_LOG] appt={$appointment_id} event={$event} to={$recipient_email} ({$recipient_type}) {$status}" .
            ($detail ? " | {$detail}" : '') .
            ($written === false ? " | [FILE WRITE FAILED: {$log_path}]" : '');

        error_log($log_entry);

        if ($status === 'error') {
            log_message('error', $log_entry);
        } else {
            log_message('info', $log_entry);
        }
    }
}
