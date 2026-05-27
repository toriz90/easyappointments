<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.3.0
 * ---------------------------------------------------------------------------- */

/**
 * Assets URL helper function.
 *
 * This function will create an asset file URL that includes a cache busting parameter in order
 * to invalidate the browser cache in case of an update.
 *
 * @param string $uri Relative URI (just like the one used in the base_url helper).
 * @param string|null $protocol Valid URI protocol.
 *
 * @return string Returns the final asset URL.
 */
function asset_url(string $uri = '', ?string $protocol = null): string
{
    $debug = config('debug');

    $cache_busting_token = '?' . _ea_cache_busting_token();

    if (str_contains(basename($uri), '.js') && !str_contains(basename($uri), '.min.js') && !$debug) {
        $uri = str_replace('.js', '.min.js', $uri);
    }

    if (str_contains(basename($uri), '.css') && !str_contains(basename($uri), '.min.css') && !$debug) {
        $uri = str_replace('.css', '.min.css', $uri);
    }

    return base_url($uri . $cache_busting_token, $protocol);
}

/**
 * Resolve the cache-busting token used on every asset URL.
 *
 * Reads the current git commit SHA directly from `.git/` (no shell-out to the `git`
 * binary required — works inside the slim PHP-FPM container that ships only PHP and
 * Composer). Returns the 8-char short SHA so the URL stays short. When `.git/` is not
 * available (for example, on installs deployed from a tarball without history) the
 * value falls back to the legacy `cache_busting_token` constant from app config.
 *
 * The computed value is memoized in a static so it costs one disk read per request
 * regardless of how many assets the page references.
 *
 * @return string An identifier that changes whenever the codebase changes.
 */
function _ea_cache_busting_token(): string
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $fallback = (string) config('cache_busting_token');

    // The helper lives in application/helpers/, so the .git directory (when present)
    // is two levels above.
    $git_dir = realpath(__DIR__ . '/../../.git');

    if ($git_dir !== false && is_dir($git_dir)) {
        $sha = _ea_read_git_head_sha($git_dir);
        if ($sha !== null) {
            $cached = substr($sha, 0, 8);
            return $cached;
        }
    }

    $cached = $fallback !== '' ? $fallback : 'nogit';
    return $cached;
}

/**
 * Resolve the SHA pointed to by `.git/HEAD`. Returns null when the layout is not
 * recognized (we deliberately avoid throwing — the caller falls back to the config
 * token in that case).
 *
 * Handles:
 *  - Direct SHA (detached HEAD).
 *  - Symbolic ref ("ref: refs/heads/<branch>") with the loose ref file present.
 *  - Symbolic ref resolved through `packed-refs` when the loose file is missing
 *    (this happens after `git gc` / on fresh clones, exactly the situation a
 *    production deploy is likely to be in).
 */
function _ea_read_git_head_sha(string $git_dir): ?string
{
    $head_path = $git_dir . '/HEAD';
    if (!is_readable($head_path)) {
        return null;
    }

    $head = trim((string) @file_get_contents($head_path));

    // Detached HEAD: the file contains a raw SHA.
    if (preg_match('/^[0-9a-f]{40}$/i', $head) === 1) {
        return $head;
    }

    // Symbolic ref: "ref: refs/heads/<branch>"
    if (preg_match('/^ref:\s*(\S+)$/', $head, $matches) !== 1) {
        return null;
    }

    $ref = $matches[1];

    // Try the loose ref file first (the common case for active branches).
    $loose_ref = $git_dir . '/' . $ref;
    if (is_readable($loose_ref)) {
        $sha = trim((string) @file_get_contents($loose_ref));
        if (preg_match('/^[0-9a-f]{40}$/i', $sha) === 1) {
            return $sha;
        }
    }

    // Fall back to packed-refs (fresh clones, post-gc repos).
    $packed_path = $git_dir . '/packed-refs';
    if (!is_readable($packed_path)) {
        return null;
    }

    $packed = @file($packed_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($packed === false) {
        return null;
    }

    foreach ($packed as $line) {
        $line = ltrim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === '^') {
            continue;
        }
        $parts = preg_split('/\s+/', $line, 2);
        if (!is_array($parts) || count($parts) !== 2) {
            continue;
        }
        [$sha, $name] = $parts;
        if ($name === $ref && preg_match('/^[0-9a-f]{40}$/i', $sha) === 1) {
            return $sha;
        }
    }

    return null;
}
