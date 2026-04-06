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

        // Load current email settings (DB values override config file values)
        $email_settings = [
            ['name' => 'smtp_host',   'value' => setting('smtp_host')   ?: config('smtp_host')],
            ['name' => 'smtp_port',   'value' => setting('smtp_port')   ?: config('smtp_port')],
            ['name' => 'smtp_crypto', 'value' => setting('smtp_crypto') ?: config('smtp_crypto')],
            ['name' => 'smtp_user',   'value' => setting('smtp_user')   ?: config('smtp_user')],
            ['name' => 'smtp_pass',   'value' => setting('smtp_pass')   ?: config('smtp_pass')],
            ['name' => 'from_name',   'value' => setting('from_name')   ?: config('from_name')],
            ['name' => 'from_address','value' => setting('from_address') ?: config('from_address')],
            ['name' => 'reply_to',    'value' => setting('reply_to')    ?: config('reply_to')],
        ];

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
