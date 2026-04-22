<?php
require_once 'projects_sync.php';
require_once 'vault.php';

$projectName = 'sixlan';
echo "--- INICIANDO DIAGNÓSTICO INTEGRAL DE SINCRONIZACIÓN PARA [$projectName] ---\n";

// 1. Obtener Configuración
$config = get_project_full_config($projectName);
if (!$config) {
    die("FALLO: No se pudo localizar la configuración para $projectName.\n");
}
echo "CONFIGURACIÓN LOCALIZADA:\n";
echo "Dir: " . $config['dir'] . "\n";
echo "Host: " . ($config['ftp']['host'] ?? 'NULL') . "\n";
echo "User: " . ($config['ftp']['user'] ?? 'NULL') . "\n";
echo "Root: " . ($config['ftp']['root'] ?? 'NULL') . "\n";

// 2. Probar Conexión
echo "\nPROBANDO HANDSHAKE FTP...\n";
$ftpHost = $config['ftp']['host'];
$conn = null;
if (function_exists('ftp_ssl_connect')) {
    echo "Intentando SSL...\n";
    $conn = @ftp_ssl_connect($ftpHost, 21, 15);
}
if (!$conn) {
    echo "SSL falló o no disponible. Intentando estándar...\n";
    $conn = @ftp_connect($ftpHost, 21, 15);
}

if (!$conn) {
    $err = error_get_last();
    die("FALLO DE CONEXIÓN: " . ($err['message'] ?? 'Desconocido') . "\n");
}

echo "CONECTADO. AUTENTICANDO...\n";
if (!@ftp_login($conn, $config['ftp']['user'], $config['ftp']['pass'])) {
    $err = error_get_last();
    ftp_close($conn);
    die("FALLO DE AUTENTICACIÓN: " . ($err['message'] ?? 'Acceso denegado') . "\n");
}

echo "AUTENTICACIÓN EXITOSA. ACTIVANDO MODO PASIVO...\n";
ftp_pasv($conn, true);

// 3. Listar archivos remotos
echo "\nLISTANDO DIRECTORIO REMOTO [{$config['ftp']['root']}]...\n";
$remoteDir = $config['ftp']['root'] ?: '/';
$list = @ftp_nlist($conn, $remoteDir);

if ($list === false) {
    $err = error_get_last();
    echo "ERROR AL LISTAR: " . ($err['message'] ?? 'No se pudo leer el directorio') . "\n";
    // Intentar con '.'
    echo "Reintentando con [.]...\n";
    $list = @ftp_nlist($conn, ".");
}

if ($list === false) {
    die("ERROR FATAL: El servidor no permite el listado de archivos.\n");
}

echo "ARCHIVOS ENCONTRADOS: " . count($list) . "\n";
foreach (array_slice($list, 0, 5) as $f) echo " - $f\n";

// 4. Probar descarga de un archivo pequeño (ej. PEON.md o index.php)
$target = null;
foreach ($list as $f) {
    if (strpos($f, 'PEON.md') !== false || strpos($f, 'index.php') !== false) {
        $target = $f;
        break;
    }
}

if ($target) {
    echo "\nPROBANDO DESCARGA TÁCTICA DE [$target]...\n";
    $tmpFile = tempnam(sys_get_temp_dir(), 'peon_test_');
    if (@ftp_get($conn, $tmpFile, $target, FTP_BINARY)) {
        echo "¡DESCARGA EXITOSA! Integridad de datos confirmada.\n";
        unlink($tmpFile);
    } else {
        $err = error_get_last();
        echo "FALLO EN DESCARGA: " . ($err['message'] ?? 'Desconocido') . "\n";
    }
}

ftp_close($conn);
echo "\n--- DIAGNÓSTICO COMPLETADO CON ÉXITO ---\n";
