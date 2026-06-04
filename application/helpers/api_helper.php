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

if (!function_exists('api_is_sensitive_field')) {
    /**
     * Whether a field/setting name is considered sensitive.
     *
     * Matches the centralized keyword list (case-insensitive substring match)
     * so that any field named like a credential (*_token, *_password,
     * *_secret, *_key, ...) is treated as sensitive by default.
     *
     * @param string $name Field or setting name.
     *
     * @return bool
     */
    function api_is_sensitive_field(string $name): bool
    {
        $name = strtolower($name);

        foreach (API_SENSITIVE_FIELD_KEYWORDS as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('api_setting_is_public')) {
    /**
     * Whether a setting may be exposed through the REST API.
     *
     * A setting is public only if it is explicitly allowlisted AND its name is
     * not flagged as sensitive (default-deny). Anything not listed is hidden.
     *
     * @param string $name Setting name.
     *
     * @return bool
     */
    function api_setting_is_public(string $name): bool
    {
        return in_array($name, API_SETTINGS_ALLOWLIST, true) && !api_is_sensitive_field($name);
    }
}
