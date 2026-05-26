<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Appointments Management controller.
 *
 * Backend list view that complements the calendar with a tabular, filterable view of
 * real appointments (unavailability blocks are excluded). Fase 1 covers the base
 * structure: list, paging, ordering by datetime and filters (date range / service /
 * status). Search, per-row actions, export and counters come in later phases.
 *
 * @package Controllers
 */
class Appointments_management extends EA_Controller
{
    /**
     * Status values currently in use (kept in sync with the
     * `appointment_status_options` setting, see migration 066).
     */
    private const STATUSES = ['Confirmada', 'Atendida', 'No se presentó', 'Cancelada'];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('services_model');
        $this->load->model('customers_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
    }

    /**
     * Render the management list page.
     */
    public function index(): void
    {
        session(['dest_url' => site_url('appointments_management')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');
            return;
        }

        $role_slug = session('role_slug');

        // Service options for the filter dropdown.
        $services = $this->services_model->get(null, null, null, 'name ASC');
        $service_options = array_map(static function (array $service): array {
            return [
                'id' => (int) $service['id'],
                'name' => $service['name'],
            ];
        }, $services);

        // Status options come from the configurable setting so they stay aligned with the
        // appointment modal; fall back to the canonical Spanish set if the setting is empty.
        $configured_statuses = json_decode(setting('appointment_status_options', '[]'), true);
        $status_options = !empty($configured_statuses) && is_array($configured_statuses)
            ? $configured_statuses
            : self::STATUSES;

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'service_options' => $service_options,
            'status_options' => $status_options,
        ]);

        html_vars([
            'page_title' => lang('appointments_management'),
            'active_menu' => 'appointments_management',
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
            'service_options' => $service_options,
            'status_options' => $status_options,
        ]);

