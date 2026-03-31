<?php
/**
 * Página de diagnóstico para EasyAppointments
 *
 * IMPORTANTE: Eliminar este archivo después de diagnosticar el problema.
 * Acceder desde: http://tu-dominio/diagnostico.php
 */

// Protección básica con clave de acceso
$clave = $_GET['clave'] ?? '';
if ($clave !== 'debug2026') {
    die('Acceso denegado. Agrega ?clave=debug2026 a la URL.');
}

$resultados = [];

// 1. Verificar config.php
$resultados['config_php'] = [
    'nombre' => 'Archivo config.php',
    'ok' => file_exists(__DIR__ . '/config.php'),
    'mensaje' => file_exists(__DIR__ . '/config.php')
        ? 'Existe'
        : 'FALTANTE - Copia config-sample.php a config.php y edita con tus datos',
];

// 2. Cargar config si existe
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';

    $resultados['base_url'] = [
        'nombre' => 'BASE_URL configurada',
        'ok' => !empty(Config::BASE_URL),
        'mensaje' => Config::BASE_URL,
    ];

    // 3. Probar conexión a base de datos
    try {
        $mysqli = new mysqli(Config::DB_HOST, Config::DB_USERNAME, Config::DB_PASSWORD, Config::DB_NAME);
        if ($mysqli->connect_error) {
            $resultados['db_conexion'] = [
                'nombre' => 'Conexión a base de datos',
                'ok' => false,
                'mensaje' => 'ERROR: ' . $mysqli->connect_error,
            ];
        } else {
            $resultados['db_conexion'] = [
                'nombre' => 'Conexión a base de datos',
                'ok' => true,
                'mensaje' => 'OK - Host: ' . Config::DB_HOST . ' / DB: ' . Config::DB_NAME,
            ];

            // 4. Verificar tablas existentes
            $tablas_requeridas = [
                'ea_users', 'ea_user_settings', 'ea_roles', 'ea_settings',
                'ea_services', 'ea_appointments', 'ea_providers_services',
                'ea_custom_fields', 'ea_custom_field_options', 'ea_custom_field_values',
            ];

            foreach ($tablas_requeridas as $tabla) {
                $result = $mysqli->query("SHOW TABLES LIKE '{$tabla}'");
                $existe = $result && $result->num_rows > 0;
                $resultados['tabla_' . $tabla] = [
                    'nombre' => "Tabla: {$tabla}",
                    'ok' => $existe,
                    'mensaje' => $existe ? 'Existe' : 'FALTANTE - Ejecutar migraciones desde /index.php/update',
                ];
            }

            // 5. Verificar usuario admin
            $result = $mysqli->query("SELECT COUNT(*) as c FROM ea_users u JOIN ea_roles r ON u.id_roles = r.id WHERE r.slug = 'admin'");
            if ($result) {
                $row = $result->fetch_assoc();
                $resultados['admin_user'] = [
                    'nombre' => 'Usuario administrador',
                    'ok' => $row['c'] > 0,
                    'mensaje' => $row['c'] > 0 ? "Existen {$row['c']} administrador(es)" : 'NO HAY ADMIN - Instalar desde /index.php/installation',
                ];
            }

            // 6. Verificar migraciones ejecutadas
            $result = $mysqli->query("SELECT MAX(version) as v FROM ea_migrations");
            if ($result) {
                $row = $result->fetch_assoc();
                $version_actual = (int) $row['v'];
                $version_esperada = 63; // Última migración disponible
                $resultados['migraciones'] = [
                    'nombre' => 'Versión de migraciones',
                    'ok' => $version_actual >= $version_esperada,
                    'mensaje' => "Versión actual: {$version_actual} / Esperada: {$version_esperada}" .
                        ($version_actual < $version_esperada ? ' - PENDIENTES: ir a /index.php/update' : ''),
                ];
            }

            // 7. Verificar configuración minimum_advance_booking
            $result = $mysqli->query("SELECT value FROM ea_settings WHERE name = 'minimum_advance_booking'");
            if ($result && $result->num_rows > 0) {
                $resultados['setting_minimum_advance'] = [
                    'nombre' => 'Setting: minimum_advance_booking',
                    'ok' => true,
                    'mensaje' => 'Existe',
                ];
            } else {
                $resultados['setting_minimum_advance'] = [
                    'nombre' => 'Setting: minimum_advance_booking',
                    'ok' => false,
                    'mensaje' => 'FALTANTE - Ejecutar migración 061',
                ];
            }

            $mysqli->close();
        }
    } catch (Exception $e) {
        $resultados['db_conexion'] = [
            'nombre' => 'Conexión a base de datos',
            'ok' => false,
            'mensaje' => 'EXCEPCIÓN: ' . $e->getMessage(),
        ];
    }
}

