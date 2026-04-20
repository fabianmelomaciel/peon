<?php
/**
 * PEÓN MAIL HELPER
 * Envio de notificaciones tácticas vía SMTP heredado de Traderfre.
 */

class PeonMail {
    public static function send($to, $subject, $message) {
        $env = getEnvData();
        
        $from = $env['SMTP_FROM'] ?? 'no-reply@sixlan.com';
        $name = $env['SMTP_NAME'] ?? 'Peon Operations';
        
        // Headers básicos para envio desde Laragon/PHP
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: $name <$from>" . "\r\n";
        
        // En un entorno real con SMTP autenticado, se usaría PHPMailer.
        // Aquí usamos mail() como fallback táctico configurado en php.ini de Laragon
        // o simulamos el envío para el log.
        
        $logEntry = date('Y-m-d H:i:s') . " | SENDING EMAIL TO: $to | SUBJECT: $subject\n";
        file_put_contents(__DIR__ . '/backups/mail_log.txt', $logEntry, FILE_APPEND);
        
        return @mail($to, $subject, $message, $headers);
    }

    public static function sendLicense($email, $license) {
        $subject = "🗝️ TU LLAVE TÁCTICA: EL PEÓN PRO";
        $body = "
            <div style='background:#05050A; color:white; padding:40px; font-family:sans-serif; border-radius:20px;'>
                <h1 style='color:#FFB300; font-size:32px;'>MISIÓN ACTIVADA</h1>
                <p style='color:#666; text-transform:uppercase; letter-spacing:2px; font-size:10px;'>Licencia de Operación Pro Concedida</p>
                <div style='background:rgba(255,255,255,0.05); padding:20px; border-radius:15px; margin:30px 0; border:1px solid rgba(255,179,0,0.3); text-align:center;'>
                    <code style='color:#FFB300; font-size:24px; letter-spacing:4px;'>$license</code>
                </div>
                <p style='color:white; opacity:0.6;'>Copia esta llave e ingrésala en tu dashboard para desbloquear el potencial total de tus agentes.</p>
                <hr style='border:none; border-top:1px solid rgba(255,255,255,0.1); margin:30px 0;'>
                <p style='font-size:10px; color:#444;'>© 2026 SIXLAN STUDIO | PEÓN STRATEGIC OS</p>
            </div>
        ";
        return self::send($email, $subject, $body);
    }
}
?>
