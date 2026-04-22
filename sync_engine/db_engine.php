<?php
/**
 * SYNC ENGINE | Database Module
 * Local and remote database operations.
 */

function backup_local_database($config, $stream = false) {
    if ($stream) send_progress(50, "Extrayendo volcado local de base de datos...");
    
    $db = $config['db']['database'] ?? $config['db']['name'] ?? '';
    if (!$db) {
        if ($stream) {
            echo "data: " . json_encode(['status' => 'request_config', 'type' => 'db', 'msg' => 'ERROR: Sin DB en .env (Se requiere DB_DATABASE o DB_NAME)']) . "\n\n";
            @flush();
            exit;
        }
        return ['status' => 'error', 'message' => 'Sin DB en .env (Se requiere DB_DATABASE o DB_NAME)'];
    }
    
    if (!MYSQLDUMP_PATH || !file_exists(MYSQLDUMP_PATH)) {
        return ['status' => 'error', 'message' => 'Binario mysqldump no localizado. Verifique configuración.'];
    }
    
    $user = $config['db']['username'] ?? $config['db']['user'] ?? 'root';
    $pass = $config['db']['password'] ?? $config['db']['pass'] ?? '';
    $host = $config['db']['host'] ?? 'localhost';
    
    $tmpDir = sys_get_temp_dir();
    $cnfFile = $tmpDir . DIRECTORY_SEPARATOR . 'peon_db_' . uniqid() . '.cnf';
    $cnfContent = "[client]\nhost=\"$host\"\nuser=\"$user\"\npassword=\"$pass\"\n";
    file_put_contents($cnfFile, $cnfContent);
    
    $filename = BACKUP_DIR . "/db/{$config['name']}_" . date('Ymd_His') . ".sql";
    if ($stream) send_progress(70, "Generando archivo SQL seguro en: " . basename($filename));
    
    $filenameWin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename);
    $dumpPathWin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, MYSQLDUMP_PATH);
    $cnfPathWin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cnfFile);
    
    $errorFile = $tmpDir . DIRECTORY_SEPARATOR . 'peon_db_err_' . uniqid() . '.txt';
    $errorFileWin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $errorFile);
    
    $cmd = "\"$dumpPathWin\" --defaults-extra-file=\"$cnfPathWin\" " . escapeshellarg($db) . " > \"$filenameWin\" 2> \"$errorFileWin\"";
    exec($cmd, $output, $returnVar);
    
    $errorMsg = file_exists($errorFile) ? trim(file_get_contents($errorFile)) : '';
    @unlink($cnfFile);
    @unlink($errorFile);
    
    if ($returnVar !== 0 && ($user !== 'root' || $host !== 'localhost')) {
        if ($stream) send_progress(85, "Credenciales del .env rechazadas. Iniciando protocolo de FALLBACK (Root)...");
        $fallbackFilename = BACKUP_DIR . "/db/{$config['name']}_" . date('Ymd_His') . "_ROOT.sql";
        $fallbackWin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fallbackFilename);
        $cmdFallback = "\"$dumpPathWin\" -h localhost -u root " . escapeshellarg($db) . " > \"$fallbackWin\" 2> \"$errorFileWin\"";
        exec($cmdFallback, $fOutput, $fReturnVar);
        if ($fReturnVar === 0) {
            if ($stream) send_progress(100, "Protocolo de base de datos completado vía Fallback.");
            return ['status' => 'success', 'message' => "Backup generado vía Fallback Root: " . basename($fallbackFilename)];
        }
    }
    
    if ($returnVar !== 0) {
        if ($stream) send_progress(100, "ERROR_PROTO: Falló el volcado.");
        if (file_exists($filename)) @unlink($filename);
        return ['status' => 'error', 'message' => "Error en mysqldump: " . ($errorMsg ?: "Código $returnVar")];
    }
    
    if ($stream) send_progress(100, "Protocolo de base de datos completado.");
    return ['status' => 'success', 'message' => "Backup SQL generado: " . basename($filename)];
}

