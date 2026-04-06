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

            json_response(['success' => true, 'message' => 'Test email sent to ' . $recipient]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
