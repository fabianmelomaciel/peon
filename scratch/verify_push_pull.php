<?php
require_once 'projects_sync.php';
$projectName = 'sixlan';
$config = get_project_full_config($projectName);

echo "INICIANDO VERIFICACIÓN DE MOVIMIENTO DE DATOS (PUSH/PULL)...\n";

$ftpHost = $config['ftp']['host'];
$conn = @ftp_ssl_connect($ftpHost);
if (!$conn) $conn = @ftp_connect($ftpHost);

if (!@ftp_login($conn, $config['ftp']['user'], $config['ftp']['pass'])) {
    die("ERROR: Login fallido.\n");
}
ftp_pasv($conn, true);

$testFile = 'PEON_SYNC_TEST.txt';
$localPath = $config['dir'] . '/' . $testFile;
$remotePath = ($config['ftp']['root'] ?: '/') . $testFile;

// 1. Crear archivo local
file_put_contents($localPath, "Tactical Sync Test: " . date('Y-m-d H:i:s'));
echo "Archivo local creado: $testFile\n";

// 2. Probar PUSH
echo "Subiendo (PUSH) a servidor remorto...\n";
if (@ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
    echo "¡PUSH EXITOSO!\n";
} else {
    echo "FALLO EN PUSH.\n";
}

// 3. Modificar archivo local (simular cambio)
sleep(2);
file_put_contents($localPath, "Modified local: " . date('Y-m-d H:i:s'));

// 4. Probar PULL (Bajar el original del servidor)
echo "Descargando (PULL) desde servidor...\n";
if (@ftp_get($conn, $localPath, $remotePath, FTP_BINARY)) {
    echo "¡PULL EXITOSO!\n";
    echo "Contenido recuperado: " . file_get_contents($localPath) . "\n";
} else {
    echo "FALLO EN PULL.\n";
}

// 5. Limpiar
@ftp_delete($conn, $remotePath);
@unlink($localPath);
ftp_close($conn);

echo "\n--- VERIFICACIÓN FINALIZADA --- \n";
