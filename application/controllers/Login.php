<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Login controller.
 *
 * Handles the login page functionality.
 *
 * @package Controllers
 */
class Login extends EA_Controller
{
    /**
     * Login constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('accounts');
        $this->load->library('ldap_client');
        $this->load->library('email_messages');

        script_vars([
            'dest_url' => session('dest_url', site_url('calendar')),
        ]);
    }

    /**
     * Render the login page.
     */
    public function index(): void
    {
        if (session('user_id')) {
            redirect('calendar');
            return;
        }

        html_vars([
            'page_title' => lang('login'),
            'base_url' => config('base_url'),
            'dest_url' => session('dest_url', site_url('calendar')),
            'company_name' => setting('company_name'),
        ]);

        $this->load->view('pages/login');
    }

    /**
     * Validate the provided credentials and start a new session if the validation was successful.
     */
    public function validate(): void
    {
        try {
            $username = request('username');

            if (empty($username)) {
                throw new InvalidArgumentException('No username value provided.');
            }

            $password = request('password');

            if (empty($password)) {
                throw new InvalidArgumentException('No password value provided.');
            }

            $user_data = $this->accounts->check_login($username, $password);

            if (empty($user_data)) {
                $user_data = $this->ldap_client->check_login($username, $password);
            }

            if (empty($user_data)) {
                throw new InvalidArgumentException(lang('invalid_credentials_provided'));
            }

            $this->session->sess_regenerate();

            session($user_data); // Save data in the session.

            // Post-login destination. A `dest_url` was stored in the session when an
            // unauthenticated user was redirected away from a protected page; honor it
            // so deep-links survive the login round-trip. When the only `dest_url` is
            // the generic fallback set in the constructor (site_url('calendar')), pick
            // a sensible default per role: users with PRIV_APPOINTMENTS land on the
            // management list (the primary daily workflow), everyone else keeps the
            // calendar fallback.
            $default_url = site_url('calendar');
            $session_url = session('dest_url');

            if (!empty($session_url) && $session_url !== $default_url) {
                $dest_url = $session_url;
            } elseif (can('view', PRIV_APPOINTMENTS)) {
                $dest_url = site_url('appointments_management');
            } else {
                $dest_url = $default_url;
            }

            json_response([
                'success' => true,
                'dest_url' => $dest_url,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
