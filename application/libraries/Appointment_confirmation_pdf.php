<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Appointment confirmation PDF library.
 *
 * Generates the PDF confirmation document for a single appointment. The HTML template lives at
 * `application/views/pdf/appointment_confirmation.php`. dompdf is configured to use DejaVu Sans
 * for proper UTF-8 / Spanish character rendering (acentos, ñ, símbolo ✓).
 *
 * Phase 2 scope: only PDF generation. Email integration and download endpoints with access
 * control are handled in Phase 3.
 *
 * @package Libraries
 */
class Appointment_confirmation_pdf
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('appointments_model');
        $this->CI->load->model('services_model');
        $this->CI->load->model('customers_model');
    }

    /**
     * Generate the appointment confirmation PDF.
     *
     * @param int $appointment_id The `ea_appointments.id` to render.
     *
     * @return string Raw PDF bytes (suitable for echoing with `Content-Type: application/pdf`
     *                or attaching to an email).
     *
     * @throws InvalidArgumentException If the appointment, service or customer cannot be found.
     */
    public function generate(int $appointment_id): string
    {
        $appointment = $this->CI->appointments_model->find($appointment_id);

        $service = !empty($appointment['id_services'])
            ? $this->CI->services_model->find((int) $appointment['id_services'])
            : [];

        $customer = !empty($appointment['id_users_customer'])
            ? $this->CI->customers_model->find((int) $appointment['id_users_customer'])
            : [];

        $view_data = $this->build_view_data($appointment, $service, $customer);

        $html = $this->CI->load->view('pdf/appointment_confirmation', $view_data, true);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Build the view-model passed to the PDF template.
     *
     * @param array $appointment
     * @param array $service
     * @param array $customer
     *
     * @return array
     */
    protected function build_view_data(array $appointment, array $service, array $customer): array
    {
        $dash = '—';

        $start = !empty($appointment['start_datetime'])
            ? new DateTime($appointment['start_datetime'])
            : null;

        $first_name = trim((string) ($customer['first_name'] ?? ''));
        $last_name = trim((string) ($customer['last_name'] ?? ''));
        $full_name = trim($first_name . ' ' . $last_name);

        $custom_fields_raw = $appointment['custom_fields'] ?? null;

        $accessories_commitment_at = !empty($appointment['accessories_commitment_at'])
            ? new DateTime($appointment['accessories_commitment_at'])
            : null;

        return [
            'folio'           => $this->value_or_dash($appointment['folio'] ?? null, $dash),
            'sucursal'        => $this->value_or_dash($service['name'] ?? null, $dash),
            'fecha'           => $start ? $this->format_date_es($start) : $dash,
            'hora'            => $start ? $this->format_time_es($start) : $dash,
            'nombre_completo' => $this->value_or_dash($full_name, $dash),
            'telefono'        => $this->value_or_dash($customer['phone_number'] ?? null, $dash),
            'email'           => $this->value_or_dash($customer['email'] ?? null, $dash),
            'equipo'          => $this->value_or_dash(
                $this->CI->appointments_model->find_custom_field_value($custom_fields_raw, ['Modelo del equipo']),
                $dash,
            ),
            'motivo'          => $this->value_or_dash(
                $this->CI->appointments_model->find_custom_field_value(
                    $custom_fields_raw,
                    ['Motivo de servicio', 'Detalles'],
                ),
                $dash,
            ),
            'compromiso_accesorios' => $accessories_commitment_at
                ? 'Aceptado el ' . $this->format_date_es($accessories_commitment_at) . ', ' .
                    $this->format_time_es($accessories_commitment_at)
                : null,
        ];
    }

    /**
     * Format a datetime as a long Spanish date (e.g. "Jueves, 28 de mayo de 2026").
     *
     * Uses an internal name table so the output is independent of the server locale.
     */
    protected function format_date_es(DateTime $dt): string
    {
        $weekdays = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $months = [
            '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];

        $weekday = $weekdays[(int) $dt->format('w')];
        $day = (int) $dt->format('j');
        $month = $months[(int) $dt->format('n')];
        $year = $dt->format('Y');

        return sprintf('%s, %d de %s de %s', $weekday, $day, $month, $year);
    }

    /**
     * Format a datetime as a 12-hour Spanish time (e.g. "4:30 p.m.").
     */
    protected function format_time_es(DateTime $dt): string
    {
        $hour = (int) $dt->format('g');
        $minute = sprintf('%02d', (int) $dt->format('i'));
        $suffix = $dt->format('A') === 'AM' ? 'a.m.' : 'p.m.';

        return $hour . ':' . $minute . ' ' . $suffix;
    }

    /**
     * Return the trimmed string value or the dash placeholder when empty.
     */
    protected function value_or_dash(mixed $value, string $dash): string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? $dash : $string;
    }
}
