<?php
$host = 'sixlan.com';
$user = 'no-reply@sixlan.com';
$pass = 'Sixlan.500380';

echo "PROBANDO CONEXIÓN TÁCTICA A $host...\n";

$conn = @ftp_ssl_connect($host);
if (!$conn) {
    echo "SSL falló, reintentando conexión estándar...\n";
    $conn = @ftp_connect($host);
}

if (!$conn) {
    $err = error_get_last();
    echo "FALLO CRÍTICO: No se puede conectar al host. " . ($err['message'] ?? '') . "\n";
    exit;
}

echo "CONEXIÓN ESTABLECIDA. AUTENTICANDO...\n";

if (@ftp_login($conn, $user, $pass)) {
    echo "¡AUTENTICACIÓN EXITOSA! Handshake completado.\n";
    ftp_pasv($conn, true);
    $list = ftp_nlist($conn, ".");
    echo "ARCHIVOS EN RAÍZ: " . count($list) . "\n";
    print_r($list);
    ftp_close($conn);
} else {
    $err = error_get_last();
    echo "FALLO DE AUTENTICACIÓN: Acceso denegado. " . ($err['message'] ?? '') . "\n";
    ftp_close($conn);
}
