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

if (!function_exists('setting')) {
    /**
     * Get / set the specified setting value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * Example "Get":
     *
     * $company_name = session('company_name', FALSE);
     *
     * Example "Set":
     *
     * setting(['company_name' => 'ACME Inc']);
     *
     * @param array|string|null $key Setting key.
     * @param mixed|null $default Default value in case the requested setting has no value.
     *
     * @return mixed|NULL Returns the requested value or NULL if you assign a new setting value.
     *
     * @throws InvalidArgumentException
     */
    function setting(array|string|null $key = null, mixed $default = null): mixed
    {
        /** @var EA_Controller $CI */
        $CI = &get_instance();

        $CI->load->model('settings_model');

        if (empty($key)) {
            throw new InvalidArgumentException('The $key argument cannot be empty.');
        }

        if (is_array($key)) {
            foreach ($key as $name => $value) {
                $setting = $CI->settings_model
                    ->query()
                    ->where('name', $name)
                    ->get()
                    ->row_array();

                if (empty($setting)) {
                    $setting = [
                        'name' => $name,
                    ];
                }

                $setting['value'] = $value;

                $CI->settings_model->save($setting);
            }

            return null;
        }

        $setting = $CI->settings_model
            ->query()
            ->where('name', $key)
            ->get()
            ->row_array();

        return $setting['value'] ?? $default;
    }
}

if (!function_exists('email_company_color')) {
    /**
     * Resolve the brand color to use in transactional email headers.
     *
     * Single source of truth for the email header background color. Reads the
     * `company_color` setting and returns it, falling back to
     * EMAIL_FALLBACK_COMPANY_COLOR (#000000) when the setting is empty/absent
     * or still holds the "unset" sentinel DEFAULT_COMPANY_COLOR (#ffffff).
     *
     * Always returns a real, visible color — never null and never the legacy
     * teal default (#429a82) — so every email path renders the same header.
     *
     * @return string Hex color string (e.g. "#000000").
     */
    function email_company_color(): string
    {
        $color = trim((string) setting('company_color'));

        if ($color === '' || strcasecmp($color, DEFAULT_COMPANY_COLOR) === 0) {
            return EMAIL_FALLBACK_COMPANY_COLOR;
        }

        return $color;
    }
}
