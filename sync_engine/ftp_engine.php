<?php
/**
 * SYNC ENGINE | FTP Module v4.0 (Memory Optimized)
 * Gestión de deltas inteligentes para evitar saturación de RAM.
 */

function ftp_sync_recursive($config, $mode = 'push', $stream = false) {
    if (!$config) return ['status' => 'error', 'message' => 'Configuración de proyecto no válida.'];

    $host = $config['ftp']['host'] ?? '';
    $user = $config['ftp']['user'] ?? '';
    $pass = $config['ftp']['pass'] ?? '';
    $remoteRoot = $config['ftp']['root'] ?? '/';
    $localRoot = $config['dir'];
    $isConfirm = (isset($_GET['confirm']) && $_GET['confirm'] == '1');
    $skipFiles = isset($_GET['skip']) ? explode(',', $_GET['skip']) : [];

    if (empty($host) || empty($user) || empty($pass)) {
        return ['status' => 'request_config', 'type' => 'ftp', 'message' => 'Credenciales FTP requeridas.'];
    }

    $conn = @ftp_connect($host, 21, 15);
    if (!$conn || !@ftp_login($conn, $user, $pass)) {
        return ['status' => 'error', 'message' => 'Fallo de autenticación FTP.'];
    }

    ftp_pasv($conn, true);
    @ftp_chdir($conn, $remoteRoot);

    $toPush = [];
    $cacheKey = BACKUP_DIR . '/cache_' . md5($config['name']) . '.json';
    $lastState = [];
    $lastSyncTime = 0;

    if (file_exists($cacheKey)) {
        $cached = json_decode(file_get_contents($cacheKey), true);
        $lastState = $cached['state'] ?? [];
        $lastSyncTime = $cached['last_sync_time'] ?? 0;
    }

    // PROTOCOLO DE DELTA RECIENTE (v4.3 - Ventana Estricta de 12 Horas)
    // Se ignoran archivos más antiguos de 12 horas para máxima estabilidad de memoria.
    $sinceThreshold = time() - (12 * 3600);

    if ($mode === 'push') {
        if ($stream) send_progress(20, "ANÁLISIS DE DELTA RECIENTE...");
        
        // El escáner v4.0 ahora es ultra-rápido al saltar carpetas excluidas
        $currentState = scan_local_dir_recursive($localRoot, $config['exclude'], $sinceThreshold);
        
        foreach ($currentState as $file => $mtime) {
            if (!isset($lastState[$file]) || $lastState[$file] < $mtime) {
                $toPush[] = $file;
            }
        }
    }

    if (empty($toPush)) {
        if ($stream) send_progress(100, "SISTEMA SINCRONIZADO. NO HAY CAMBIOS RECIENTES.");
        ftp_close($conn);
        return ['status' => 'success', 'message' => 'Todo al día.'];
    }

    $total = count($toPush);
    
    // FASE DE AUTORIZACIÓN (v4.0)
    if (!$isConfirm) {
        // Guardamos el estado actual para la confirmación
        file_put_contents($cacheKey . '.tmp', json_encode(['files' => $toPush, 'state' => $currentState]));
        ftp_close($conn);
        
        if ($stream) {
            $previewFiles = array_slice(array_values($toPush), 0, 300);
            echo "data: " . json_encode([
                'status' => 'require_auth', 
                'files' => $previewFiles, 
                'total_files' => $total,
                'msg' => 'OBJETIVOS RECIENTES DETECTADOS: ' . $total . ' (Últimas 48h/Sincro)'
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
        }
        return ['status' => 'require_auth', 'files' => $toPush, 'total_files' => $total];
    }

    // FASE DE EJECUCIÓN
    $tmpCache = $cacheKey . '.tmp';
    if (file_exists($tmpCache)) {
        $cached = json_decode(file_get_contents($tmpCache), true);
        $currentState = $cached['state'] ?? [];
        @unlink($tmpCache);
    }

    $pushedCount = 0;
    $lastProgressTime = microtime(true);
    
    foreach ($toPush as $idx => $file) {
        if (in_array($file, $skipFiles)) continue;
        
        $localFilePath = $localRoot . '/' . $file;
        if (!file_exists($localFilePath)) continue;

        $pct = 20 + round((($idx + 1) / $total) * 75);
        if ($stream && (microtime(true) - $lastProgressTime > 0.8 || ($idx + 1) === $total)) {
            send_progress($pct, "SUBIENDO [" . ($idx+1) . "/$total]: " . basename($file));
            $lastProgressTime = microtime(true);
        }
        
        // Asegurar estructura remota
        $parts = explode('/', dirname($file));
        $curr = $remoteRoot;
        foreach ($parts as $part) {
            if (empty($part) || $part === '.') continue;
            $curr = rtrim($curr, '/') . '/' . $part;
            if (!@ftp_chdir($conn, $curr)) {
                if (@ftp_mkdir($conn, $curr)) @ftp_chdir($conn, $curr);
            }
        }
        @ftp_chdir($conn, $remoteRoot);
        
        $remoteFile = rtrim($remoteRoot, '/') . '/' . ltrim($file, '/');
        if (@ftp_put($conn, $remoteFile, $localFilePath, FTP_BINARY)) {
            $lastState[$file] = $currentState[$file] ?? filemtime($localFilePath);
            $pushedCount++;
        }
    }

    // PERSISTENCIA DE ESTADO
    file_put_contents($cacheKey, json_encode([
        'last_sync_time' => time(),
        'state' => $lastState
    ]));

    ftp_close($conn);
    
    if ($stream) {
        send_progress(100, "DESPLIEGUE FINALIZADO: $pushedCount ARCHIVOS ACTUALIZADOS.");
    }
    
    return ['status' => 'success', 'message' => "Sincronización completada: $pushedCount archivos."];
}