        $this->load->view('pages/appointments_management');
    }

    /**
     * AJAX endpoint: return a paginated, filtered slice of real appointments.
     *
     * Accepted request params:
     *  - q                      Optional search term (>= 2 chars). Matches against folio,
     *                           customer first/last name, phone and email. When present,
     *                           the date range filter is ignored (a search is meant to find
     *                           a specific appointment regardless of when it happens).
     *  - id_users_customer      Optional customer id. Filters to that customer's appointments
     *                           and, like `q`, overrides the date range (used by the per-row
     *                           "Customer history" action).
     *  - start_date / end_date  Date range (Y-m-d). Ignored when `q` or
     *                           `id_users_customer` is present.
     *  - id_services            Optional service id to filter by.
     *  - status                 Optional status string to filter by.
     *  - page                   1-based page number (default 1).
     *  - per_page               Page size (default 25, capped at 100).
     *  - order                  'asc' or 'desc' on start_datetime (default 'asc').
     */
    public function search(): void
    {
        try {
            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            $filters = $this->build_filters_from_request();
            $page = max(1, (int) request('page', 1));
            $per_page = min(100, max(1, (int) request('per_page', 25)));
            $order = strtolower((string) request('order', 'asc')) === 'desc' ? 'DESC' : 'ASC';

            $total = (int) $this->apply_filters($filters)->count_all_results();

            $rows = $this->apply_filters($filters)
                ->order_by('appointments.start_datetime', $order)
                ->limit($per_page, ($page - 1) * $per_page)
                ->get()
                ->result_array();

            $data = $this->hydrate_rows($rows);

            // When the customer filter is active, return the customer's name so the UI can
            // show "Historial de: <name>" without making a second roundtrip.
            $customer_filter_name = null;
            if ($filters['has_customer_filter']) {
                try {
                    $customer = $this->customers_model->find($filters['customer_filter_id']);
                    $customer_filter_name = trim(
                        ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')
                    );
                } catch (Throwable $e) {
                    // ignore — UI will fall back to id.
                }
            }

            json_response([
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'order' => strtolower($order),
                'customer_filter_id' => $filters['has_customer_filter'] ? $filters['customer_filter_id'] : null,
                'customer_filter_name' => $customer_filter_name,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Read filter parameters from the current request and normalize them into a single
     * associative array. Centralizing this lets `search()`, `export_pdf()` and
     * `export_csv()` share the exact same filter semantics.
     */
    private function build_filters_from_request(): array
    {
        $search_term = trim((string) request('q', ''));
        $customer_filter_id = (int) request('id_users_customer', 0);

        return [
            'search_term' => $search_term,
            'has_search' => mb_strlen($search_term) >= 2,
            'customer_filter_id' => $customer_filter_id,
            'has_customer_filter' => $customer_filter_id > 0,
            'start_date' => request('start_date'),
            'end_date' => request('end_date'),
            'id_services' => request('id_services'),
            'status' => request('status'),
        ];
    }

    /**
     * Apply the filter set to a fresh query builder. Returns the builder ready to receive
     * `count_all_results()`, `get()`, `order_by()` etc. Each call constructs a NEW builder
     * (CodeIgniter 3's QB is a singleton on the DB driver, so we never reuse one across
     * passes).
     */
    private function apply_filters(array $filters): CI_DB_query_builder
    {
        $b = $this->appointments_model->query()->where('appointments.is_unavailability', 0);

        if ($filters['has_search']) {
            // Search overrides the date range. INNER JOIN on users so we can match against
            // customer fields too. SELECT appointments.* so the user table's `id` and other
            // columns do not clobber the appointment fields.
            $b->select('appointments.*')
                ->join('users', 'users.id = appointments.id_users_customer', 'inner')
                ->group_start()
                    ->like('appointments.folio', $filters['search_term'], 'both')
                    ->or_like('users.first_name', $filters['search_term'], 'both')
                    ->or_like('users.last_name', $filters['search_term'], 'both')
                    ->or_like('users.phone_number', $filters['search_term'], 'both')
                    ->or_like('users.email', $filters['search_term'], 'both')
                ->group_end();
        } elseif ($filters['has_customer_filter']) {
            $b->where('appointments.id_users_customer', $filters['customer_filter_id']);
        } else {
            if (!empty($filters['start_date'])) {
                $b->where('appointments.start_datetime >=', $filters['start_date'] . ' 00:00:00');
            }
            if (!empty($filters['end_date'])) {
                $b->where('appointments.start_datetime <=', $filters['end_date'] . ' 23:59:59');
            }
        }

        if (!empty($filters['id_services'])) {
            $b->where('appointments.id_services', (int) $filters['id_services']);
        }
        if (!empty($filters['status'])) {
            $b->where('appointments.status', $filters['status']);
        }

        return $b;
    }

    /**
     * Enrich raw appointment rows with customer name/phone, service name and the
     * "Modelo del equipo" custom field value. Lookups are batched by id_set so repeated
     * services/customers in the same page cost a single query each.
     *
     * @param array[] $rows
     * @return array[]
     */
    private function hydrate_rows(array $rows): array
    {
        $service_ids = array_unique(array_filter(array_map(static fn($r) => (int) $r['id_services'], $rows)));
        $customer_ids = array_unique(array_filter(array_map(static fn($r) => (int) $r['id_users_customer'], $rows)));

        $services_by_id = [];
        foreach ($service_ids as $sid) {
            try {
                $services_by_id[$sid] = $this->services_model->find($sid);
            } catch (Throwable $e) {
                // Service might have been deleted; skip the lookup.
            }
        }

        $customers_by_id = [];
        foreach ($customer_ids as $cid) {
            try {
                $customers_by_id[$cid] = $this->customers_model->find($cid);
            } catch (Throwable $e) {
                // Same as above.
            }
        }

        $data = [];
        foreach ($rows as $row) {
            $service = $services_by_id[(int) $row['id_services']] ?? null;
            $customer = $customers_by_id[(int) $row['id_users_customer']] ?? null;

            $first_name = trim((string) ($customer['first_name'] ?? ''));
            $last_name = trim((string) ($customer['last_name'] ?? ''));
            $full_name = trim($first_name . ' ' . $last_name);

            $data[] = [
                'id' => (int) $row['id'],
                'hash' => $row['hash'] ?? '',
                'folio' => $row['folio'] ?? null,
                'start_datetime' => $row['start_datetime'],
                'end_datetime' => $row['end_datetime'],
                'customer_id' => (int) ($row['id_users_customer'] ?? 0),
                'customer_name' => $full_name,
                'customer_phone' => $customer['phone_number'] ?? '',
                'service_name' => $service['name'] ?? '',
                'equipment' => $this->extract_equipment_label($row['custom_fields'] ?? null),
                'status' => $row['status'] ?? '',
            ];
        }

        return $data;
    }

    /**
     * AJAX endpoint: change the status of an appointment.
     *
     * Requires `edit PRIV_APPOINTMENTS`. Validates the new status against the configured
     * options. Responds with `{ success: true, status: '<new value>' }` so the frontend can
     * refresh the badge in place without a full reload.
     */
    public function update_status(): void
    {
        try {
            if (cannot('edit', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            $appointment_id = (int) request('appointment_id', 0);
            $new_status = trim((string) request('status', ''));

            if ($appointment_id <= 0 || $new_status === '') {
                throw new InvalidArgumentException('Faltan datos para actualizar el estado.');
            }

            $configured = json_decode(setting('appointment_status_options', '[]'), true);
            $allowed = !empty($configured) && is_array($configured) ? $configured : self::STATUSES;

            if (!in_array($new_status, $allowed, true)) {
                throw new InvalidArgumentException('Estado no permitido: ' . $new_status);
            }

            // Use find() to make sure the appointment exists and is not an unavailability.
            $appointment = $this->appointments_model->find($appointment_id);
            if (!empty($appointment['is_unavailability'])) {
                throw new InvalidArgumentException('No se puede cambiar el estado de un bloqueo de agenda.');
            }

            $appointment['status'] = $new_status;
            $this->appointments_model->save($appointment);

            json_response(['success' => true, 'status' => $new_status]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * AJAX endpoint: resend the confirmation email (with the PDF attached) to the customer.
     *
     * Synchronous on purpose so the admin gets explicit success/failure feedback. The
     * `customer_notifications` system setting is intentionally bypassed: this is a manual
     * admin action, not an automatic notification.
     */
    public function resend_confirmation(): void
    {
        try {
            if (cannot('edit', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            $appointment_id = (int) request('appointment_id', 0);
            if ($appointment_id <= 0) {
                throw new InvalidArgumentException('Falta el id de la cita.');
            }

            $appointment = $this->appointments_model->find($appointment_id);

            if (!empty($appointment['is_unavailability'])) {
                throw new InvalidArgumentException('No se puede reenviar correo para un bloqueo de agenda.');
            }

            $customer = $this->customers_model->find((int) $appointment['id_users_customer']);
            if (empty($customer['email'])) {
                throw new RuntimeException('El cliente no tiene correo registrado.');
            }

            $this->load->model('providers_model');
            $this->load->model('services_model');
            $this->load->library('email_messages');
            $this->load->library('ics_file');

            $provider = $this->providers_model->find((int) $appointment['id_users_provider']);
            $service = $this->services_model->find((int) $appointment['id_services']);

            $company_color = setting('company_color');
            $settings = [
                'company_name' => setting('company_name'),
                'company_link' => setting('company_link'),
                'company_email' => setting('company_email'),
                'company_color' =>
                    !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                'date_format' => setting('date_format'),
                'time_format' => setting('time_format'),
            ];

            $ics_stream = $this->ics_file->get_stream($appointment, $service, $provider, $customer);

            $current_language = config('language');
            config(['language' => $customer['language'] ?? $current_language]);
            $this->lang->load('translations');

            $this->email_messages->send_appointment_saved(
                $appointment,
                $provider,
                $service,
                $customer,
                $settings,
                lang('appointment_booked'),
                lang('thank_you_for_appointment'),
                site_url('booking/reschedule/' . $appointment['hash']),
                $customer['email'],
                $ics_stream,
                $customer['timezone'] ?? null,
            );

            config(['language' => $current_language]);
            $this->lang->load('translations');

            json_response([
                'success' => true,
                'email' => $customer['email'],
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Resend confirmation failed for appointment ' . (request('appointment_id') ?: '?')
                . ': ' . $e->getMessage()
            );
            json_exception($e);
        }
    }

    /**
     * Export the filtered appointments as a printable PDF (the "Imprimir agenda" button).
     *
     * Same filter semantics as `search()` and `export_csv()`. The `scope` parameter
     * controls whether the export covers the entire filtered set (`scope=all`, default)
     * or only the page the user is currently viewing (`scope=page`, which also requires
     * `page`, `per_page` and `order` to match what the table shows).
     */
    public function export_pdf(): void
    {
        if (cannot('view', PRIV_APPOINTMENTS)) {
            abort(403, 'Forbidden');
            return;
        }

        $this->load->library('appointments_agenda_pdf');

        $filters = $this->build_filters_from_request();
        $export = $this->resolve_export_scope();
        $rows = $this->fetch_export_rows($filters, $export);
        $data = $this->hydrate_rows($rows);

        $meta = $this->build_export_meta($filters);
        if ($export['scope'] === 'page') {
            $meta['scope_label'] = 'Página ' . $export['page'];
        }

        try {
            $pdf_bytes = $this->appointments_agenda_pdf->generate($data, $meta);
        } catch (Throwable $e) {
            log_message('error', 'Agenda PDF generation failed: ' . $e->getMessage());
            show_error('No se pudo generar el PDF de la agenda.', 500);
            return;
        }

        $filename = 'agenda-' . date('Ymd-His') . '.pdf';
        $disposition = request('download') ? 'attachment' : 'inline';

        $this->output
            ->set_content_type('application/pdf')
            ->set_header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"')
            ->set_header('Cache-Control: private, max-age=0, must-revalidate')
            ->set_output($pdf_bytes);
    }

    /**
     * Read the export scope params from the current request. Returns a normalized array
     * with `scope` (always 'all' or 'page'), plus `page`, `per_page` and `order` when
     * relevant.
     */
    private function resolve_export_scope(): array
    {
        $scope = request('scope', 'all') === 'page' ? 'page' : 'all';

        return [
            'scope' => $scope,
            'page' => max(1, (int) request('page', 1)),
            'per_page' => min(100, max(1, (int) request('per_page', 25))),
            'order' => strtolower((string) request('order', 'asc')) === 'desc' ? 'DESC' : 'ASC',
        ];
    }

    /**
     * Fetch the appointment rows to export. With `scope=all` the entire filtered set is
     * returned (ascending by start_datetime — what makes sense for a printable agenda).
     * With `scope=page` the result mirrors exactly what the user has on screen: same
     * sort order, same page slice.
     */
    private function fetch_export_rows(array $filters, array $export): array
    {
        $builder = $this->apply_filters($filters);

        if ($export['scope'] === 'page') {
            return $builder
                ->order_by('appointments.start_datetime', $export['order'])
                ->limit($export['per_page'], ($export['page'] - 1) * $export['per_page'])
                ->get()
                ->result_array();
        }

        return $builder
            ->order_by('appointments.start_datetime', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Build the heading metadata used by the PDF export.
     */
    private function build_export_meta(array $filters): array
    {
        $meta = [
            'range_label' => null,
            'center_name' => null,
            'status_label' => $filters['status'] ?: null,
            'search_label' => $filters['has_search'] ? $filters['search_term'] : null,
        ];

        // Range label only applies when neither search nor customer-history overrides it.
        if (!$filters['has_search'] && !$filters['has_customer_filter']) {
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $meta['range_label'] = $this->humanize_date_range(
                    $filters['start_date'],
                    $filters['end_date']
                );
            }
        } elseif ($filters['has_customer_filter']) {
            try {
                $customer = $this->customers_model->find($filters['customer_filter_id']);
                $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                $meta['range_label'] = 'Historial del cliente: ' . ($name !== '' ? $name : '#' . $filters['customer_filter_id']);
            } catch (Throwable $e) {
                $meta['range_label'] = 'Historial del cliente';
            }
        }

        if (!empty($filters['id_services'])) {
            try {
                $service = $this->services_model->find((int) $filters['id_services']);
                $meta['center_name'] = $service['name'] ?? null;
            } catch (Throwable $e) {
                // fall through — column stays visible.
            }
        }

        return $meta;
    }

    /**
     * Format a date range as a human-readable Spanish label, locale-independent.
     */
    private function humanize_date_range(string $start, string $end): string
    {
        $months = [
            '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];

        try {
            $a = new DateTime($start);
            $b = new DateTime($end);
        } catch (Throwable $e) {
            return $start . ' — ' . $end;
        }

        $same_day = $a->format('Y-m-d') === $b->format('Y-m-d');
        $same_month = $a->format('Y-m') === $b->format('Y-m');
        $same_year = $a->format('Y') === $b->format('Y');

        if ($same_day) {
            return (int) $a->format('j') . ' de ' . $months[(int) $a->format('n')] . ' de ' . $a->format('Y');
        }
        if ($same_month) {
            return (int) $a->format('j') . ' al ' . (int) $b->format('j')
                . ' de ' . $months[(int) $a->format('n')] . ' de ' . $a->format('Y');
        }
        if ($same_year) {
            return (int) $a->format('j') . ' de ' . $months[(int) $a->format('n')]
                . ' al ' . (int) $b->format('j') . ' de ' . $months[(int) $b->format('n')]
                . ' de ' . $a->format('Y');
        }
        return (int) $a->format('j') . ' de ' . $months[(int) $a->format('n')] . ' de ' . $a->format('Y')
            . ' al ' . (int) $b->format('j') . ' de ' . $months[(int) $b->format('n')] . ' de ' . $b->format('Y');
    }

    /**
     * Export the filtered appointments as a CSV file.
     *
     * Respects the same filter parameters as `search()` and supports `scope=all` (default)
     * or `scope=page` for "only the page currently on screen". UTF-8 BOM is written first
     * so Excel auto-detects the encoding.
     */
    public function export_csv(): void
    {
        if (cannot('view', PRIV_APPOINTMENTS)) {
            abort(403, 'Forbidden');
            return;
        }

        $filters = $this->build_filters_from_request();
        $export = $this->resolve_export_scope();
        $rows = $this->fetch_export_rows($filters, $export);
        $data = $this->hydrate_rows($rows);

        $filename = 'agenda-' . date('Ymd-His') . '.csv';

        $this->output
            ->set_content_type('text/csv; charset=utf-8')
            ->set_header('Content-Disposition: attachment; filename="' . $filename . '"')
            ->set_header('Cache-Control: private, max-age=0, must-revalidate');

        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM so Excel auto-detects the encoding and shows accents correctly.
        fwrite($handle, "\xEF\xBB\xBF");

        // Pass an explicit empty `$escape` argument: from PHP 8.4 onwards the legacy
        // backslash escape behavior is removed, and PHP 8.1-8.3 emit a deprecation when
        // it is left at the default. An empty string asks for plain RFC 4180 CSV.
        fputcsv($handle, [
            'Folio',
            'Fecha y hora',
            'Cliente',
            'Teléfono',
            'Centro de servicio',
            'Equipo',
            'Estado',
        ], ',', '"', '');

        foreach ($data as $row) {
            $datetime = $row['start_datetime']
                ? date('Y-m-d H:i', strtotime($row['start_datetime']))
                : '';
            fputcsv($handle, [
                $row['folio'] ?? '',
                $datetime,
                $row['customer_name'],
                $row['customer_phone'],
                $row['service_name'],
                $row['equipment'],
                $row['status'],
            ], ',', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->output->set_output($csv);
    }

    /**
     * Pull the value of the "Modelo del equipo" custom field from the per-appointment
     * JSON snapshot. Returns an empty string if the field is missing.
     */
    private function extract_equipment_label(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }

        $decoded = $this->appointments_model->decode_custom_fields($raw);
        $target = mb_strtolower('Modelo del equipo');

        foreach ((array) $decoded as $label => $value) {
            if (mb_strtolower(trim((string) $label)) === $target) {
                return (string) $value;
            }
        }

        return '';
    }
}
