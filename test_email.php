<?php
/**
 * Script de prueba para verificar conexión SMTP
 * Accede a: http://localhost/CineBlog/test_email.php
 */

// Cargar configuración
$mailConfig = require 'C:\\xampp\\config\\cineblog_mail.php';

// Verificar PHPMailer
$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!file_exists($autoloadPath)) {
    die("❌ PHPMailer no instalado. Ejecuta: composer install en la carpeta CineBlog");
}

require $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>🧪 Test de Conexión SMTP</h2>";

// Información de configuración
echo "<h3>📋 Configuración Actual:</h3>";
echo "<pre>";
echo "Host: " . $mailConfig['host'] . "\n";
echo "Port: " . $mailConfig['port'] . "\n";
echo "Secure: " . $mailConfig['secure'] . "\n";
echo "Username: " . $mailConfig['username'] . "\n";
echo "Verify SSL: " . ($mailConfig['verify_ssl'] ? 'Sí' : 'No') . "\n";
echo "</pre>";

// Intentar conexión
echo "<h3>🔌 Intentando conexión...</h3>";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    $mail->SMTPSecure = $mailConfig['secure'];
    $mail->Port = $mailConfig['port'];

    // Configurar SSL
    $caFile = 'C:\\xampp\\php\\extras\\ssl\\cacert.pem';
    if ($mailConfig['verify_ssl'] && file_exists($caFile)) {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'cafile' => $caFile,
            ],
        ];
    } else {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
    }

    // Conectar y verificar
    $mail->smtpConnect();
    echo "✅ <strong>Conexión SMTP exitosa!</strong>\n";
    $mail->smtpClose();

    // Intentar enviar un correo de prueba
    echo "<h3>📧 Intentando enviar correo de prueba...</h3>";
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    $mail->SMTPSecure = $mailConfig['secure'];
    $mail->Port = $mailConfig['port'];
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
    $mail->addAddress($mailConfig['username']);
    $mail->Subject = 'Test CineBlog SMTP';
    $mail->Body = 'Si recibes este correo, SMTP está funcionando correctamente.';
    $mail->send();

    echo "✅ <strong>Correo de prueba enviado a " . $mailConfig['username'] . "</strong>\n";

} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage() . "\n";
    echo "<pre>" . $mail->ErrorInfo . "</pre>";
}

echo "<hr>";
echo "<p>Para eliminar este archivo después de las pruebas, ejecuta en terminal:</p>";
echo "<code>del C:\\xampp\\htdocs\\CineBlog\\test_email.php</code>";
?>