function import_backup_db($projectName, $filename, $stream = false) {
    $config = get_project_full_config($projectName);
    if (!$config) return ['status' => 'error', 'message' => 'Configuración no localizada'];
    
    $db = $config['db']['database'] ?? $config['db']['name'] ?? '';
    if (!$db) return ['status' => 'error', 'message' => 'Base de datos no definida'];
    
    $filePath = BACKUP_DIR . '/db/' . $filename;
    if (!file_exists($filePath)) return ['status' => 'error', 'message' => 'Archivo SQL no localizado.'];

    if ($stream) send_progress(90, "Inyectando backup SQL en base de datos local...");
    
    $user = $config['db']['username'] ?? 'root';
    $pass = $config['db']['password'] ?? '';
    $host = $config['db']['host'] ?? 'localhost';
    
    $tmpDir = sys_get_temp_dir();
    $cnfFile = $tmpDir . DIRECTORY_SEPARATOR . 'peon_db_imp_' . uniqid() . '.cnf';
    $cnfContent = "[client]\nhost=\"$host\"\nuser=\"$user\"\npassword=\"$pass\"\n";
    file_put_contents($cnfFile, $cnfContent);

    $mysqlPathWin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, MYSQL_PATH);
    $cnfPathWin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cnfFile);
    $filePathWin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
    
    $cmd = "\"$mysqlPathWin\" --defaults-extra-file=\"$cnfPathWin\" " . escapeshellarg($db) . " < \"$filePathWin\" 2>&1";
    exec($cmd, $output, $returnVar);

    @unlink($cnfFile);

    if ($stream) send_progress(100, "Restauración completada.");
    return ($returnVar === 0) ? ['status' => 'success', 'message' => "Base de datos restaurada con éxito."] : ['status' => 'error', 'message' => "Error en restauración mysql: " . implode("\n", $output)];
}

function ftp_pull_remote_db($config, $stream = false) {
    if (empty($config['ftp']['host']) || $config['ftp']['host'] === 'LOCAL_ONLY') {
        if ($stream) {
            echo "data: " . json_encode(['status' => 'request_config', 'type' => 'ftp', 'msg' => 'ERROR: Configuración FTP no detectada.']) . "\n\n";
            @flush();
            exit;
        }
        return ['status' => 'error', 'message' => 'Configuración FTP no detectada.'];
    }
    
    if ($stream) send_progress(10, "ESTABLECIENDO PROTOCOLO DE CONEXIÓN FTP...");
    $ftpHost = $config['ftp']['host'];
    $conn = null;
    if (function_exists('ftp_ssl_connect')) $conn = @ftp_ssl_connect($ftpHost);
    if (!$conn) $conn = @ftp_connect($ftpHost);
    
    if (!$conn) return ['status' => 'error', 'message' => 'No se pudo conectar al host táctico.'];
    
    if (!@ftp_login($conn, $config['ftp']['user'], $config['ftp']['pass'])) {
        ftp_close($conn);
        return ['status' => 'error', 'message' => 'Autenticación fallida.'];
    }
    ftp_pasv($conn, true);

    $remoteDir = $config['ftp']['root'] ?? '/';
    $dbDir = BACKUP_DIR . '/db';
    if (!is_dir($dbDir)) @mkdir($dbDir, 0777, true);

    if ($stream) send_progress(20, "ESCANEANDO SERVIDOR EN BUSCA DE BACKUPS SQL...");
    $nlist = @ftp_nlist($conn, $remoteDir);
    if (!$nlist) {
        ftp_close($conn);
        return ['status' => 'error', 'message' => 'No se pudieron listar archivos remotos.'];
    }

    $sqlFiles = [];
    foreach ($nlist as $item) {
        $name = basename($item);
        if (pathinfo($name, PATHINFO_EXTENSION) === 'sql') {
            $mtime = ftp_mdtm($conn, $remoteDir . '/' . $name);
            $sqlFiles[] = ['name' => $name, 'mtime' => ($mtime != -1 ? $mtime : 0)];
        }
    }

    if (empty($sqlFiles)) {
        ftp_close($conn);
        return ['status' => 'error', 'message' => 'No se encontraron archivos .sql.'];
    }

    usort($sqlFiles, function($a, $b) { return $b['mtime'] <=> $a['mtime']; });
    $targetFile = $sqlFiles[0]['name'];

    if ($stream) send_progress(40, "BACKUP DETECTADO: $targetFile. INICIANDO DESCARGA...");
    $localPath = $dbDir . '/' . $targetFile;
    if (@ftp_get($conn, $localPath, $remoteDir . '/' . $targetFile, FTP_BINARY)) {
        $importResult = import_backup_db($config['name'], $targetFile, $stream);
        ftp_close($conn);
        return ['status' => 'success', 'message' => "DB sincronizada e importada: $targetFile."];
    }

    ftp_close($conn);
    return ['status' => 'error', 'message' => "Fallo al descargar el archivo SQL."];
}
