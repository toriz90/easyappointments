/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 *
 * Appointments Management — list page (Fase 1).
 *
 * Loads appointments via /appointments_management/list with the current filter set,
 * renders them into the table, and manages pagination + sort by start_datetime.
 * Defaults to "today" on first load.
 * ---------------------------------------------------------------------------- */

App.Pages = App.Pages || {};

App.Pages.AppointmentsManagement = (function () {
    const $startDate = $('#filter-start-date');
    const $endDate = $('#filter-end-date');
    const $service = $('#filter-service');
    const $status = $('#filter-status');
    const $apply = $('#apply-filters');
    const $perPage = $('#per-page');
    const $prev = $('#page-prev');
    const $next = $('#page-next');
    const $pageIndicator = $('#page-indicator');
    const $info = $('#pagination-info');
    const $tbody = $('#appointments-table tbody');
    const $sortHeader = $('#appointments-table thead th.sortable');
    const $search = $('#filter-search');
    const $clearSearch = $('#clear-search');
    const $searchHint = $('#search-hint');
    const $historyBadge = $('#customer-history-badge');
    const $historyName = $('#customer-history-name');
    const $clearHistory = $('#clear-customer-history');
    const $toastContainer = $('#appointments-management-toasts');

    const SEARCH_DEBOUNCE_MS = 350;
    const SEARCH_MIN_LENGTH = 2;
    const STATUS_LIST = ['Confirmada', 'Atendida', 'No se presentó', 'Cancelada'];

    let currentPage = 1;
    let currentOrder = 'asc';
    let totalRows = 0;
    let searchDebounce = null;
    let lastDispatchedSearch = '';
    let activeCustomerId = null; // null = ningún historial activo
    let startFp = null;
    let endFp = null;
    let programmaticDateChange = false;

    function formatDateInput(date) {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function presetRange(preset) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let start = new Date(today);
        let end = new Date(today);

        if (preset === 'tomorrow') {
            start.setDate(today.getDate() + 1);
            end = new Date(start);
        } else if (preset === 'this_week') {
            // Lunes a domingo de la semana actual.
            const day = today.getDay(); // 0 = domingo, 1 = lunes ...
            const offsetToMonday = day === 0 ? -6 : 1 - day;
            start.setDate(today.getDate() + offsetToMonday);
            end = new Date(start);
            end.setDate(start.getDate() + 6);
        }

        // Set programmatically: keep the flatpickr instance in sync but suppress the
        // `onChange` handler that would otherwise clear the active preset highlight.
        programmaticDateChange = true;
        if (startFp) {
            startFp.setDate(start, false);
        } else {
            $startDate.val(formatDateInput(start));
        }
        if (endFp) {
            endFp.setDate(end, false);
        } else {
            $endDate.val(formatDateInput(end));
        }
        programmaticDateChange = false;

        setActivePreset(preset);
    }

    function setActivePreset(preset) {
        $('.preset-range').removeClass('active');
        if (preset) {
            $(`.preset-range[data-preset="${preset}"]`).addClass('active');
        }
    }

    function renderRows(rows) {
        if (!rows.length) {
            $tbody.html(
                '<tr><td colspan="8" class="text-center text-muted py-4">' +
                'No hay citas que coincidan con los filtros.' +
                '</td></tr>',
            );
            return;
        }

        const html = rows
            .map((row) => {
                const dt = row.start_datetime ? moment(row.start_datetime) : null;
                const dateCell = dt
                    ? `${dt.format('DD/MM/YYYY')} <span class="text-muted">·</span> ${dt.format('HH:mm')}`
                    : '<span class="text-muted">—</span>';

                const folio = row.folio
                    ? `<code class="text-dark">${escapeHtml(row.folio)}</code>`
                    : '<span class="text-muted">—</span>';

                return (
                    `<tr data-appointment-id="${row.id}">` +
                    `<td data-label="Folio">${folio}</td>` +
                    `<td data-label="Fecha y hora">${dateCell}</td>` +
                    `<td data-label="Cliente">${escapeHtml(row.customer_name) || '<span class="text-muted">—</span>'}</td>` +
                    `<td data-label="Teléfono">${escapeHtml(row.customer_phone) || '<span class="text-muted">—</span>'}</td>` +
                    `<td data-label="Centro">${escapeHtml(row.service_name) || '<span class="text-muted">—</span>'}</td>` +
                    `<td data-label="Equipo">${escapeHtml(row.equipment) || '<span class="text-muted">—</span>'}</td>` +
                    `<td data-label="Estado" class="status-cell">${renderStatusBadge(row.status)}</td>` +
                    `<td data-label="Acciones" class="actions-cell text-end">${renderActionsMenu(row)}</td>` +
                    '</tr>'
                );
            })
            .join('');

        $tbody.html(html);
    }

    function renderActionsMenu(row) {
        const phoneDigits = (row.customer_phone || '').replace(/\D+/g, '');
        const hasPhone = phoneDigits.length > 0;
        const hasHash = Boolean(row.hash);
        const hasFolio = Boolean(row.folio);
        const hasCustomer = (row.customer_id || 0) > 0;

        // Encode payload for the row-level event delegation in bindRowActions().
        const rowJson = escapeHtml(JSON.stringify(row));

        // Status submenu items.
        const statusItems = STATUS_LIST.map((s) => {
            const isCurrent = s === row.status;
            const check = isCurrent
                ? '<i class="fas fa-check text-success me-2"></i>'
                : '<span class="d-inline-block me-2" style="width: 1em;"></span>';
            return (
                `<li><a class="dropdown-item action-set-status" href="#" data-status="${escapeHtml(s)}">` +
                `${check}${escapeHtml(s)}</a></li>`
            );
        }).join('');

        const pdfLink = App.Utils.Url.siteUrl('confirmation_pdf/admin/' + row.id);
        const calendarLink = hasHash
            ? App.Utils.Url.siteUrl('calendar/reschedule/' + encodeURIComponent(row.hash))
            : '#';

        const waLink = hasPhone ? `https://wa.me/52${phoneDigits}` : '#';
        const telLink = hasPhone ? `tel:${phoneDigits}` : '#';

        return (
            '<div class="dropdown" data-row=\'' + rowJson + '\'>' +
                '<button class="btn btn-sm btn-outline-secondary" type="button" ' +
                    'data-bs-toggle="dropdown" aria-expanded="false" data-bs-boundary="viewport">' +
                    '<i class="fas fa-cog"></i>' +
                '</button>' +
                '<ul class="dropdown-menu dropdown-menu-end">' +
                    `<li><a class="dropdown-item" href="${pdfLink}" target="_blank" rel="noopener">` +
                        '<i class="fas fa-file-pdf me-2 text-danger"></i>Descargar PDF</a></li>' +

                    '<li><a class="dropdown-item action-resend" href="#">' +
                        '<i class="fas fa-paper-plane me-2 text-primary"></i>Reenviar correo de confirmación</a></li>' +

                    (hasHash
                        ? `<li><a class="dropdown-item" href="${calendarLink}">` +
                          '<i class="fas fa-edit me-2"></i>Ver / Editar la cita</a></li>'
                        : '<li><span class="dropdown-item disabled">' +
                          '<i class="fas fa-edit me-2"></i>Ver / Editar la cita</span></li>') +

                    (hasFolio
                        ? '<li><a class="dropdown-item action-copy-folio" href="#">' +
                          '<i class="fas fa-copy me-2"></i>Copiar folio</a></li>'
                        : '<li><span class="dropdown-item disabled">' +
                          '<i class="fas fa-copy me-2"></i>Copiar folio</span></li>') +

                    (hasCustomer
                        ? '<li><a class="dropdown-item action-history" href="#">' +
                          '<i class="fas fa-history me-2"></i>Historial del cliente</a></li>'
                        : '<li><span class="dropdown-item disabled">' +
                          '<i class="fas fa-history me-2"></i>Historial del cliente</span></li>') +

                    '<li><hr class="dropdown-divider"></li>' +
                    '<li><h6 class="dropdown-header">Contactar al cliente</h6></li>' +
                    (hasPhone
                        ? `<li><a class="dropdown-item" href="${waLink}" target="_blank" rel="noopener">` +
                          '<i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp</a></li>' +
                          `<li><a class="dropdown-item" href="${telLink}">` +
                          '<i class="fas fa-phone me-2"></i>Llamar</a></li>'
                        : '<li><span class="dropdown-item disabled">' +
                          '<i class="fab fa-whatsapp me-2"></i>WhatsApp</span></li>' +
                          '<li><span class="dropdown-item disabled">' +
                          '<i class="fas fa-phone me-2"></i>Llamar</span></li>') +

                    '<li><hr class="dropdown-divider"></li>' +
                    '<li><h6 class="dropdown-header">Cambiar estado</h6></li>' +
                    statusItems +
                '</ul>' +
            '</div>'
        );
    }

    function renderStatusBadge(status) {
        if (!status) {
            return '<span class="text-muted">—</span>';
        }
        const palette = {
            'Confirmada': 'bg-primary',
            'Atendida': 'bg-success',
            'No se presentó': 'bg-warning text-dark',
            'Cancelada': 'bg-secondary',
        };
        const cssClass = palette[status] || 'bg-light text-dark';
        return `<span class="badge ${cssClass}">${escapeHtml(status)}</span>`;
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function updatePagination() {
        const perPage = parseInt($perPage.val(), 10) || 25;
        const totalPages = Math.max(1, Math.ceil(totalRows / perPage));

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        $pageIndicator.text(`Página ${currentPage} de ${totalPages}`);
        $prev.prop('disabled', currentPage <= 1);
        $next.prop('disabled', currentPage >= totalPages);

        if (totalRows === 0) {
            $info.text('Sin resultados.');
            return;
        }

        const from = (currentPage - 1) * perPage + 1;
        const to = Math.min(currentPage * perPage, totalRows);
        $info.text(`Mostrando ${from}–${to} de ${totalRows}`);
    }

    function updateSortIcon() {
        const $icon = $sortHeader.find('i');
        $icon
            .removeClass('fa-sort fa-sort-up fa-sort-down')
            .addClass(currentOrder === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
    }

    function currentSearchTerm() {
        const raw = ($search.val() || '').trim();
        return raw.length >= SEARCH_MIN_LENGTH ? raw : '';
    }

    function showToast(message, variant) {
        const v = variant || 'primary';
        const id = 'toast-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
        const html =
            `<div id="${id}" class="toast align-items-center text-bg-${v} border-0" role="alert" aria-live="assertive" aria-atomic="true">` +
                '<div class="d-flex">' +
                    `<div class="toast-body">${escapeHtml(message)}</div>` +
                    '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>' +
                '</div>' +
            '</div>';

        const $el = $(html).appendTo($toastContainer);
        const node = $el.get(0);

        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const toast = new bootstrap.Toast(node, { delay: 3500 });
            toast.show();
            node.addEventListener('hidden.bs.toast', () => $el.remove());
        } else {
            $el.show();
            setTimeout(() => $el.remove(), 3500);
        }
    }

    /**
     * Re-create the Bootstrap dropdown instances for the per-row gear menu with
     * `popperConfig.strategy = 'fixed'`. By default Bootstrap positions the menu with
     * absolute coordinates inside the trigger's containing block, which means the menu
     * gets clipped by the `overflow-x: auto` of `.table-responsive` when the table is
     * short (one or two rows). A fixed-strategy popper renders against the viewport, so
     * the menu can extend past the table boundaries without scroll or truncation.
     */
    function initializeRowDropdowns() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
        $tbody.find('[data-bs-toggle="dropdown"]').each(function () {
            const existing = bootstrap.Dropdown.getInstance(this);
            if (existing) {
                existing.dispose();
            }
            new bootstrap.Dropdown(this, {
                popperConfig: (defaults) => Object.assign({}, defaults, { strategy: 'fixed' }),
            });
        });
    }

    function getRowDataFromTrigger(triggerEl) {
        const $dropdown = $(triggerEl).closest('.dropdown');
        try {
            return JSON.parse($dropdown.attr('data-row') || '{}');
        } catch (e) {
            return {};
        }
    }

    function updateHistoryBadge(name) {
        if (activeCustomerId) {
            $historyName.text(name || ('#' + activeCustomerId));
            $historyBadge.removeClass('d-none');
        } else {
            $historyBadge.addClass('d-none');
            $historyName.text('');
        }
    }

    function setHistoryCustomer(customerId, customerName) {
        activeCustomerId = parseInt(customerId, 10) || null;
        if (activeCustomerId) {
            // Search and customer-history are mutually exclusive on the server; clear the
            // search box to avoid a confusing UI where both look active.
            $search.val('');
            lastDispatchedSearch = '';
            updateSearchUi();
            updateHistoryBadge(customerName || null);
            currentPage = 1;
            load();
        }
    }

    function clearHistoryFilter() {
        activeCustomerId = null;
        updateHistoryBadge(null);
        currentPage = 1;
        load();
    }

    function updateSearchUi() {
        const raw = ($search.val() || '').trim();
        const searchActive = raw.length >= SEARCH_MIN_LENGTH;
        const historyActive = Boolean(activeCustomerId);

        // Show/hide the helper hint and the "clear" button for the search input.
        $searchHint.toggleClass('d-none', !searchActive);
        $clearSearch.toggleClass('d-none', raw.length === 0);

        // Either search or customer-history overrides the date range on the server;
        // mirror that visually so the user understands those filters do not apply.
        const dateBypass = searchActive || historyActive;
        $startDate.prop('disabled', dateBypass);
        $endDate.prop('disabled', dateBypass);
        $('.preset-range').prop('disabled', dateBypass);

        // Also disable the search field while history mode is active (they are mutually
        // exclusive). The user must close the history badge first to search.
        $search.prop('disabled', historyActive);
    }

    function load() {
        $tbody.html(
            '<tr><td colspan="8" class="text-center text-muted py-4">' +
            '<i class="fas fa-spinner fa-spin me-2"></i>Cargando…' +
            '</td></tr>',
        );

        updateSearchUi();
        lastDispatchedSearch = currentSearchTerm();

        const params = {
            csrf_token: vars('csrf_token'),
            q: lastDispatchedSearch,
            id_users_customer: activeCustomerId || '',
            start_date: $startDate.val(),
            end_date: $endDate.val(),
            id_services: $service.val(),
            status: $status.val(),
            page: currentPage,
            per_page: $perPage.val(),
            order: currentOrder,
        };

        return $.post(App.Utils.Url.siteUrl('appointments_management/search'), params)
            .done((response) => {
                if (!response || !Array.isArray(response.data)) {
                    $tbody.html(
                        '<tr><td colspan="8" class="text-center text-danger py-4">' +
                        'Respuesta inesperada del servidor.' +
                        '</td></tr>',
                    );
                    return;
                }
                totalRows = response.total || 0;
                renderRows(response.data);
                initializeRowDropdowns();
                updatePagination();
                updateSortIcon();

                // Refresh the customer-history badge with the name the server resolved.
                if (activeCustomerId && response.customer_filter_name) {
                    updateHistoryBadge(response.customer_filter_name);
                }
            })
            .fail((jqXHR) => {
                const msg =
                    (jqXHR.responseJSON && jqXHR.responseJSON.message) ||
                    'Error al cargar las citas.';
                $tbody.html(
                    `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(msg)}</td></tr>`,
                );
            });
    }

    function bindEvents() {
        $('.preset-range').on('click', function () {
            presetRange($(this).data('preset'));
            currentPage = 1;
            load();
        });

        $apply.on('click', () => {
            currentPage = 1;
            load();
        });

        $perPage.on('change', () => {
            currentPage = 1;
            load();
        });

        $prev.on('click', () => {
            if (currentPage > 1) {
                currentPage -= 1;
                load();
            }
        });

        $next.on('click', () => {
            currentPage += 1;
            load();
        });

        $sortHeader.on('click', () => {
            currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            currentPage = 1;
            load();
        });

        // Live search with debounce. A 1-character term is ignored on the wire (the
        // server enforces >= 2 too); empty input restores the regular filter behavior.
        $search.on('input', function () {
            updateSearchUi();

            const term = currentSearchTerm();

            // Skip the trip to the server if the effective search term has not changed
            // (e.g. user typed and then deleted within the same debounce window, or is
            // typing a single character that we already ignore).
            if (term === lastDispatchedSearch) {
                return;
            }

            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                currentPage = 1;
                load();
            }, SEARCH_DEBOUNCE_MS);
        });

        $clearSearch.on('click', () => {
            $search.val('');
            updateSearchUi();
            if (lastDispatchedSearch !== '') {
                currentPage = 1;
                load();
            }
            $search.trigger('focus');
        });

        $clearHistory.on('click', () => {
            clearHistoryFilter();
        });

        $('#export-pdf').on('click', function () { triggerExport('export_pdf', $(this)); });
        $('#export-csv').on('click', function () { triggerExport('export_csv', $(this)); });

        bindRowActions();
    }

    /**
     * Trigger one of the export endpoints with the current filter set encoded as a query
     * string.
     *
     * The PDF generation can take several seconds for large result sets (dompdf is slow),
     * so we don't use a plain `<a download>` — instead we fetch the file as a blob in the
     * background and only after it lands do we synthesize the download. While it runs the
     * button is disabled and shows "Generando…" with a spinner; on error we show a toast
     * and restore the button.
     *
     * The `scope` radio decides whether to export all matching rows or just the page
     * currently on screen. When scope = "page" we also forward the page/per_page/order
     * so the server reproduces exactly the slice the user is looking at.
     */
    function triggerExport(action, $button) {
        const scope = $('input[name="export-scope"]:checked').val() || 'all';

        const params = {
            scope: scope,
            q: currentSearchTerm(),
            id_users_customer: activeCustomerId || '',
            start_date: $startDate.val() || '',
            end_date: $endDate.val() || '',
            id_services: $service.val() || '',
            status: $status.val() || '',
        };

        if (scope === 'page') {
            params.page = currentPage;
            params.per_page = $perPage.val();
            params.order = currentOrder;
        }

        // Force `download` disposition on the server so the filename header is present;
        // the actual triggering of the download happens via blob below either way.
        params.download = 1;

        const query = $.param(
            Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null)),
        );
        const url = App.Utils.Url.siteUrl('appointments_management/' + action) + (query ? '?' + query : '');

        const originalHtml = $button.html();
        $button
            .prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generando…');

        fetch(url, { credentials: 'same-origin' })
            .then(async (response) => {
                if (!response.ok) {
                    let detail = 'HTTP ' + response.status;
                    try {
                        const text = await response.text();
                        if (text) detail += ' — ' + text.substring(0, 200);
                    } catch (_) {}
                    throw new Error(detail);
                }
                const filename = parseFilenameFromHeaders(response.headers)
                    || defaultExportFilename(action);
                const blob = await response.blob();
                return { blob, filename };
            })
            .then(({ blob, filename }) => {
                const blobUrl = URL.createObjectURL(blob);
                const $a = $('<a>', { href: blobUrl, download: filename })
                    .css('display', 'none')
                    .appendTo('body');
                $a.get(0).click();
                $a.remove();
                // Free the blob a tick later so the download has time to start.
                setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
            })
            .catch((err) => {
                showToast('No se pudo generar el archivo: ' + err.message, 'danger');
            })
            .finally(() => {
                $button.prop('disabled', false).html(originalHtml);
            });
    }

    function parseFilenameFromHeaders(headers) {
        const cd = headers.get('Content-Disposition') || '';
        // RFC 5987 (filename*) or plain filename="..."
        let match = cd.match(/filename\*=UTF-8''([^;]+)/i);
        if (match) {
            try { return decodeURIComponent(match[1]); } catch (_) { /* fall through */ }
        }
        match = cd.match(/filename="?([^"]+)"?/i);
        return match ? match[1] : null;
    }

    function defaultExportFilename(action) {
        const stamp = moment().format('YYYYMMDD-HHmmss');
        return action === 'export_pdf' ? `agenda-${stamp}.pdf` : `agenda-${stamp}.csv`;
    }

    /**
     * Event delegation for all per-row actions inside the table. The delegate root is the
     * table body so handlers survive every re-render.
     */
    function bindRowActions() {
        // Resend confirmation email
        $tbody.on('click', '.action-resend', function (e) {
            e.preventDefault();
            const row = getRowDataFromTrigger(this);
            if (!row.id) return;

            if (!confirm('¿Reenviar el correo de confirmación al cliente?')) {
                return;
            }

            const $link = $(this);
            const original = $link.html();
            $link.html('<i class="fas fa-spinner fa-spin me-2"></i>Enviando…');

            $.post(App.Utils.Url.siteUrl('appointments_management/resend_confirmation'), {
                csrf_token: vars('csrf_token'),
                appointment_id: row.id,
            })
                .done((res) => {
                    if (res && res.success) {
                        showToast('Correo reenviado a ' + (res.email || 'el cliente'), 'success');
                    } else {
                        const msg = (res && res.message) || 'No se pudo reenviar el correo.';
                        showToast(msg, 'danger');
                    }
                })
                .fail((jqXHR) => {
                    const msg =
                        (jqXHR.responseJSON && jqXHR.responseJSON.message) ||
                        'No se pudo reenviar el correo.';
                    showToast(msg, 'danger');
                })
                .always(() => {
                    $link.html(original);
                });
        });

        // Change status
        $tbody.on('click', '.action-set-status', function (e) {
            e.preventDefault();
            const row = getRowDataFromTrigger(this);
            const newStatus = $(this).data('status');
            if (!row.id || !newStatus || newStatus === row.status) return;

            const $row = $tbody.find(`tr[data-appointment-id="${row.id}"]`);
            const $statusCell = $row.find('.status-cell');
            const previousHtml = $statusCell.html();
            $statusCell.html('<span class="spinner-border spinner-border-sm text-muted" role="status"></span>');

            $.post(App.Utils.Url.siteUrl('appointments_management/update_status'), {
                csrf_token: vars('csrf_token'),
                appointment_id: row.id,
                status: newStatus,
            })
                .done((res) => {
                    if (res && res.success) {
                        $statusCell.html(renderStatusBadge(res.status));
                        // Re-render the actions cell so the checkmark moves to the new status.
                        // renderActionsMenu() re-encodes the data-row payload safely for HTML
                        // injection; calling .html() with that string keeps DOM parsing
                        // consistent (the attribute is decoded once by the browser, as in the
                        // initial render).
                        const $row2 = $tbody.find(`tr[data-appointment-id="${row.id}"]`);
                        const data = Object.assign({}, row, { status: res.status });
                        $row2.find('td:last').html(renderActionsMenu(data));
                        initializeRowDropdowns();
                        showToast('Estado actualizado a "' + res.status + '"', 'success');
                    } else {
                        $statusCell.html(previousHtml);
                        const msg = (res && res.message) || 'No se pudo actualizar el estado.';
                        showToast(msg, 'danger');
                    }
                })
                .fail((jqXHR) => {
                    $statusCell.html(previousHtml);
                    const msg =
                        (jqXHR.responseJSON && jqXHR.responseJSON.message) ||
                        'No se pudo actualizar el estado.';
                    showToast(msg, 'danger');
                });
        });

        // Copy folio to clipboard
        $tbody.on('click', '.action-copy-folio', function (e) {
            e.preventDefault();
            const row = getRowDataFromTrigger(this);
            if (!row.folio) return;

            const fallbackCopy = (text) => {
                const $temp = $('<textarea>')
                    .css({ position: 'fixed', top: '-1000px', opacity: '0' })
                    .val(text)
                    .appendTo('body');
                $temp.get(0).select();
                try {
                    document.execCommand('copy');
                    showToast('Folio copiado: ' + text, 'success');
                } catch (err) {
                    showToast('No se pudo copiar el folio.', 'danger');
                } finally {
                    $temp.remove();
                }
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard
                    .writeText(row.folio)
                    .then(() => showToast('Folio copiado: ' + row.folio, 'success'))
                    .catch(() => fallbackCopy(row.folio));
            } else {
                fallbackCopy(row.folio);
            }
        });

        // Customer history
        $tbody.on('click', '.action-history', function (e) {
            e.preventDefault();
            const row = getRowDataFromTrigger(this);
            if (!row.customer_id) return;
            setHistoryCustomer(row.customer_id, row.customer_name);
        });
    }

    function initializeDatePickers() {
        if (typeof flatpickr !== 'function') return;

        const onUserChange = () => {
            // Any manual date change clears the active preset highlight. We skip the call
            // when the change came from presetRange() itself (programmaticDateChange flag).
            if (!programmaticDateChange) {
                setActivePreset(null);
            }
        };

        const common = {
            dateFormat: 'Y-m-d',
            allowInput: true,
            onChange: onUserChange,
        };

        startFp = flatpickr('#filter-start-date', common);
        endFp = flatpickr('#filter-end-date', common);
    }

    function initialize() {
        initializeDatePickers();
        presetRange('today');
        bindEvents();
        load();
    }

    return {
        initialize,
    };
})();

$(() => {
    App.Pages.AppointmentsManagement.initialize();
});