// 8. Verificar permisos de directorios
$dirs_escribibles = [
    'storage/cache' => __DIR__ . '/storage/cache',
    'storage/logs' => __DIR__ . '/storage/logs',
    'storage/uploads' => __DIR__ . '/storage/uploads',
];
foreach ($dirs_escribibles as $nombre => $path) {
    $existe = is_dir($path);
    $escribible = $existe && is_writable($path);
    $resultados['dir_' . str_replace('/', '_', $nombre)] = [
        'nombre' => "Directorio: {$nombre}",
        'ok' => $escribible,
        'mensaje' => !$existe ? 'NO EXISTE' : (!$escribible ? 'SIN PERMISOS DE ESCRITURA' : 'OK'),
    ];
}

// 9. Versión de PHP
$php_version = PHP_VERSION;
$php_ok = version_compare($php_version, '8.0', '>=');
$resultados['php_version'] = [
    'nombre' => 'Versión de PHP',
    'ok' => $php_ok,
    'mensaje' => $php_version . ($php_ok ? '' : ' - Se requiere PHP 8.0+'),
];

// Mostrar resultados
$todos_ok = array_reduce($resultados, fn($carry, $item) => $carry && $item['ok'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico - EasyAppointments</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        h1 { color: #333; }
        .item { padding: 10px; margin: 5px 0; border-radius: 4px; }
        .ok { background: #d4edda; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .nombre { font-weight: bold; }
        .mensaje { color: #555; font-size: 0.9em; margin-top: 4px; }
        .resumen { padding: 15px; margin: 20px 0; border-radius: 4px; font-size: 1.2em; }
        .resumen.ok { background: #d4edda; }
        .resumen.error { background: #f8d7da; }
        .advertencia { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico EasyAppointments</h1>

    <div class="advertencia">
        <strong>⚠️ IMPORTANTE:</strong> Elimina este archivo (<code>diagnostico.php</code>) después de resolver el problema por seguridad.
    </div>

    <div class="resumen <?= $todos_ok ? 'ok' : 'error' ?>">
        <?= $todos_ok ? '✅ Todo en orden' : '❌ Se encontraron problemas - revisar elementos en rojo abajo' ?>
    </div>

    <?php foreach ($resultados as $resultado): ?>
        <div class="item <?= $resultado['ok'] ? 'ok' : 'error' ?>">
            <div class="nombre"><?= $resultado['ok'] ? '✅' : '❌' ?> <?= htmlspecialchars($resultado['nombre']) ?></div>
            <div class="mensaje"><?= htmlspecialchars($resultado['mensaje']) ?></div>
        </div>
    <?php endforeach; ?>

    <hr>
    <h2>Pasos para resolver problemas comunes:</h2>
    <ol>
        <li><strong>config.php faltante:</strong> <code>cp config-sample.php config.php</code> y edita con tus credenciales reales</li>
        <li><strong>Migraciones pendientes:</strong> Ve a <code>/index.php/update</code> en tu navegador</li>
        <li><strong>Sin usuario admin:</strong> Ve a <code>/index.php/installation</code></li>
        <li><strong>Permisos:</strong> <code>chown -R www-data:www-data storage/</code> y <code>chmod -R 755 storage/</code></li>
        <li><strong>Rate limit (HTTP 429):</strong> Espera 2 minutos o limpia la caché: <code>rm -rf storage/cache/*</code></li>
    </ol>
</body>
</html>
