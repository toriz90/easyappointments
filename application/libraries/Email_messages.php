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

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Email messages library.
 *
 * Handles the email messaging related functionality.
 *
 * @package Libraries
 */
class Email_messages
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Email_messages constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('settings_model');

        $this->CI->load->library('email');
        $this->CI->load->library('ics_file');
        $this->CI->load->library('timezones');
        $this->CI->load->library('appointment_confirmation_pdf');
    }

    /**
     * Send an email with the appointment details.
     *
     * @param array $appointment Appointment data.
     * @param array $provider Provider data.
     * @param array $service Service data.
     * @param array $customer Customer data.
     * @param array $settings App settings.
     * @param string $subject Email subject.
     * @param string $message Email message.
     * @param string $appointment_link Appointment unique URL.
     * @param string $recipient_email Recipient email address.
     * @param string $ics_stream ICS file contents.
     * @param string|null $timezone Custom timezone.
     *
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function send_appointment_saved(
        array $appointment,
        array $provider,
        array $service,
        array $customer,
        array $settings,
        string $subject,
        string $message,
        string $appointment_link,
        string $recipient_email,
        string $ics_stream,
        ?string $timezone = null,
    ): void {
        $appointment_timezone = new DateTimeZone($provider['timezone']);

        $appointment_start = new DateTime($appointment['start_datetime'], $appointment_timezone);

        $appointment_end = new DateTime($appointment['end_datetime'], $appointment_timezone);

        if ($timezone && $timezone !== $provider['timezone']) {
            $custom_timezone = new DateTimeZone($timezone);

            $appointment_start->setTimezone($custom_timezone);
            $appointment['start_datetime'] = $appointment_start->format('Y-m-d H:i:s');

            $appointment_end->setTimezone($custom_timezone);
            $appointment['end_datetime'] = $appointment_end->format('Y-m-d H:i:s');
        }

        // Load custom fields for this appointment
        $custom_fields_data = $this->get_appointment_custom_fields($appointment);

        // The logo must be embedded (and its CID known) before the view is rendered, since the
        // template decides whether to reference `cid:company_logo` based on that result.
        $php_mailer = $this->create_php_mailer();
        $company_logo_cid = $this->embed_company_logo($php_mailer) ? 'company_logo' : null;

        $html = $this->CI->load->view(
            'emails/appointment_saved_email',
            [
                'subject' => $subject,
                'message' => $message,
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'timezone' => $timezone,
                'appointment_link' => $appointment_link,
                'custom_fields' => $custom_fields_data,
                'company_logo_cid' => $company_logo_cid,
            ],
            true,
        );

        $this->finalize_php_mailer($php_mailer, $recipient_email, $subject, $html);

        $php_mailer->addStringAttachment($ics_stream, 'invitation.ics', PHPMailer::ENCODING_BASE64, 'text/calendar');

        // Attach the confirmation PDF. Used both when the appointment is first booked and when
        // it is rescheduled/edited; the cancellation email (send_appointment_deleted) intentionally
        // does not include it. A failure here should not prevent the notification from being sent.
        try {
            $pdf_bytes = $this->CI->appointment_confirmation_pdf->generate((int) $appointment['id']);

            $pdf_filename = !empty($appointment['folio'])
                ? 'Confirmacion-' . $appointment['folio'] . '.pdf'
                : 'Confirmacion-cita-' . (int) $appointment['id'] . '.pdf';

            $php_mailer->addStringAttachment(
                $pdf_bytes,
                $pdf_filename,
                PHPMailer::ENCODING_BASE64,
                'application/pdf',
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                'Confirmation PDF could not be attached to email for appointment '
                . ($appointment['id'] ?? '?') . ': ' . $e->getMessage(),
            );
        }

        $php_mailer->send();
    }

    /**
     * Send an email with the appointment removal details.
     *
     * @param array $appointment Appointment data.
     * @param array $provider Provider data.
     * @param array $service Service data.
     * @param array $customer Customer data.
     * @param array $settings App settings.
     * @param string $recipient_email Recipient email address.
     * @param string|null $reason Removal reason.
     * @param string|null $timezone Custom timezone.
     *
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function send_appointment_deleted(
        array $appointment,
        array $provider,
        array $service,
        array $customer,
        array $settings,
        string $recipient_email,
        ?string $reason = null,
        ?string $timezone = null,
    ): void {
        $appointment_timezone = new DateTimeZone($provider['timezone']);

        $appointment_start = new DateTime($appointment['start_datetime'], $appointment_timezone);

        $appointment_end = new DateTime($appointment['end_datetime'], $appointment_timezone);

        if ($timezone && $timezone !== $provider['timezone']) {
            $custom_timezone = new DateTimeZone($timezone);

            $appointment_start->setTimezone($custom_timezone);
            $appointment['start_datetime'] = $appointment_start->format('Y-m-d H:i:s');

            $appointment_end->setTimezone($custom_timezone);
            $appointment['end_datetime'] = $appointment_end->format('Y-m-d H:i:s');
        }

        // Load custom fields for this appointment
        $custom_fields_data = $this->get_appointment_custom_fields($appointment);

        $php_mailer = $this->create_php_mailer();
        $company_logo_cid = $this->embed_company_logo($php_mailer) ? 'company_logo' : null;

        $html = $this->CI->load->view(
            'emails/appointment_deleted_email',
            [
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'timezone' => $timezone,
                'reason' => $reason,
                'custom_fields' => $custom_fields_data,
                'company_logo_cid' => $company_logo_cid,
            ],
            true,
        );

        $subject = lang('appointment_cancelled_title');

        $this->finalize_php_mailer($php_mailer, $recipient_email, $subject, $html);

        $php_mailer->send();
    }

    /**
     * Send the account recovery details.
     *
     * @param string $password New password.
     * @param string $recipient_email Recipient email address.
     * @param array $settings App settings.
     *
     * @throws Exception
     */
    public function send_password(string $password, string $recipient_email, array $settings): void
    {
        $php_mailer = $this->create_php_mailer();
        $company_logo_cid = $this->embed_company_logo($php_mailer) ? 'company_logo' : null;

        $html = $this->CI->load->view(
            'emails/account_recovery_email',
            [
                'subject' => lang('new_account_password'),
                'message' => str_replace('$password', '<strong>' . $password . '</strong>', lang('new_password_is')),
                'settings' => $settings,
                'company_logo_cid' => $company_logo_cid,
            ],
            true,
        );

        $subject = lang('new_account_password');

        $this->finalize_php_mailer($php_mailer, $recipient_email, $subject, $html);

        $php_mailer->send();
    }

    /**
     * Send a test email to verify SMTP configuration.
     *
     * @param string $recipient_email
     *
     * @throws Exception
     */
    public function send_test(string $recipient_email): void
    {
        $from_name = setting('from_name') ?: config('from_name') ?: setting('company_name');
        $subject = 'Correo de prueba — ' . ($from_name ?: 'EasyAppointments');
        $html = '<p>Si recibes este correo, la configuración SMTP es correcta.</p>';

        $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);
        $php_mailer->send();
    }

    /**
     * Send the password reset link.
     *
     * @param string $reset_link The password reset URL.
     * @param string $recipient_email Recipient email address.
     * @param array $settings App settings.
     *
     * @throws Exception
     */
    public function send_password_reset_link(string $reset_link, string $recipient_email, array $settings): void
    {
        $php_mailer = $this->create_php_mailer();
        $company_logo_cid = $this->embed_company_logo($php_mailer) ? 'company_logo' : null;

        $html = $this->CI->load->view(
            'emails/password_reset_email',
            [
                'subject' => lang('password_reset_request'),
                'message' => lang('password_reset_email_message'),
                'reset_link' => $reset_link,
                'settings' => $settings,
                'company_logo_cid' => $company_logo_cid,
            ],
            true,
        );

        $subject = lang('password_reset_request');

        $this->finalize_php_mailer($php_mailer, $recipient_email, $subject, $html);

        $php_mailer->send();
    }

    /**
     * Create a PHP Mailer instance (SMTP transport + from/reply-to) with no recipient, subject,
     * body or attachments set yet.
     *
     * Split out from the former `get_php_mailer()` so callers that need to know whether the
     * company logo got embedded (to decide what to render in the email template) can do so
     * before rendering: create the mailer, call `embed_company_logo()`, render the view with
     * that result, then call `finalize_php_mailer()` to attach the recipient/subject/body.
     *
     * @return PHPMailer
     *
     * @throws Exception
     */
    private function create_php_mailer(): PHPMailer
    {
        $php_mailer = new PHPMailer(true);

        $php_mailer->CharSet = 'UTF-8';
        $php_mailer->SMTPDebug = config('smtp_debug') ? SMTP::DEBUG_SERVER : null;

        if (config('protocol') === 'smtp') {
            $php_mailer->isSMTP();
            // DB settings override config file values (set from backend email settings page)
            $php_mailer->Host       = setting('smtp_host')   ?: config('smtp_host');
            $php_mailer->SMTPAuth   = (bool) (setting('smtp_auth') ?? config('smtp_auth'));
            $php_mailer->Username   = setting('smtp_user')   ?: config('smtp_user');
            $php_mailer->Password   = setting('smtp_pass')   ?: config('smtp_pass');
            $php_mailer->SMTPSecure = setting('smtp_crypto') ?: config('smtp_crypto');
            $php_mailer->Port       = (int) (setting('smtp_port') ?: config('smtp_port'));
        }

        $from_name = setting('from_name') ?: config('from_name') ?: setting('company_name');
        $from_address = setting('from_address') ?: config('from_address') ?: setting('company_email');
        $reply_to_address = setting('reply_to') ?: config('reply_to') ?: setting('company_email');

        $php_mailer->setFrom($from_address, $from_name);
        $php_mailer->addReplyTo($reply_to_address);

        return $php_mailer;
    }

    /**
     * Attach the recipient, subject and body to an already created PHP Mailer instance.
     *
     * @param PHPMailer $php_mailer Instance returned by `create_php_mailer()`.
     * @param string|null $recipient_email
     * @param string|null $subject
     * @param string|null $html
     *
     * @return PHPMailer The same instance, for chaining.
     */
    private function finalize_php_mailer(
        PHPMailer $php_mailer,
        ?string $recipient_email = null,
        ?string $subject = null,
        ?string $html = null,
    ): PHPMailer {
        if ($recipient_email) {
            $php_mailer->addAddress($recipient_email);
        }

        if ($subject) {
            $php_mailer->Subject = $subject;
        }

        if ($html) {
            $plain_text = str_replace(["\n\n", "\n\n\n"], '', strip_tags($html));

            if (config('mailtype') === 'html') {
                $php_mailer->isHTML();
            } else {
                $html = $plain_text;
            }

            $php_mailer->Body = $html;
            $php_mailer->AltBody = $plain_text;
        }

        return $php_mailer;
    }

    /**
     * Create PHP Mailer instance based on the email configuration.
     *
     * Convenience wrapper around create_php_mailer() + embed_company_logo() +
     * finalize_php_mailer() for simple callers that do not need to reference the logo's CID in
     * their own view (e.g. send_test(), which renders no template at all).
     *
     * @param string|null $recipient_email
     * @param string|null $subject
     * @param string|null $html
     *
     * @return PHPMailer
     *
     * @throws Exception
     */
    private function get_php_mailer(
        ?string $recipient_email = null,
        ?string $subject = null,
        ?string $html = null,
    ): PHPMailer {
        $php_mailer = $this->create_php_mailer();

        $this->embed_company_logo($php_mailer);

        return $this->finalize_php_mailer($php_mailer, $recipient_email, $subject, $html);
    }

    /**
     * Embed the configured company logo as a CID image in the given PHPMailer instance.
     *
     * `setting('company_logo')` is either a `data:image/...;base64,...` string (uploaded and
     * stored inline) or a filename living under `storage/uploads/`. Either way it is embedded as
     * a CID attachment rather than referenced directly in the HTML, because Gmail strips `data:`
     * URIs used as an `<img src>` and would otherwise show a broken image.
     *
     * Callers must check the return value (not a `$settings['company_logo']` array key, which is
     * never guaranteed to be populated the same way the global `setting()` helper is) to decide
     * whether to pass a `company_logo_cid` of `'company_logo'` or `null` into their email view;
     * the template then renders `<img src="cid:company_logo">` only when it received a non-null
     * value, falling back to the company name as text otherwise.
     *
     * @return bool True if the logo was embedded (the view may reference `cid:company_logo`),
     *              false if there is no company logo configured or it could not be read.
     */
    private function embed_company_logo(PHPMailer $php_mailer): bool
    {
        $company_logo = setting('company_logo');

        if (empty($company_logo)) {
            return false;
        }

        $cid = 'company_logo';

        if (str_starts_with($company_logo, 'data:image')) {
            if (!preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,(.+)$/', $company_logo, $matches)) {
                return false;
            }

            [, $image_type, $base64_data] = $matches;

            $image_data = base64_decode($base64_data, true);

            if ($image_data === false) {
                return false;
            }

            $php_mailer->addStringEmbeddedImage(
                $image_data,
                $cid,
                'company_logo.' . $image_type,
                PHPMailer::ENCODING_BASE64,
                'image/' . $image_type,
            );

            return true;
        }

        $logo_path = FCPATH . 'storage/uploads/' . $company_logo;

        if (!is_file($logo_path)) {
            return false;
        }

        $mime_type = mime_content_type($logo_path) ?: 'image/png';

        $php_mailer->addEmbeddedImage($logo_path, $cid, basename($logo_path), 'base64', $mime_type);

        return true;
    }

    /**
     * Get custom fields data for an appointment formatted for email display.
     *
     * @param array $appointment
     * @return array
     */
    private function get_appointment_custom_fields(array $appointment): array
    {
        try {
            // Check if custom_fields already exist in the appointment array
            if (!empty($appointment['custom_fields'])) {
                $custom_fields_json = $appointment['custom_fields'];

                // Parse if it's a JSON string
                if (is_string($custom_fields_json)) {
                    $custom_fields_json = json_decode($custom_fields_json, true);
                }

                if (is_array($custom_fields_json)) {
                    $custom_fields_data = [];

                    foreach ($custom_fields_json as $field_id => $field_data) {
                        if (!empty($field_data['value'])) {
                            $custom_fields_data[] = [
                                'label' => $field_data['label'] ?? '',
                                'value' => $field_data['value'],
                                'type' => $field_data['type'] ?? 'text',
                            ];
                        }
                    }

                    return $custom_fields_data;
                }
            }

            // If custom_fields is not in appointment, load from database
            if (!empty($appointment['id_users_customer'])) {
                $this->CI->load->model('custom_field_values_model');
                $this->CI->load->model('custom_fields_model');

                $custom_field_values = $this->CI->custom_field_values_model->get(['id_users' => $appointment['id_users_customer']]);
                $custom_fields_data = [];

                foreach ($custom_field_values as $value) {
                    $custom_field = $this->CI->custom_fields_model->find($value['id_custom_fields']);
                    if ($custom_field && $custom_field['active']) {
                        $custom_fields_data[] = [
                            'label' => $custom_field['label'],
                            'value' => $value['value'],
                            'type' => $custom_field['type'],
                        ];
                    }
                }

                return $custom_fields_data;
            }

            return [];
        } catch (Exception $e) {
            return [];
        }
    }
}
