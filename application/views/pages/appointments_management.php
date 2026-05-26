<?php extend('layouts/backend_layout'); ?>

<?php section('styles'); ?>
<style>
    /* ===== Estado visual del preset activo ===== */
    #appointments-management-page .preset-range.active {
        background-color: #1a1a1a;
        border-color: #1a1a1a;
        color: #fff;
    }

    /* ===== Responsive < md: tabla → cards apiladas ==========================
       En móvil cada cita se ve como una tarjeta vertical con etiquetas (label)
       a la izquierda y valores a la derecha. Reutilizamos el mismo markup de
       <table>; solo cambiamos su layout con CSS. El JS añade data-label="..."
       a cada celda para que aparezca como etiqueta arriba del valor. */
    @media (max-width: 767.98px) {
        #appointments-table thead { display: none; }

        #appointments-table,
        #appointments-table tbody,
        #appointments-table tr,
        #appointments-table td {
            display: block;
            width: 100%;
        }

        #appointments-table tr {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0.75rem;
            background-color: #fff;
        }

        #appointments-table td {
            border: 0;
            border-bottom: 1px solid #f1f3f5;
            padding: 0.4rem 0;
            text-align: right;
            font-size: 0.9rem;
        }
        #appointments-table td:last-child { border-bottom: 0; padding-top: 0.5rem; }

        #appointments-table td::before {
            content: attr(data-label);
            display: inline-block;
            font-weight: 600;
            color: #6c757d;
            margin-right: 0.75rem;
            float: left;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* La celda de acciones no necesita la etiqueta a la izquierda. */
        #appointments-table td.actions-cell { text-align: right; }
        #appointments-table td.actions-cell::before { content: ''; margin: 0; }

        /* Que la barra de acciones (Imprimir/CSV/scope) no se vea apretada. */
        #appointments-management-page .btn-group { flex-wrap: wrap; }
    }
</style>
<?php end_section('styles'); ?>

<?php section('content'); ?>

<div class="container-fluid backend-page" id="appointments-management-page">

    <div class="row mb-3">
        <div class="col">
            <h4 class="text-black-50 mb-0 fw-light">
                <?= lang('appointments_management') ?>
            </h4>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">

            <!-- Buscador (folio, nombre, teléfono, correo). Si se usa, el rango de fecha
                 se ignora — el buscador localiza una cita puntual sin importar el periodo. -->
            <div class="row mb-3">
                <div class="col-12 col-md-6 col-lg-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input id="filter-search" type="text" class="form-control"
                               placeholder="Buscar por folio, nombre, teléfono o correo…"
                               autocomplete="off">
                        <button id="clear-search" type="button" class="btn btn-outline-secondary d-none"
                                title="Limpiar búsqueda">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <small id="search-hint" class="text-muted d-none">
                        Mostrando resultados de búsqueda — el rango de fecha se ignora.
                    </small>
                </div>
            </div>

            <div class="row g-2 align-items-end">

                <div class="col-12 col-md-auto">
                    <label class="form-label mb-1">Rango</label>
                    <div class="btn-group d-block d-md-inline-flex" role="group">
                        <button type="button" class="btn btn-outline-secondary btn-sm preset-range" data-preset="today">Hoy</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm preset-range" data-preset="tomorrow">Mañana</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm preset-range" data-preset="this_week">Esta semana</button>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <label for="filter-start-date" class="form-label mb-1">Desde</label>
                    <input id="filter-start-date" type="text" class="form-control form-control-sm" autocomplete="off">
                </div>

                <div class="col-6 col-md-2">
                    <label for="filter-end-date" class="form-label mb-1">Hasta</label>
                    <input id="filter-end-date" type="text" class="form-control form-control-sm" autocomplete="off">
                </div>

                <div class="col-12 col-md-3">
                    <label for="filter-service" class="form-label mb-1">Centro de servicio</label>
                    <select id="filter-service" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (vars('service_options') as $service): ?>
                            <option value="<?= (int) $service['id'] ?>"><?= e($service['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label for="filter-status" class="form-label mb-1">Estado</label>
                    <select id="filter-status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (vars('status_options') as $status): ?>
                            <option value="<?= e($status) ?>"><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-auto">
                    <button id="apply-filters" type="button" class="btn btn-primary btn-sm w-100 w-md-auto">
                        <i class="fas fa-filter me-1"></i>
                        Aplicar
                    </button>
                </div>

                <div class="col-12 col-md-auto ms-md-auto">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-md-end">

                        <div class="btn-group btn-group-sm" role="group" aria-label="Alcance de la exportación">
                            <input type="radio" class="btn-check" name="export-scope" id="scope-all" value="all" checked>
                            <label class="btn btn-outline-secondary" for="scope-all"
                                   title="Exportar todas las citas que coinciden con los filtros activos">
                                Todo lo filtrado
                            </label>

                            <input type="radio" class="btn-check" name="export-scope" id="scope-page" value="page">
                            <label class="btn btn-outline-secondary" for="scope-page"
                                   title="Exportar únicamente los registros visibles en la página actual">
                                Solo esta página
                            </label>
                        </div>

                        <div class="btn-group btn-group-sm" role="group" aria-label="Exportar">
                            <button id="export-pdf" type="button" class="btn btn-outline-dark"
                                    title="Imprimir agenda (PDF)">
                                <i class="fas fa-print me-1"></i>
                                Imprimir
                            </button>
                            <button id="export-csv" type="button" class="btn btn-outline-dark"
                                    title="Exportar a CSV">
                                <i class="fas fa-file-csv me-1"></i>
                                CSV
                            </button>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Etiqueta de historial activo (visible solo cuando se filtra por cliente) -->
    <div id="customer-history-badge" class="alert alert-info py-2 px-3 d-none d-flex align-items-center justify-content-between" role="alert">
        <span>
            <i class="fas fa-history me-2"></i>
            Mostrando historial del cliente: <strong id="customer-history-name"></strong>
        </span>
        <button id="clear-customer-history" type="button" class="btn-close" aria-label="Cerrar"></button>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body">

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="appointments-table">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th class="sortable" data-sort="start_datetime" style="cursor: pointer;">
                                Fecha y hora
                                <i class="fas fa-sort ms-1 text-muted small"></i>
                            </th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Centro de servicio</th>
                            <th>Equipo</th>
                            <th>Estado</th>
                            <th class="text-end" style="width: 1%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Cargando…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small id="pagination-info" class="text-muted">&nbsp;</small>

                <div class="d-flex align-items-center gap-2">
                    <label for="per-page" class="form-label mb-0 small text-muted">Por página:</label>
                    <select id="per-page" class="form-select form-select-sm" style="width: auto;">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>

                    <button id="page-prev" type="button" class="btn btn-outline-secondary btn-sm" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="page-indicator" class="small text-muted">1</span>
                    <button id="page-next" type="button" class="btn btn-outline-secondary btn-sm" disabled>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Contenedor de toasts para feedback de acciones -->
    <div id="appointments-management-toasts"
         class="toast-container position-fixed top-0 end-0 p-3"
         style="z-index: 1080;"></div>

</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/pages/appointments_management.js') ?>"></script>

<?php end_section('scripts'); ?>
