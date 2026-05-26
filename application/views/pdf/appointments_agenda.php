<?php
/**
 * Local variables.
 *
 * @var array       $rows
 * @var int         $total
 * @var string      $generated_at
 * @var string|null $range_label
 * @var string|null $center_name
 * @var string|null $status_label
 * @var string|null $search_label
 * @var string|null $scope_label
 */
$scope_label = $scope_label ?? null;

// When the list is filtered to a single service, hide the column from the table and
// surface the center name in the heading instead.
$show_center_column = empty($center_name);
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agenda de citas — Honey Whale</title>
    <style>
        @page { margin: 24px 28px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }

        /* ===== Cabecera ===== */
        .header {
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .brand {
            font-size: 16pt;
            font-weight: bold;
            color: #1a1a1a;
        }
        .brand-sub {
            font-size: 9pt;
            color: #6f6f6f;
            margin-top: 2px;
        }
        .title {
            font-size: 13pt;
            font-weight: bold;
            margin-top: 4px;
        }

        .meta {
            margin-top: 6px;
            font-size: 9pt;
            color: #4a4a4a;
        }
        .meta .meta-row { margin-bottom: 2px; }
        .meta .meta-label { color: #6f6f6f; }

        /* ===== Tabla ===== */
        table.agenda {
            width: 100%;
            border-collapse: collapse;
        }
        table.agenda thead {
            display: table-header-group; /* repeat header on each page break */
        }
        table.agenda th {
            background-color: #1a1a1a;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #1a1a1a;
        }
        table.agenda td {
            font-size: 9pt;
            padding: 5px 8px;
            border: 1px solid #d4d4d4;
            vertical-align: top;
        }
        table.agenda tr.alt td { background-color: #f7f7f7; }

        .folio-cell {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8.5pt;
            white-space: nowrap;
        }
        .time-cell { white-space: nowrap; }
        .phone-cell { white-space: nowrap; }

        /* Status pills — monochrome with thin border to keep the printed look sober. */
        .status {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            border: 1px solid #b9b9b9;
            font-size: 8.5pt;
            background-color: #f0f0f0;
            white-space: nowrap;
        }
        .status.confirmada { background-color: #e7eef7; border-color: #b6c7df; }
        .status.atendida   { background-color: #e6f4ea; border-color: #b7dec1; }
        .status.no-presento { background-color: #fff4d6; border-color: #e7d59a; }
        .status.cancelada  { background-color: #ececec; border-color: #c9c9c9; color: #555; }

        /* ===== Vacío ===== */
        .empty {
            text-align: center;
            padding: 28px 0;
            color: #6f6f6f;
            font-style: italic;
        }

        /* ===== Pie ===== */
        .footer {
            margin-top: 14px;
            font-size: 8pt;
            color: #6f6f6f;
            text-align: right;
        }
    </style>
</head>
<body>

    <table width="100%" cellspacing="0" cellpadding="0" class="header">
        <tr>
            <td>
                <div class="brand">Honey Whale</div>
                <div class="brand-sub">Centro de Servicio</div>
            </td>
            <td style="text-align: right; vertical-align: bottom;">
                <div class="title">Agenda de citas</div>
            </td>
        </tr>
    </table>

    <div class="meta">
        <?php if (!empty($range_label)): ?>
            <div class="meta-row">
                <span class="meta-label">Rango:</span> <?= e($range_label) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($center_name)): ?>
            <div class="meta-row">
                <span class="meta-label">Centro de servicio:</span> <strong><?= e($center_name) ?></strong>
            </div>
        <?php endif; ?>
        <?php if (!empty($status_label)): ?>
            <div class="meta-row">
                <span class="meta-label">Estado:</span> <?= e($status_label) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($search_label)): ?>
            <div class="meta-row">
                <span class="meta-label">Búsqueda:</span> <?= e($search_label) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($scope_label)): ?>
            <div class="meta-row">
                <span class="meta-label">Alcance:</span> <?= e($scope_label) ?>
            </div>
        <?php endif; ?>
        <div class="meta-row">
            <span class="meta-label">Total de citas:</span> <strong><?= (int) $total ?></strong>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty">No hay citas que coincidan con los filtros aplicados.</div>
    <?php else: ?>
        <table class="agenda" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <th style="width: 14%;">Folio</th>
                    <th style="width: 14%;">Fecha y hora</th>
                    <th style="width: <?= $show_center_column ? '17%' : '22%' ?>;">Cliente</th>
                    <th style="width: 12%;">Teléfono</th>
                    <?php if ($show_center_column): ?>
                        <th style="width: 15%;">Centro</th>
                    <?php endif; ?>
                    <th style="width: <?= $show_center_column ? '17%' : '22%' ?>;">Equipo</th>
                    <th style="width: 11%;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $row):
                    $dt = $row['start_datetime'] ? new DateTime($row['start_datetime']) : null;
                    $date_label = $dt ? $dt->format('d/m/Y') . ' · ' . $dt->format('H:i') : '—';
                    $status_class = strtolower(strtr((string) $row['status'], [
                        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
                        ' ' => '-',
                    ]));
                ?>
                <tr<?= $i % 2 ? ' class="alt"' : '' ?>>
                    <td class="folio-cell"><?= e($row['folio'] ?: '—') ?></td>
                    <td class="time-cell"><?= e($date_label) ?></td>
                    <td><?= e($row['customer_name'] ?: '—') ?></td>
                    <td class="phone-cell"><?= e($row['customer_phone'] ?: '—') ?></td>
                    <?php if ($show_center_column): ?>
                        <td><?= e($row['service_name'] ?: '—') ?></td>
                    <?php endif; ?>
                    <td><?= e($row['equipment'] ?: '—') ?></td>
                    <td>
                        <?php if (!empty($row['status'])): ?>
                            <span class="status <?= e($status_class) ?>"><?= e($row['status']) ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        Generado el <?= e($generated_at) ?> · honeywhale.com.mx
    </div>

</body>
</html>
