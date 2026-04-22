<?php
/**
 * SYNC ENGINE | Core Logic & Utilities
 * Shared helpers for the synchronization ecosystem.
 */

/**
 * Envío de fragmento de progreso (Streaming)
 */
function send_progress($pct, $msg) {
    echo "data: " . json_encode([
        'progress' => $pct,
        'status' => 'processing',
        'msg' => $msg
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    @flush();
}

/**
 * Valida la licencia contra el servidor central de Sixlan
 */
function validate_remote_license($isStream = false) {
    if ($isStream) send_progress(10, "Verificando autorización táctica con Sixlan Hub...");
    
    $env = getEnvData();
    $key = $env['SIXLAN_LICENSE'] ?? '';
    
    if (empty($key)) {
        return ['status' => 'error', 'message' => 'Llave táctica ausente. El sistema requiere licencia activa.'];
    }

    $url = SIXLAN_HUB_URL . "?action=verify&key=" . urlencode($key) . "&domain=" . urlencode($_SERVER['HTTP_HOST'] ?? 'localhost');
    
    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return ['status' => 'success', 'message' => 'Servidor de licencias offline. Operando en modo de emergencia.'];
    }

    $data = json_decode($response, true);
    if (($data['status'] ?? '') === 'success') {
        if ($isStream) send_progress(15, "Autorización confirmada. Tier: " . ($data['data']['license_tier'] ?? 'PRO'));
        return ['status' => 'success', 'data' => $data['data']];
    }

    return ['status' => 'error', 'message' => $data['message'] ?? 'Firma digital no válida.'];
}

function get_project_full_config($projectName) {
    $dir = PROJECTS_BASE_DIR . DIRECTORY_SEPARATOR . $projectName;
    if (!is_dir($dir)) {
        $dir = PROJECTS_BASE_DIR . DIRECTORY_SEPARATOR . strtolower($projectName);
        if (!is_dir($dir)) return null;
    }

    $config = ['name' => strtoupper($projectName), 'dir' => $dir, 'db' => [], 'ftp' => [], 'exclude' => GLOBAL_EXCLUDE];
    $envFile = $dir . DIRECTORY_SEPARATOR . '.env';

    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k); $v = trim($v, " \t\n\r\0\x0B\"'");
            
            $val = PeonVault::isEncrypted($v) ? PeonVault::decrypt($v) : $v;

            if (stripos($k, 'DB_') === 0) $config['db'][strtolower(substr($k, 3))] = $val;
            if (stripos($k, 'FTP_') === 0) $config['ftp'][strtolower(substr($k, 4))] = $val;
            if (in_array(strtolower($k), ['host', 'user', 'pass', 'root'])) $config['ftp'][strtolower($k)] = $val;
            
            if ($k === 'PROJ_EXCLUDE' || $k === 'PEON_EXCLUDE') {
                $customExcludes = array_map('trim', explode(',', $val));
                $config['exclude'] = array_unique(array_merge($config['exclude'], $customExcludes));
            }
        }
    }

    // FALLBACK: Consultar el .env global de Peon para configuraciones PROJ_{ID}_...
    $id = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $projectName));
    $envGlobal = getEnvData();

    $dbFields = ['DB_HOST' => 'host', 'DB_NAME' => 'database', 'DB_USER' => 'username', 'DB_PASS' => 'password', 'DATABASE' => 'database', 'NAME' => 'database', 'USER' => 'username', 'PASS' => 'password', 'HOST' => 'host'];
    foreach ($dbFields as $suffix => $configKey) {
        $key = "PROJ_{$id}_{$suffix}";
        if (isset($envGlobal[$key]) && empty($config['db'][$configKey])) {
            $config['db'][$configKey] = PeonVault::isEncrypted($envGlobal[$key]) ? PeonVault::decrypt($envGlobal[$key]) : $envGlobal[$key];
        }
    }
    
    $ftpFields = ['FTP_HOST' => 'host', 'FTP_USER' => 'user', 'FTP_PASS' => 'pass', 'FTP_ROOT' => 'root', 'HOST' => 'host', 'USER' => 'user', 'PASS' => 'pass', 'ROOT' => 'root'];
    foreach ($ftpFields as $suffix => $configKey) {
        $key = "PROJ_{$id}_{$suffix}";
        if (isset($envGlobal[$key]) && empty($config['ftp'][$configKey])) {
            $config['ftp'][$configKey] = PeonVault::isEncrypted($envGlobal[$key]) ? PeonVault::decrypt($envGlobal[$key]) : $envGlobal[$key];
        }
    }

    if (empty($config['db']['database'])) $config['db']['database'] = strtolower($projectName);
    
    $peonIgnore = $dir . DIRECTORY_SEPARATOR . '.peonignore';
    if (file_exists($peonIgnore)) {
        $extra = file($peonIgnore, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($extra) $config['exclude'] = array_unique(array_merge($config['exclude'], $extra));
    }

    return $config;
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function array_copy_recursive($src, $dst, $exclude = []) {
    if (!is_dir($src)) return;
    $dir = opendir($src);
    @mkdir($dst, 0777, true);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (in_array($file, $exclude)) continue;
            if (is_dir($src . '/' . $file)) {
                array_copy_recursive($src . '/' . $file, $dst . '/' . $file, $exclude);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function should_exclude($name, $path, $excludeList) {
    foreach ($excludeList as $ex) {
        if (empty($ex)) continue;
        if ($name === $ex || strpos($path, $ex) !== false) return true;
    }
    return false;
}

/**
 * Persiste la configuración táctica de un proyecto en el .env global
 */
function save_project_tactical_config($projectName, $data) {
    $envPath = __DIR__ . '/../.env';
    $id = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $projectName));
    
    if (!file_exists($envPath)) @file_put_contents($envPath, "");
    
    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    $newLines = [];
    $foundKeys = [];
    
    $map = [
        'ftp_host' => "PROJ_{$id}_FTP_HOST",
        'ftp_user' => "PROJ_{$id}_FTP_USER",
        'ftp_pass' => "PROJ_{$id}_FTP_PASS",
        'ftp_root' => "PROJ_{$id}_FTP_ROOT",
        'db_host'  => "PROJ_{$id}_DB_HOST",
        'db_name'  => "PROJ_{$id}_DB_NAME",
        'db_user'  => "PROJ_{$id}_DB_USER",
        'db_pass'  => "PROJ_{$id}_DB_PASS",
    ];

    // Procesar líneas existentes
    foreach ($lines as $line) {
        $matched = false;
        foreach ($map as $dataKey => $envKey) {
            if (strpos(trim($line), $envKey . '=') === 0) {
                $val = $data[$dataKey] ?? '';
                // Cifrar contraseñas
                if (strpos($envKey, '_PASS') !== false && !empty($val)) {
                    $val = PeonVault::encrypt($val);
                }
                $newLines[] = "{$envKey}=\"{$val}\"";
                $foundKeys[] = $envKey;
                $matched = true;
                break;
            }
        }
        if (!$matched) $newLines[] = $line;
    }

    // Añadir llaves nuevas
    foreach ($map as $dataKey => $envKey) {
        if (!in_array($envKey, $foundKeys)) {
            $val = $data[$dataKey] ?? '';
            if (strpos($envKey, '_PASS') !== false && !empty($val)) {
                $val = PeonVault::encrypt($val);
            }
            $newLines[] = "{$envKey}=\"{$val}\"";
        }
    }

    if (@file_put_contents($envPath, implode("\n", $newLines))) {
        return ['status' => 'success', 'message' => 'Configuración táctica guardada.'];
    }
    
    return ['status' => 'error', 'message' => 'No se pudo escribir en el archivo .env'];
}
