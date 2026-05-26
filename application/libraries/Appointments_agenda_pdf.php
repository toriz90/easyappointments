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
 * Appointments agenda PDF library.
 *
 * Renders a printable list of appointments for the management view. The data and the
 * heading metadata (range, single-center label) are passed in by the caller — this
 * library only handles dompdf configuration, layout and pagination.
 *
 * @package Libraries
 */
class Appointments_agenda_pdf
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Generate the agenda PDF.
     *
     * @param array $rows  Hydrated appointment rows (the same shape produced by
     *                     `Appointments_management::hydrate_rows()`).
     * @param array $meta  {
     *     @type string|null $range_label   Human-readable date range ("21 al 27 de mayo de 2026").
     *     @type string|null $center_name   When the list is filtered to a single service, its
     *                                      name is shown in the heading and the "Centro de
     *                                      servicio" column is omitted from the table.
     *     @type string|null $status_label  Optional status filter being applied.
     *     @type string|null $search_label  Optional search context (e.g. "Búsqueda: 00002").
     * }
     *
     * @return string Raw PDF bytes.
     */
    public function generate(array $rows, array $meta = []): string
    {
        $view_data = [
            'rows' => $rows,
            'generated_at' => date('d/m/Y H:i'),
            'range_label' => $meta['range_label'] ?? null,
            'center_name' => $meta['center_name'] ?? null,
            'status_label' => $meta['status_label'] ?? null,
            'search_label' => $meta['search_label'] ?? null,
            'total' => count($rows),
        ];

        $html = $this->CI->load->view('pdf/appointments_agenda', $view_data, true);

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
}
