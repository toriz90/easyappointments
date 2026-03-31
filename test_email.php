<?php
/**
 * Email Test Script - Diagnose SMTP connectivity
 * Access: https://citas.honeywhale.com.mx/test_email.php?key=hwtest2026&to=YOUR_EMAIL
 * DELETE THIS FILE after diagnosis is complete.
 */

$secret = 'hwtest2026';
if (($_GET['key'] ?? '') !== $secret) {
    die('Access denied. Use: ?key=hwtest2026&to=your@email.com');
}

$to = $_GET['to'] ?? null;
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    die('Provide a valid recipient: ?key=hwtest2026&to=your@email.com');
}

require __DIR__ . '/vendor/autoload.php';

// Read settings from email.php config
$config = [];
// Parse the config file to extract SMTP settings
$config_content = file_get_contents(__DIR__ . '/application/config/email.php');
preg_match_all('/\$config\[\'(\w+)\'\]\s*=\s*[\'"]?([^\'";\n]+)[\'"]?\s*;/', $config_content, $matches);
for ($i = 0; $i < count($matches[1]); $i++) {
    $config[$matches[1][$i]] = trim($matches[2][$i]);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

echo '<pre style="font-family:monospace; background:#1e1e1e; color:#d4d4d4; padding:20px;">';
echo "=== Honey Whale Email Diagnostic ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "SMTP Host: smtp.gmail.com:587 (TLS)\n";
echo "From: hola@honeywhale.com.mx\n";
echo "To: $to\n\n";
echo "--- SMTP Debug Output ---\n";

try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function($str, $level) {
        echo htmlspecialchars($str) . "\n";
    };

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'hola@honeywhale.com.mx';
    $mail->Password   = 'vdmppehfsvmikbqm';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 15;

    $mail->setFrom('hola@honeywhale.com.mx', 'Honey Whale');
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = '[TEST] Honey Whale SMTP - ' . date('Y-m-d H:i:s');
    $mail->Body    = '<p>Este es un correo de prueba del sistema de citas Honey Whale.</p><p>Si recibiste este mensaje, el SMTP está funcionando correctamente.</p>';
    $mail->AltBody = 'Este es un correo de prueba del sistema de citas Honey Whale. Si recibiste este mensaje, el SMTP está funcionando correctamente.';

    $mail->send();
    echo "\n✅ ÉXITO: Correo enviado correctamente a $to\n";
    echo "Revisa tu bandeja de entrada (y carpeta de spam).\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: No se pudo enviar el correo.\n";
    echo "Error PHPMailer: " . $mail->ErrorInfo . "\n";
    echo "Excepción: " . $e->getMessage() . "\n\n";
    echo "POSIBLES CAUSAS:\n";
    echo "1. La contraseña de aplicación de Gmail fue revocada\n";
    echo "   Solución: Crear una nueva contraseña en myaccount.google.com > Seguridad > Contraseñas de aplicación\n";
    echo "2. La verificación en 2 pasos está desactivada\n";
    echo "   Solución: Activar 2FA en la cuenta de Google\n";
    echo "3. Problema de red/firewall del servidor\n";
    echo "   Solución: Verificar conectividad al puerto 587 de smtp.gmail.com\n";
}

echo '</pre>';
