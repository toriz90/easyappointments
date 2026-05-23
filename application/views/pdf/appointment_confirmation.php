<?php
/**
 * Local variables.
 *
 * @var string $folio
 * @var string $sucursal
 * @var string $fecha
 * @var string $hora
 * @var string $nombre_completo
 * @var string $telefono
 * @var string $email
 * @var string $equipo
 * @var string $motivo
 */
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de cita de servicio</title>
    <style>
        @page { margin: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            line-height: 1.25;
        }

        /* ===== Cabecera ===== */
        .header {
            background-color: #1a1a1a;
            color: #ffffff;
            padding: 14px 28px;
        }
        .brand-title {
            font-size: 18pt;
            font-weight: bold;
            line-height: 1.1;
            color: #ffffff;
        }
        .brand-sub {
            font-size: 9.5pt;
            color: #b9b9b9;
            margin-top: 2px;
        }

        /* ===== Contenido ===== */
        .content { padding: 14px 28px 0 28px; }

        h1.title {
            font-size: 13pt;
            font-weight: bold;
            text-align: center;
            margin: 0 0 12px 0;
            color: #1a1a1a;
        }

        /* ===== Caja del folio ===== */
        .folio-wrap {
            width: 56%;
            margin: 0 auto 12px auto;
        }
        .folio-box {
            background-color: #f4f4f4;
            border: 1px solid #dcdcdc;
            padding: 8px 0 9px 0;
            text-align: center;
        }
        .folio-label {
            font-size: 9pt;
            color: #6f6f6f;
        }
        .folio-value {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 16pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-top: 3px;
        }

        /* ===== Secciones ===== */
        h2.section {
            font-size: 11pt;
            font-weight: bold;
            color: #1a1a1a;
            border-bottom: 1px solid #d4d4d4;
            padding-bottom: 3px;
            margin: 10px 0 4px 0;
        }

        /* ===== Tablas etiqueta/valor ===== */
        table.kv {
            width: 100%;
            border-collapse: collapse;
        }
        table.kv td {
            padding: 3px 0;
            vertical-align: top;
            border-bottom: 1px solid #ececec;
        }
        table.kv td.k {
            color: #6f6f6f;
            font-size: 10pt;
            width: 38%;
        }
        table.kv td.v {
            color: #1a1a1a;
            font-size: 10pt;
            font-weight: bold;
            text-align: right;
        }

        /* ===== Recomendaciones ===== */
        .rec-box {
            background-color: #fafafa;
            border: 1px solid #ececec;
            margin-top: 8px;
        }
        .rec-inner { padding: 8px 12px; }
        .rec-intro {
            font-size: 10pt;
            color: #1a1a1a;
            margin: 0 0 3px 0;
        }
        table.rec-list {
            width: 100%;
            border-collapse: collapse;
        }
        table.rec-list td {
            padding: 1px 0;
            font-size: 10pt;
            color: #1a1a1a;
        }
        table.rec-list td.rec-check {
            width: 20px;
            font-weight: bold;
            color: #1a1a1a;
        }

        /* ===== Cierre ===== */
        .closing {
            text-align: center;
            margin-top: 15px;
            margin-bottom: 2px;
            font-size: 10.5pt;
            color: #1a1a1a;
        }
        .signature {
            text-align: center;
            margin-top: 2px;
            margin-bottom: 0;
            font-size: 10pt;
            color: #6f6f6f;
        }

        /* ===== Pie de página ===== */
        .footer {
            background-color: #f0f0f0;
            color: #5a5a5a;
            text-align: center;
            font-size: 8.5pt;
            padding: 8px 20px;
            margin-top: 14px;
        }
    </style>
</head>
<body>

    <!-- Cabecera -->
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td class="header">
                <div class="brand-title">Honey Whale</div>
                <div class="brand-sub">Centro de Servicio</div>
            </td>
        </tr>
    </table>

    <!-- Contenido -->
    <div class="content">

        <h1 class="title">Confirmación de cita de servicio</h1>

        <!-- Folio -->
        <table class="folio-wrap" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <table class="folio-box" width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>
                                <div class="folio-label">Folio de cita</div>
                                <div class="folio-value"><?= e($folio) ?></div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Datos de la cita -->
        <h2 class="section">Datos de la cita</h2>
        <table class="kv">
            <tr>
                <td class="k">Sucursal</td>
                <td class="v"><?= e($sucursal) ?></td>
            </tr>
            <tr>
                <td class="k">Fecha</td>
                <td class="v"><?= e($fecha) ?></td>
            </tr>
            <tr>
                <td class="k">Hora</td>
                <td class="v"><?= e($hora) ?></td>
            </tr>
        </table>

        <!-- Datos del cliente -->
        <h2 class="section">Datos del cliente</h2>
        <table class="kv">
            <tr>
                <td class="k">Nombre completo</td>
                <td class="v"><?= e($nombre_completo) ?></td>
            </tr>
            <tr>
                <td class="k">Teléfono</td>
                <td class="v"><?= e($telefono) ?></td>
            </tr>
            <tr>
                <td class="k">Correo electrónico</td>
                <td class="v"><?= e($email) ?></td>
            </tr>
            <tr>
                <td class="k">Equipo</td>
                <td class="v"><?= e($equipo) ?></td>
            </tr>
            <tr>
                <td class="k">Motivo de servicio</td>
                <td class="v"><?= e($motivo) ?></td>
            </tr>
        </table>

        <!-- Recomendaciones -->
        <h2 class="section">Recomendaciones para tu visita</h2>
        <table class="rec-box" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="rec-inner">
                    <p class="rec-intro">Llega 5 minutos antes de tu hora agendada y presenta los siguientes documentos y elementos:</p>
                    <table class="rec-list">
                        <tr><td class="rec-check">&#10003;</td><td>Póliza de garantía</td></tr>
                        <tr><td class="rec-check">&#10003;</td><td>Comprobante de compra</td></tr>
                        <tr><td class="rec-check">&#10003;</td><td>Equipo a reparar</td></tr>
                        <tr><td class="rec-check">&#10003;</td><td>Identificación oficial</td></tr>
                        <tr><td class="rec-check">&#10003;</td><td>Este documento, impreso o en tu celular</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Cierre -->
        <p class="closing">Te esperamos para brindarte la mejor atención.</p>
        <p class="signature">Atentamente, Equipo Honey Whale</p>

    </div>

    <!-- Pie de página -->
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td class="footer">honeywhale.com.mx &middot; Honey Whale Centro de Servicio</td>
        </tr>
    </table>

</body>
</html>
