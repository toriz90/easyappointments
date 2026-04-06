<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Email settings controller.
 *
 * Handles SMTP / email configuration from the backend UI.
 */
class Email_settings extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('settings_model');
        $this->load->library('accounts');
    }

    public function index(): void
    {
        session(['dest_url' => site_url('email_settings')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }
            redirect('login');
            return;
        }

        $role_slug = session('role_slug');

        // DB values take priority; fallback to config file values
        $fields = ['smtp_host', 'smtp_port', 'smtp_crypto', 'smtp_user', 'smtp_pass', 'from_name', 'from_address', 'reply_to'];
        $email_settings = [];
        foreach ($fields as $field) {
            $db_value = $this->settings_model->query()->where('name', $field)->get()->row_array();
            $email_settings[] = [
                'name'  => $field,
                'value' => !empty($db_value['value']) ? $db_value['value'] : config($field),
            ];
        }

        script_vars([
            'user_id'        => $user_id,
            'role_slug'      => $role_slug,
            'email_settings' => $email_settings,
        ]);

        html_vars([
            'page_title'        => lang('settings'),
            'active_menu'       => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
        ]);

        $this->load->view('pages/email_settings');
    }

    public function save(): void
    {
        try {
            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $settings = request('email_settings', []);

            foreach ($settings as $setting) {
                // Don't overwrite password if an empty placeholder was submitted
                if ($setting['name'] === 'smtp_pass' && $setting['value'] === '') {
                    continue;
                }

                $existing = $this->settings_model
                    ->query()
                    ->where('name', $setting['name'])
                    ->get()
                    ->row_array();

                if (!empty($existing)) {
                    $setting['id'] = $existing['id'];
                }

                $this->settings_model->save($setting);
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function test(): void
    {
        try {
            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $recipient = request('recipient');

            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid recipient email address.');
            }

            $this->load->library('email_messages');
            $this->email_messages->send_test($recipient);

            json_response(['success' => true, 'message' => 'Correo de prueba enviado a ' . $recipient]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function resend(): void
    {
        try {
            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('No tienes permisos para realizar esta acción.');
            }

            $appointment_id  = (int) request('appointment_id');
            $recipient_type  = request('recipient_type');
            $recipient_email = request('recipient_email');

            if (!$appointment_id || !$recipient_type || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Parámetros inválidos.');
            }

            $this->load->library('notifications');
            $this->notifications->resend_to($appointment_id, $recipient_type, $recipient_email);

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function log(): void
    {
        try {
            if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
                throw new RuntimeException('No tienes permisos para ver este recurso.');
            }

            $base = APPPATH;
            for ($i = 0; $i < 4; $i++) {
                $base = dirname($base);
                if (is_dir($base . '/storage/logs')) {
                    break;
                }
            }
            $log_path = $base . '/storage/logs/email_log.csv';

            if (!file_exists($log_path)) {
                json_response(['entries' => []]);
                return;
            }

            $lines = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $headers = array_shift($lines); // remove header row

            $entries = [];
            foreach (array_reverse($lines) as $line) {
                // Parse CSV line respecting quoted fields
                $row = str_getcsv($line);
                if (count($row) < 6) continue;
                $entries[] = [
                    'datetime'        => $row[0],
                    'appointment_id'  => $row[1],
                    'event'           => $row[2],
                    'recipient_type'  => $row[3],
                    'recipient_email' => $row[4],
                    'status'          => $row[5],
                    'detail'          => $row[6] ?? '',
                ];
                if (count($entries) >= 200) break;
            }

            json_response(['entries' => $entries]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
