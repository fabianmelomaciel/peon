<?php
/**
 * Motor de Gestión de Proyectos Estratégicos v1.5 (Zero-Latency Handshake)
 * Gestión de copias, borrado seguro y optimización de monitorización.
 */

// 1. HANDSHAKE INMEDIATO (Prioridad Crítica)
    $action = $_GET['action'] ?? null;
    $isStream = ($_GET['stream'] ?? '0') == '1';

    if ($action && $isStream) {
        @set_time_limit(0);
        @ignore_user_abort(true);
        header('Content-Type: text/event-stream');
        header('Content-Encoding: identity');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('X-Content-Type-Options: nosniff');
        
        while (ob_get_level() > 0) ob_end_clean();
        ob_implicit_flush(true);
    
    // Acolchado masivo (8KB) para romper el búfer de navegadores modernos (Chrome/Edge)
    echo str_repeat(' ', 8192) . "\n";
    echo "data: " . json_encode(['progress' => 5, 'msg' => 'HANDSHAKE SEGURO ESTABLECIDO. CANAL OPERATIVO.']) . "\n\n";
    @flush();
}

// 2. CARGA DE DEPENDENCIAS (Después de la conexión)
require_once 'core.php'; // Incluye config.php, env_discovery.php, vault.php, data_sync.php

$diag = PeonEnv::getDiagnostics();

// Detección dinámica de ruta de proyectos
define('PROJECTS_BASE_DIR', !empty($diag['root']) ? $diag['root'] : (__DIR__ . '/..'));
const BACKUP_DIR = __DIR__ . '/backups';

// Resolución dinámica de binarios de MySQL
define('MYSQLDUMP_PATH', $diag['mysqldump_bin']);
define('MYSQL_PATH', $diag['mysql_bin']);


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

    // Handshake remoto vía cURL (puedes usar file_get_contents si no hay cURL)
    $url = SIXLAN_HUB_URL . "?action=verify&key=" . urlencode($key) . "&domain=" . urlencode($_SERVER['HTTP_HOST'] ?? 'localhost');
    
    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'ignore_errors' => true]
    ]);
    
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        // Fallback: Si el servidor de licencias está offline, permitimos modo offline limitado
        // o bloqueamos según tu preferencia. Aquí seremos permisivos por ahora.
        return ['status' => 'success', 'message' => 'Servidor de licencias offline. Operando en modo de emergencia.'];
    }

    $data = json_decode($response, true);
    if (($data['status'] ?? '') === 'success') {
        if ($isStream) send_progress(15, "Autorización confirmada. Tier: " . ($data['data']['license_tier'] ?? 'PRO'));
        return ['status' => 'success', 'data' => $data['data']];
    }

    return ['status' => 'error', 'message' => $data['message'] ?? 'Firma digital no válida.'];
}

const GLOBAL_EXCLUDE = ['.git', '.antigravity', '.agents', '.agent', 'node_modules', 'BACKUP', 'tmp', 'peon'];

    $isStream = ($_GET['stream'] ?? '0') == '1';

    if (($action ?? null) && $isStream) {
        send_progress(6, "Motor sincronizado y cargado.");
    }

// Validación de Seguridad proactiva
if (!function_exists('openssl_encrypt')) {
    $msg = '☢️ ADVERTENCIA: Extensión OpenSSL no detectada en PHP. El Cofre está OFFLINE.';
    $instr = 'INSTRUCCIÓN: Activa "openssl" en Laragon (Menú -> PHP -> Extensions) y reinicia.';
    if ($is_stream ?? false) {
        send_progress(8, $msg);
        send_progress(9, $instr);
    } else {
        // En caso de petición JSON, no bloqueamos pero avisamos en el log si fuera posible
    }
}

if (!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR, 0777, true);
if (!is_dir(BACKUP_DIR . '/db')) @mkdir(BACKUP_DIR . '/db', 0777, true);
if (!is_dir(BACKUP_DIR . '/sites')) @mkdir(BACKUP_DIR . '/sites', 0777, true);

/**
 * Handler para peticiones API
 */
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $isStream = isset($_GET['stream']) && $_GET['stream'] == '1';

    // Limpieza proactiva de búfer para evitar que errores accidentales rompan el JSON
    while (ob_get_level() > 0) ob_end_clean();

    if (!$isStream) {
        ob_start();
        header('Content-Type: application/json');
    }

    $response = ['status' => 'error', 'message' => 'Acción desconocida'];

    switch ($action) {
        case 'list':
            $response = ['status' => 'success', 'projects' => get_managed_projects()];
            break;
        case 'verify_license':
            $key = $_GET['key'] ?? '';
            $resData = ['status' => 'error', 'message' => 'Servidor de licencias inalcanzable.'];
            
            if (defined('SIXLAN_HUB_URL')) {
                $url = SIXLAN_HUB_URL . "?action=verify&key=" . urlencode($key) . "&domain=" . urlencode($_SERVER['HTTP_HOST'] ?? 'localhost');
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
                $apiRes = @file_get_contents($url, false, $ctx);
                if ($apiRes) $resData = json_decode($apiRes, true);
            }
            
            if (isset($resData['status']) && $resData['status'] === 'success') {
                $envPath = __DIR__ . '/.env';
                $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
                if (strpos($envContent, 'SIXLAN_LICENSE=') !== false) {
                    $envContent = preg_replace('/SIXLAN_LICENSE=".*?"/', 'SIXLAN_LICENSE="' . $key . '"', $envContent);
                } else {
                    $envContent .= "\nSIXLAN_LICENSE=\"{$key}\"\n";
                }
                file_put_contents($envPath, $envContent);
                $response = $resData;
                audit_log('LICENSE_VERIFY', 'SYSTEM', 'SUCCESS', "Key authenticated: " . substr($key, 0, 12) . "...");
            } else {
                $response = $resData ?? ['status' => 'error', 'message' => 'Firma digital no reconocida.'];
                audit_log('LICENSE_VERIFY_FAIL', 'SYSTEM', 'ERROR', "Invalid key: " . substr($key, 0, 12) . "...");
            }
            break;
        case 'install_intelligence_pack':
            $response = install_intelligence_pack($isStream);
            break;
        case 'verify_license_purchase':
            $email = $_GET['email'] ?? '';
            $resData = ['status' => 'error', 'message' => 'Servidor de licencias inalcanzable.'];
            
            if (defined('SIXLAN_HUB_URL')) {
                $url = SIXLAN_HUB_URL . "?action=purchase&email=" . urlencode($email);
                $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 5, 'ignore_errors' => true]]);
                $apiRes = @file_get_contents($url, false, $ctx);
                if ($apiRes) $resData = json_decode($apiRes, true);
            }
            
            if (isset($resData['status']) && $resData['status'] === 'success') {
                audit_log('PURCHASE', 'SIXLAN_HUB', 'SUCCESS', "New license generated for $email");
            } else {
                audit_log('PURCHASE_FAILED', 'SIXLAN_HUB', 'ERROR', $resData['message'] ?? 'Hub unreachable');
            }
            
            $response = $resData;
            break;
        case 'shield_all':
            shield_env_file(__DIR__ . '/.env');
            $base = PROJECTS_BASE_DIR;
            $items = @array_diff(scandir($base), ['.', '..']);
            if ($items) {
                foreach ($items as $item) {
                    $path = $base . '/' . $item;
                    if (is_dir($path) && file_exists($path . '/.env')) shield_env_file($path . '/.env');
                }
            }
            $response = ['status' => 'success', 'message' => 'PROTOCOLO DE BLINDAJE GLOBAL COMPLETADO. Todos los secretos han sido encriptados.'];
            break;
        case 'scan':
            $found = scan_projects($isStream);
            $response = ['status' => 'success', 'found' => $found];
            break;
        case 'get_config':
            $project = $_GET['project'] ?? '';
            $response = ['status' => 'success', 'config' => get_project_full_config($project)];
            break;
        case 'list_backups':
            if (!is_system_authorized($env)) {
                $response = ['status' => 'error', 'message' => 'SISTEMA BLOQUEADO. Licencia requerida.'];
                break;
            }
            $project = $_GET['project'] ?? '';
            $response = list_project_backups($project);
            break;
        case 'db_local':
            $project = $_GET['project'] ?? '';
            $config = get_project_full_config($project);
            
            // Suplementar con manual si viene en el request
            if (isset($_GET['db_name'])) {
                $config['db']['database'] = $_GET['db_name'];
                $config['db']['username'] = $_GET['db_user'] ?? 'root';
                $config['db']['password'] = $_GET['db_pass'] ?? '';
                $config['db']['host'] = $_GET['db_host'] ?? 'localhost';
            }
            
            $response = backup_local_database($config, $isStream);
            break;
        case 'pull':
            $project = $_GET['project'] ?? '';
            $config = get_project_full_config($project);
            $response = ftp_sync_recursive($config, 'pull', $isStream);
            break;
        case 'push':
            $project = $_GET['project'] ?? '';
            $config = get_project_full_config($project);
            $response = ftp_sync_recursive($config, 'push', $isStream);
            break;
        case 'backup_full':
            $project = $_GET['project'] ?? '';
            $config = get_project_full_config($project);
            $response = ftp_sync_recursive($config, 'backup', $isStream);
            break;
        case 'db_remote_pull':
            if (!is_system_authorized($env)) {
                $response = ['status' => 'error', 'message' => 'SISTEMA BLOQUEADO. Licencia requerida.'];
                break;
            }
            $project = $_GET['project'] ?? '';
            $config = get_project_full_config($project);
            $response = ftp_pull_remote_db($config, $isStream);
            break;
        case 'delete_backup':
            $file = $_GET['file'] ?? '';
            $type = $_GET['type'] ?? '';
            $response = delete_backup_safe($file, $type);
            break;
        case 'download_backup':
            $file = $_GET['file'] ?? '';
            $type = $_GET['type'] ?? '';
            download_backup_safe($file, $type);
            break;

        case 'push_preview':
            $project = $_GET['project'] ?? '';
            $config = get_project_full_config($project);
            $response = ftp_sync_preview($config);
            break;

        case 'deploy_github':
            $peonDir = __DIR__;
            // Verificar si es repositorio
            if (!is_dir($peonDir . DIRECTORY_SEPARATOR . '.git')) {
                exec("cd \"$peonDir\" && git init 2>&1");
            }
            
            // Forzar añadido de la carpeta code respetando gitignore modificado
            exec("cd \"$peonDir\" && git add -u code/ 2>&1");
            exec("cd \"$peonDir\" && git add code/ 2>&1");
            
            // Commit táctico
            exec("cd \"$peonDir\" && git commit -m \"Auto-deploy Open Source Core via Sixlan OS\" 2>&1", $outCommit, $codeCommit);
            
            // Intentar push al remote configurado
            exec("cd \"$peonDir\" && git push origin main 2>&1", $outPush, $codePush);
            if ($codePush === 0) {
                $response = ['status' => 'success', 'message' => 'Despliegue a GitHub completado con éxito desde el Dashboard.'];
            } else {
                $response = ['status' => 'success', 'message' => 'Commit local creado para /code/. (La subida a la nube requiere asociar origin manualmente).'];
            }
            break;
        case 'op':
            $type = $_GET['type'] ?? '';
            $project = $_GET['project'] ?? '';
            $response = execute_project_operation($project, $type, $isStream);
            break;
        case 'save_excludes':
            $project = $_GET['project'] ?? '';
            $excludes = $_POST['excludes'] ?? '';
            $response = save_project_excludes($project, $excludes);
            break;
        case 'list_backups':
            $project = $_GET['project'] ?? '';
            $response = list_project_backups($project);
            break;
        case 'import_backup':
            $project = $_GET['project'] ?? '';
            $file = $_GET['file'] ?? '';
            $response = import_backup_db($project, $file, $isStream);
            break;
        case 'uninstall':
            $response = perform_purge_cleanup();
            break;
        case 'test_ftp':
            $host = $_GET['host'] ?? '';
            $user = $_GET['user'] ?? '';
            $pass = $_GET['pass'] ?? '';
            
            $conn = null;
            if (function_exists('ftp_ssl_connect')) $conn = @ftp_ssl_connect($host);
            if (!$conn) $conn = @ftp_connect($host);
            
            if (!$conn) {
                $err = error_get_last();
                $response = ['status' => 'error', 'message' => 'No se pudo conectar al host. ' . ($err['message'] ?? '')];
            } else {
                if (@ftp_login($conn, $user, $pass)) {
                    $response = ['status' => 'success', 'message' => 'Conexión establecida correctamente.'];
                    ftp_close($conn);
                } else {
                    $err = error_get_last();
                    ftp_close($conn);
                    $response = ['status' => 'error', 'message' => 'Autenticación fallida. ' . ($err['message'] ?? '')];
                }
            }
            break;
        case 'test_db':
            $host = $_GET['host'] ?? 'localhost';
            $user = $_GET['user'] ?? 'root';
            $pass = $_GET['pass'] ?? '';
            $db   = $_GET['db'] ?? '';
            
            try {
                $conn = @new mysqli($host, $user, $pass, $db);
                if ($conn->connect_error) {
                    $response = ['status' => 'error', 'message' => 'Fallo de conexión DB: ' . $conn->connect_error];
                } else {
                    $response = ['status' => 'success', 'message' => 'Conexión a Base de Datos establecida correctamente.'];
                    $conn->close();
                }
            } catch (Exception $e) {
                $response = ['status' => 'error', 'message' => 'Excepción de sistema: ' . $e->getMessage()];
            }
            break;
        case 'save_project_config':
            $project = $_GET['project'] ?? '';
            $host = $_GET['host'] ?? 'LOCAL_ONLY';
            $user = $_GET['user'] ?? '';
            $pass = $_GET['pass'] ?? '';
            $root = $_GET['root'] ?? '/';
            $excludes = $_GET['excludes'] ?? '';
            
            // Campos de Base de Datos
            $db_name = $_GET['db_name'] ?? '';
            $db_user = $_GET['db_user'] ?? '';
            $db_pass = $_GET['db_pass'] ?? '';
            $db_host = $_GET['db_host'] ?? '';
            
            if (empty($project)) {
                $response = ['status' => 'error', 'message' => 'Proyecto no especificado.'];
            } else {
                $payload = [
                    'name' => $project,
                    'host' => $host,
                    'user' => $user,
                    'pass' => $pass,
                    'root' => $root,
                    'excludes' => $excludes,
                    'db_name' => $db_name,
                    'db_user' => $db_user,
                    'db_pass' => $db_pass,
                    'db_host' => $db_host
                ];
                
                $existing = get_managed_projects();
                $updated = false;
                foreach ($existing as &$p) {
                    if (strcasecmp($p['name'] ?? '', $project) === 0) {
                        $p = array_merge($p, $payload);
                        $updated = true;
                        break;
                    }
                }
                if (!$updated) {
                    $existing[] = $payload;
                }
                
                save_projects_batch($existing);
                
                // Asegurar que las exclusiones también se guarden localmente
                if (isset($excludes)) {
                    save_project_excludes($project, $excludes);
                }
                
                $response = ['status' => 'success', 'message' => 'Configuración táctica guardada y blindada.'];
            }
            break;
        case 'get_discovery':
            $response = ['status' => 'success', 'data' => PeonEnv::getDiagnostics()];
            break;
    }

    if (!$isStream) {
        ob_end_clean();
        echo json_encode($response);
    } else {
        $finalStatus = $response['status'] ?? 'success';
        $finalMsg = $response['message'] ?? 'OPERACIÓN COMPLETADA.';
        echo "data: " . json_encode([
            'progress' => ($finalStatus === 'success' ? 100 : 0),
            'status' => $finalStatus,
            'message' => $finalMsg,
            'msg' => ($finalStatus === 'error' ? "☣️ ERROR TÁCTICO: " : "") . $finalMsg
        ]) . "\n\n";
    }
    exit;
}


/**
 * Descarga segura de backups
 */
function delete_backup_safe($filename, $type) {
    $base = BACKUP_DIR . '/' . ($type === 'db' ? 'db' : 'sites');
    $path = realpath($base . '/' . $filename);
    $baseReal = realpath($base);
    
    if (!$path || !$baseReal || strpos($path, $baseReal) !== 0 || !file_exists($path)) {
        return ['status' => 'error', 'message' => 'Archivo no detectado. Acceso denegado.'];
    }

    if (is_dir($path)) {
        delete_dir_recursive($path);
    } else {
        unlink($path);
    }
    
    return ['status' => 'success', 'message' => 'DEPÓSITO ELIMINADO CON ÉXITO.'];
}

function delete_dir_recursive($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delete_dir_recursive("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function download_backup_safe($filename, $type) {
    if ($type !== 'db') {
        echo 'Descarga no soportada para este tipo.';
        exit;
    }
    $base = BACKUP_DIR . '/db/';
    $path = realpath($base . $filename);
    
    if (!$path || strpos($path, realpath($base)) !== 0 || !file_exists($path)) {
        header("HTTP/1.0 404 Not Found");
        echo 'Archivo no encontrado o acceso denegado.';
        exit;
    }
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($path).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function get_dir_size($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Envío de fragmento de progreso (Streaming)
 */
function send_progress($pct, $msg, $status = 'processing') {
    $payload = json_encode(['progress' => $pct, 'msg' => $msg, 'status' => $status], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    echo "data: " . $payload . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    @flush();
}

/**
 * Ejecuta operaciones integradas
 */
function execute_project_operation($projectName, $type, $stream = false) {
    if ($stream) send_progress(5, "Iniciando protocolo para $projectName...");
    $config = get_project_full_config($projectName);
    if (!$config) return ['status' => 'error', 'message' => 'Configuración no localizada'];
    
    // Rutas que no requieren FTP
    if ($type === 'db_local' || $type === 'db_backup') {
        return backup_local_database($config, $stream);
    }
    
    // Rutas FTP
    return ftp_sync_recursive($config, $type, $stream);
}

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
        if ($stream) send_progress(100, "ERROR: Binario mysqldump no encontrado en el PATH.");
        return ['status' => 'error', 'message' => 'Binario mysqldump no encontrado. Verifique Diagnóstico.'];
    }
    
    $user = $config['db']['username'] ?? $config['db']['user'] ?? 'root';
    $pass = $config['db']['password'] ?? $config['db']['pass'] ?? '';
    $host = $config['db']['host'] ?? 'localhost';
    
    // PROTOCOLO MAESTRO: Blindaje vía archivo de configuración temporal (.cnf)
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
    
    // Comando optimizado: SQL a archivo, Errores a archivo temporal
    $cmd = "\"$dumpPathWin\" --defaults-extra-file=\"$cnfPathWin\" " . escapeshellarg($db) . " > \"$filenameWin\" 2> \"$errorFileWin\"";
    exec($cmd, $output, $returnVar);
    
    $errorMsg = file_exists($errorFile) ? trim(file_get_contents($errorFile)) : '';
    @unlink($cnfFile);
    @unlink($errorFile);
    
    // ESTRATEGIA DE FALLBACK: Si falla (ej. credenciales remotas en local), intentar con ROOT de Laragon
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
        $errorMsg = file_exists($errorFile) ? trim(file_get_contents($errorFile)) : $errorMsg;
        @unlink($errorFile);
    }
    
    if ($returnVar !== 0) {
        if ($stream) send_progress(100, "ERROR_PROTO: Falló el volcado.");
        // Si el archivo se creó pero tiene error, borrarlo
        if (file_exists($filename)) @unlink($filename);
        return ['status' => 'error', 'message' => "Error en mysqldump: " . ($errorMsg ?: "Código $returnVar")];
    }
    
    if ($stream) send_progress(100, "Protocolo de base de datos completado.");
    return ['status' => 'success', 'message' => "Backup SQL generado: " . basename($filename)];
}

/**
 * Lista backups (SQL y Sitios) para un proyecto específico
 */
function list_project_backups($projectName) {
    $dbDir = BACKUP_DIR . '/db';
    $siteDir = BACKUP_DIR . '/sites';
    $backups = [];
    $projectName = strtoupper($projectName);

    if (is_dir($dbDir)) {
        $files = scandir($dbDir);
        foreach ($files as $f) {
            if (strpos(strtoupper($f), $projectName) === 0 && strpos($f, '.sql') !== false) {
                $backups[] = ['name' => $f, 'type' => 'db', 'size' => round(filesize($dbDir.'/'.$f)/1024, 2).' KB', 'date' => date('Y-m-d H:i', filemtime($dbDir.'/'.$f))];
            }
        }
    }
    
    if (is_dir($siteDir)) {
        $files = scandir($siteDir);
        foreach ($files as $f) {
            if (strpos(strtoupper($f), $projectName) === 0) {
                $backups[] = ['name' => $f, 'type' => 'site', 'size' => is_dir($siteDir.'/'.$f) ? 'DIR' : round(filesize($siteDir.'/'.$f)/1024, 2).' KB', 'date' => date('Y-m-d H:i', filemtime($siteDir.'/'.$f))];
            }
        }
    }
    
    return ['status' => 'success', 'backups' => array_reverse($backups)];
}

/**
 * Importa un backup SQL a la base de datos local
 */
function import_backup_db($projectName, $filename, $stream = false) {
    $config = get_project_full_config($projectName);
    if (!$config) return ['status' => 'error', 'message' => 'Configuración no localizada'];
    
    $db = $config['db']['database'] ?? $config['db']['name'] ?? '';
    if (!$db) {
        if ($stream) send_progress(100, "ERROR: Base de datos no definida en .env.");
        return ['status' => 'error', 'message' => 'Base de datos no definida'];
    }
    
    if (!MYSQL_PATH || !file_exists(MYSQL_PATH)) {
        if ($stream) send_progress(100, "ERROR: Binario mysql no encontrado.");
        return ['status' => 'error', 'message' => 'Binario mysql no encontrado. Verifique Diagnóstico.'];
    }

    $base = BACKUP_DIR . '/db/';
    $filePath = realpath($base . $filename);
    if (!$filePath || strpos($filePath, realpath($base)) !== 0 || !file_exists($filePath)) {
        if ($stream) send_progress(100, "☣️ ERROR_SEGURIDAD: Acceso denegado o archivo no encontrado.");
        return ['status' => 'error', 'message' => 'Archivo SQL no encontrado o acceso denegado.'];
    }

    if ($stream) send_progress(30, "Iniciando restauración de base de datos...");
    
    $user = $config['db']['username'] ?? $config['db']['user'] ?? 'root';
    $pass = $config['db']['password'] ?? $config['db']['pass'] ?? '';
    $host = $config['db']['host'] ?? 'localhost';
    
    $tmpDir = sys_get_temp_dir();
    $cnfFile = $tmpDir . DIRECTORY_SEPARATOR . 'peon_db_import_' . uniqid() . '.cnf';
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

// NOTE: Legacy sync functions removed in favor of ftp_sync_recursive

function get_project_full_config($projectName) {
    // Intentar primero con el nombre exacto, luego con minúsculas como fallback
    $dir = PROJECTS_BASE_DIR . DIRECTORY_SEPARATOR . $projectName;
    if (!is_dir($dir)) {
        $dir = PROJECTS_BASE_DIR . DIRECTORY_SEPARATOR . strtolower($projectName);
        if (!is_dir($dir)) return null;
    }

    $config = ['name' => strtoupper($projectName), 'dir' => $dir, 'exclude' => GLOBAL_EXCLUDE];
    $envFile = $dir . DIRECTORY_SEPARATOR . '.env';

    if (file_exists($envFile)) {
        $shouldShield = false;
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k); $v = trim($v, " \t\n\r\0\x0B\"'");
            
            $isSensitive = stripos($k, 'DB_') === 0 || stripos($k, 'FTP_') === 0 || in_array(strtolower($k), ['host', 'user', 'pass', 'root']);
            if ($isSensitive && !PeonVault::isEncrypted($v) && !empty($v)) $shouldShield = true;

            if (stripos($k, 'DB_') === 0) $config['db'][strtolower(substr($k, 3))] = PeonVault::isEncrypted($v) ? PeonVault::decrypt($v) : $v;
            if (stripos($k, 'FTP_') === 0) $config['ftp'][strtolower(substr($k, 4))] = PeonVault::isEncrypted($v) ? PeonVault::decrypt($v) : $v;
            if (in_array(strtolower($k), ['host', 'user', 'pass', 'root'])) $config['ftp'][strtolower($k)] = PeonVault::isEncrypted($v) ? PeonVault::decrypt($v) : $v;
            
            // Exclusiones personalizadas por proyecto
            if ($k === 'PROJ_EXCLUDE') {
                $customExcludes = array_map('trim', explode(',', $v));
                $config['exclude'] = array_unique(array_merge($config['exclude'], $customExcludes));
            }
        }
        if ($shouldShield) shield_env_file($envFile);
    }

    // ESTRATEGIA DE FALLBACK: Consultar el Cofre Maestro si el .env local está vacío o incompleto
    if (empty($config['ftp']['host']) || empty($config['ftp']['user'])) {
        $managed = get_managed_projects();
        foreach ($managed as $p) {
            if (strcasecmp($p['name'] ?? '', $projectName) === 0) {
                if (empty($config['ftp']['host'])) $config['ftp']['host'] = PeonVault::isEncrypted($p['host'] ?? '') ? PeonVault::decrypt($p['host']) : ($p['host'] ?? '');
                if (empty($config['ftp']['user'])) $config['ftp']['user'] = PeonVault::isEncrypted($p['user'] ?? '') ? PeonVault::decrypt($p['user']) : ($p['user'] ?? '');
                if (empty($config['ftp']['pass'])) $config['ftp']['pass'] = PeonVault::isEncrypted($p['pass'] ?? '') ? PeonVault::decrypt($p['pass']) : ($p['pass'] ?? '');
                if (empty($config['ftp']['root'])) $config['ftp']['root'] = PeonVault::isEncrypted($p['root'] ?? '') ? PeonVault::decrypt($p['root']) : ($p['root'] ?? '/');
                
                // Fallback para Base de Datos desde Master si no está en local
                if (empty($config['db']['database'])) {
                    $db_name = PeonVault::isEncrypted($p['db_name'] ?? '') ? PeonVault::decrypt($p['db_name']) : ($p['db_name'] ?? '');
                    if (!empty($db_name)) $config['db']['database'] = $db_name;
                }
                break;
            }
        }
    }

    // FALLBACK FINAL: En Laragon, el nombre del directorio suele ser el nombre de la DB
    if (empty($config['db']['database'])) {
        $config['db']['database'] = strtolower($projectName);
    }


    // Soporte para .peonignore
    $peonIgnore = $dir . DIRECTORY_SEPARATOR . '.peonignore';
    if (file_exists($peonIgnore)) {
        $extra = file($peonIgnore, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($extra) $config['exclude'] = array_unique(array_merge($config['exclude'], $extra));
    }

    // 4. Fallback a configuración manual en Peon .env (si el proyecto no tiene su propio .env completo)
    $id = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $projectName));
    $env = getEnvData();

    $dbFields = ['DATABASE' => 'db_name', 'NAME' => 'db_name', 'USERNAME' => 'db_user', 'USER' => 'db_user', 'PASSWORD' => 'db_pass', 'PASS' => 'db_pass', 'HOST' => 'db_host'];
    foreach ($dbFields as $suffix => $configKey) {
        $key = "PROJ_{$id}_{$suffix}";
        if (isset($env[$key])) {
            $val = PeonVault::isEncrypted($env[$key]) ? PeonVault::decrypt($env[$key]) : $env[$key];
            if ($configKey === 'db_name') $config['db']['database'] = $val;
            else if ($configKey === 'db_user') $config['db']['username'] = $val;
            else if ($configKey === 'db_pass') $config['db']['password'] = $val;
            else if ($configKey === 'db_host') $config['db']['host'] = $val;
        }
    }
    $ftpFields = ['HOST' => 'host', 'USER' => 'user', 'PASS' => 'pass', 'ROOT' => 'root'];
    foreach ($ftpFields as $suffix => $configKey) {
        $key = "PROJ_{$id}_{$suffix}";
        if (isset($env[$key])) {
            $val = PeonVault::isEncrypted($env[$key]) ? PeonVault::decrypt($env[$key]) : $env[$key];
            if ($val !== '' && $val !== 'LOCAL_ONLY') {
                $config['ftp'][$configKey] = $val;
            }
        }
    }

    return $config;
}


/**
 * Escaneo proactivo con reporte de nodos crudos
 */
function scan_projects($stream = false) {
    @set_time_limit(0);
    if ($stream) {
        send_progress(10, "INICIANDO BARRIDO DE FRECUENCIAS...");
        usleep(100000); 
    }
    
    $baseDir = PROJECTS_BASE_DIR;
    if ($stream) {
        send_progress(12, "Checkpoint A (Ruta): " . $baseDir);
        usleep(100000);
    }
    
    if (!$baseDir || !is_dir($baseDir)) {
        if ($stream) send_progress(100, "ERROR: Ruta base no accesible.");
        return [];
    }

    if ($stream) {
        send_progress(15, "Checkpoint B (Permisos): " . (is_readable($baseDir) ? "LECTURA_OK" : "RESTRINGIDO"));
        usleep(100000);
    }
    
    if ($stream) {
        send_progress(18, "Cargando motor de búsqueda redundante...");
        usleep(100000);
    }

    $dirs = [];
    $scan = @array_diff(scandir($baseDir), ['.', '..']);
    if ($scan) {
        foreach ($scan as $s) {
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $s;
            if (is_dir($fullPath)) $dirs[] = $fullPath;
        }
    }

    if ($stream) {
        send_progress(20, "Sensor activo. Nodos crudos detectados: " . count($dirs));
        usleep(100000);
    }
    
    $found = [];
    $total = count($dirs);
    $current = 0;

    foreach ($dirs as $dir) {
        $current++;
        try {
            $folderName = basename($dir);
            $pct = 20 + round(($current / $total) * 75);
            $lowerName = strtolower($folderName);
            
            if ($stream) send_progress($pct, "ANALIZANDO NODO: " . $folderName);
            
            if (in_array($lowerName, array_map('strtolower', GLOBAL_EXCLUDE)) || $lowerName === 'peon' || strpos($folderName, '.') === 0) {
                if ($stream) send_progress($pct, "SISTEMA: Nodo excluido por protocolo.");
                continue;
            }
            
            $config = get_project_full_config($folderName);
            $ftpData = $config['ftp'] ?? [];
            
            // Docker Sentinel
            $isDocker = file_exists($dir . DIRECTORY_SEPARATOR . 'Dockerfile') || file_exists($dir . DIRECTORY_SEPARATOR . 'docker-compose.yml');

            $payload = ['name' => $folderName, 'host' => 'LOCAL_ONLY', 'docker' => $isDocker];
            if ($config && isset($ftpData['host']) && $ftpData['host'] !== 'LOCAL_ONLY') {
                if ($stream) send_progress($pct, "PROTOCOLO FTP DETECTADO: " . $folderName . ($isDocker ? " [DOCKER]" : ""));
                $payload = array_merge($ftpData, $payload);
            } else {
                if ($stream) send_progress($pct, "PROYECTO LOCAL REGISTRADO: " . $folderName . ($isDocker ? " [MODO_DOCKER]" : ""));
            }
            
            $found[] = $payload;

        } catch (Exception $e) {
            if ($stream) send_progress($pct, "ERROR_SENSOR: " . $e->getMessage());
        }
    }

    if (!empty($found)) {
        if ($stream) send_progress(95, "Sincronizando base de datos de proyectos...");
        save_projects_batch($found);
    }

    if ($stream) send_progress(100, "RECONOCIMIENTO DE RED FINALIZADO.", ['done' => true, 'found' => $found]);
    return $found;
}

function ftp_fetch_items($conn, $path) {
    // 1. Intentar el comando moderno MLSD (RFC 3659)
    $items = @ftp_mlsd($conn, $path);
    if ($items !== false) return $items;

    // 2. Fallback a NLIST + Información individual (Legacy)
    $list = @ftp_nlist($conn, $path);
    if ($list === false) return [];
    
    $results = [];
    foreach ($list as $entry) {
        $name = basename($entry);
        if ($name === '.' || $name === '..') continue;
        
        $fullRemotePath = ($path === '/') ? '/' . $name : $path . '/' . $name;
        
        // Determinar tipo (cambio de directorio es el método más fiable en legacy)
        $isDir = @ftp_chdir($conn, $fullRemotePath);
        if ($isDir) {
            @ftp_chdir($conn, $path);
            $type = 'dir';
            $mtime = null;
            $size = -1;
        } else {
            $type = 'file';
            // Obtener fecha de modificación (MDTM)
            $mtimeRaw = @ftp_mdtm($conn, $fullRemotePath);
            $mtime = ($mtimeRaw !== -1) ? date('Y-m-d H:i:s', $mtimeRaw) : null;
            $size = @ftp_size($conn, $fullRemotePath);
        }

        $results[] = [
            'name' => $name,
            'type' => $type,
            'modify' => $mtime,
            'size' => $size
        ];
    }
    return $results;
}

function should_exclude($name, $path, $excludeList) {
    if ($name === '.' || $name === '..') return true;
    
    // Normalizar ruta a forward slashes para comparación consistente
    $normalizedPath = str_replace(['\\', '//'], '/', $path);
    $normalizedName = str_replace(['\\', '//'], '/', $name);
    
    foreach ($excludeList as $pattern) {
        if (empty($pattern)) continue;
        
        // Coincidencia exacta de nombre o ruta
        if ($normalizedName === $pattern || $name === $pattern) return true;
        
        // Coincidencia de prefijo/directorio
        if (strpos($normalizedPath, '/' . $pattern . '/') !== false || 
            strpos($normalizedPath, '/' . $pattern) === 0 ||
            strpos($normalizedName, $pattern) === 0) return true;
            
        // Coincidencia por patrón (glob)
        if (fnmatch($pattern, $name) || fnmatch($pattern, $normalizedPath)) return true;
    }
    return false;
}

/**
 * Guarda una lista de proyectos en el .env en una sola operación (Optimizado)
 */
function save_projects_batch($projects) {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) @touch($envPath);

    $lock = @fopen($envPath, 'c+');
    if (!$lock) return;
    flock($lock, LOCK_EX);

    $content = stream_get_contents($lock);
    $lines = explode("\n", str_replace("\r", "", $content));
    
    // 1. Clasificar líneas: Preservar las que no son PROJ_
    $otherLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) {
            $otherLines[] = $line;
            continue;
        }
        if (strpos($trimmed, '#') === 0 || strpos($line, '=') === false) {
            $otherLines[] = $line;
            continue;
        }
        
        list($k) = explode('=', $line, 2);
        if (strpos(trim($k), 'PROJ_') !== 0) {
            $otherLines[] = $line;
        }
    }

    // 2. Generar nuevas líneas de proyectos
    $projectLines = [];
    foreach ($projects as $config) {
        if (empty($config['name'])) continue;
        
        // ID determinista pero seguro: Uppercase, solo letras y números
        $id = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $config['name']));
        if (empty($id)) $id = strtoupper(substr(md5($config['name']), 0, 8));

        $fields = [
            'ID'      => $config['name'], 
            'HOST'    => $config['host'] ?? 'LOCAL_ONLY', 
            'USER'    => $config['user'] ?? '', 
            'PASS'    => $config['pass'] ?? '', 
            'ROOT'    => $config['root'] ?? '/',
            'DBNAME'  => $config['db_name'] ?? $config['dbname'] ?? '',
            'DBUSER'  => $config['db_user'] ?? $config['dbuser'] ?? '',
            'DBPASS'  => $config['db_pass'] ?? $config['dbpass'] ?? '',
            'DBHOST'  => $config['db_host'] ?? $config['dbhost'] ?? ''
        ];

        foreach ($fields as $field => $val) {
            $key = "PROJ_{$id}_{$field}";
            $valEnc = PeonVault::encrypt($val);
            $projectLines[] = "{$key}=\"{$valEnc}\"";
        }
    }

    // 3. Reensamblaje: Mantener orden y evitar duplicidad masiva pero sin afectar comentarios
    $finalLines = array_filter($otherLines, function($line) { return !empty(trim($line)) || $line === ""; });
    
    // Añadir proyectos al final si no existen ya (aunque acabamos de filtrar los PROJ_)
    $finalContent = implode("\n", $finalLines);
    if (!empty($projectLines)) {
        $finalContent = rtrim($finalContent) . "\n\n# --- PEON MANAGED PROJECTS ---\n" . implode("\n", $projectLines);
    }

    // 4. Escritura Atómica
    ftruncate($lock, 0);
    rewind($lock);
    fwrite($lock, trim($finalContent) . "\n");
    
    fflush($lock);
    flock($lock, LOCK_UN);
    fclose($lock);
}

function save_project_to_env($config) {
    save_projects_batch([$config]);
}

function get_managed_projects() {
    $env = getEnvData();
    $projects = [];
    foreach ($env as $key => $val) {
        if (strpos($key, 'PROJ_') === 0) {
            // Lógica táctica: El campo es la última palabra tras el último guión bajo
            $parts = explode('_', $key);
            if (count($parts) < 3) continue;
            
            $field = strtoupper(end($parts)); 
            
            // Reconstrucción del ID (todo lo que hay entre PROJ_ y el último _)
            $id = substr($key, 5, -(strlen($field) + 1));
            
            if (!isset($projects[$id])) $projects[$id] = [];
            $projects[$id][strtolower($field)] = $val;
            
            if (strtolower($field) === 'id') {
                $projects[$id]['name'] = $val;
            }
        }
    }
    
    // Post-procesamiento y saneamiento
    $finalList = [];
    foreach ($projects as $k => $p) {
        $projectName = $p['name'] ?? $p['id'] ?? $k;
        if (strpos($projectName, '.') === 0 && $projectName !== '.env') { // Permitir .env si es necesario, pero skip otros ocultos
            continue;
        }
        
        $p['name'] = $projectName;
        
        // Mapeo inverso de DB si los campos vinieron del legacy
        if (!isset($p['db_name']) && isset($p['dbname'])) $p['db_name'] = $p['dbname'];
        if (!isset($p['db_user']) && isset($p['dbuser'])) $p['db_user'] = $p['dbuser'];
        if (!isset($p['db_pass']) && isset($p['dbpass'])) $p['db_pass'] = $p['dbpass'];
        if (!isset($p['db_host']) && isset($p['dbhost'])) $p['db_host'] = $p['dbhost'];

        $finalList[] = $p;
    }
    
    // Ordenar por nombre para consistencia visual
    usort($finalList, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
    
    return $finalList;
}

/**
 * Guarda exclusiones personalizadas en el .env del proyecto
 */
function save_project_excludes($projectName, $excludes) {
    $dir = PROJECTS_BASE_DIR . DIRECTORY_SEPARATOR . strtolower($projectName);
    $envFile = $dir . DIRECTORY_SEPARATOR . '.env';
    
    if (!is_dir($dir)) return ['status' => 'error', 'message' => 'Directorio de proyecto no encontrado.'];
    
    $lines = file_exists($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES) : [];
    $found = false;
    $key = 'PROJ_EXCLUDE';
    $val = trim($excludes);

    foreach ($lines as &$line) {
        if (strpos($line, $key . '=') === 0) {
            $line = "{$key}=\"{$val}\"";
            $found = true;
            break;
        }
    }
    if (!$found) $lines[] = "{$key}=\"{$val}\"";
    
    if (@file_put_contents($envFile, implode("\n", $lines)) !== false) {
        return ['status' => 'success', 'message' => 'Protocolo de exclusión actualizado correctamente.'];
    }
    return ['status' => 'error', 'message' => 'Error al escribir en el archivo .env del proyecto.'];
}

/**
 * Utilidad de Blindaje Automático (In-place)
 */
function shield_env_file($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $modified = false;
    foreach ($lines as &$line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $kTrim = trim($k);
        $vTrim = trim($v, " \t\n\r\0\x0B\"'");
        
        $isSensitive = stripos($kTrim, 'DB_') === 0 || stripos($kTrim, 'FTP_') === 0 || in_array(strtolower($kTrim), ['host', 'user', 'pass', 'root']) || strpos($kTrim, 'PROJ_') === 0;
        
        if ($isSensitive && !PeonVault::isEncrypted($vTrim) && !empty($vTrim)) {
            $valEnc = PeonVault::encrypt($vTrim);
            $line = "{$kTrim}=\"{$valEnc}\"";
            $modified = true;
        }
    }
    if ($modified) file_put_contents($path, implode("\n", $lines));
}

/**
 * Sincronización Previa (Vista)
 */
function ftp_sync_preview($config) {
    if (empty($config['ftp']['host']) || $config['ftp']['host'] === 'LOCAL_ONLY') return ['status' => 'request_config', 'type' => 'ftp', 'message' => 'Configuración FTP no detectada.'];
    $localRoot = $config['dir'];
    $exclude = $config['exclude'] ?? GLOBAL_EXCLUDE;
    
    $stateFile = $localRoot . '/.sync_state.json';
    $lastState = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    $currentState = [];
    scan_local_dir_recursive($localRoot, $currentState, $exclude, $localRoot);
    
    $toPush = [];
    foreach ($currentState as $file => $mtime) {
        if (!isset($lastState[$file]) || $lastState[$file] < $mtime) {
            $toPush[] = $file;
        }
    }
    return ['status' => 'success', 'files' => $toPush];
}

/**
 * Auditoría Táctica - Registra operaciones en un archivo protegido
 */
function audit_log($action, $projectName, $status, $details = '') {
    $logFile = __DIR__ . '/backups/audit.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = PeonEnv::getUserName();
    $entry = "[$timestamp] [$user] ACTION: $action | PROJECT: $projectName | STATUS: $status | DETAILS: $details\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

if (!defined('GLOBAL_EXCLUDE')) {
    define('GLOBAL_EXCLUDE', ['.git', '.antigravity', '.agents', '.agent', 'node_modules', 'BACKUP', 'tmp', 'peon']);
}

/**
 * Motor de Sincronización Avanzada (Pull/Push/Backup)
 */
function ftp_sync_recursive($config, $mode, $stream = false) {
    // BLINDAJE TÁCTICO: Validación de Licencia para Funciones Pro
    $auth = validate_remote_license($stream);
    if ($auth['status'] === 'error') {
        if ($stream) {
            echo "data: " . json_encode(['status' => 'error', 'msg' => '⚠️ ' . $auth['message']]) . "\n\n";
            @flush();
            exit;
        }
        return $auth;
    }

    if (empty($config['ftp']['host']) || $config['ftp']['host'] === 'LOCAL_ONLY') {
        if ($stream) {
            echo "data: " . json_encode(['status' => 'request_config', 'type' => 'ftp', 'msg' => 'ERROR: Configuración FTP no detectada. Ingreso manual requerido.']) . "\n\n";
            @flush();
            exit;
        }
        return ['status' => 'error', 'message' => 'Configuración FTP no detectada.'];
    }
    
    if ($stream) send_progress(10, "ESTABLECIENDO PROTOCOLO DE CONEXIÓN...");
    $ftpHost = $config['ftp']['host'];
    $conn = null;
    if (function_exists('ftp_ssl_connect')) $conn = @ftp_ssl_connect($ftpHost);
    if (!$conn) $conn = @ftp_connect($ftpHost);
    
    if (!$conn) {
        $err = error_get_last();
        return ['status' => 'error', 'message' => 'No se pudo conectar al host táctico. ' . ($err['message'] ?? '')];
    }
    
    if (!@ftp_login($conn, $config['ftp']['user'], $config['ftp']['pass'])) {
        $err = error_get_last();
        ftp_close($conn);
        return ['status' => 'error', 'message' => 'Autenticación fallida. Acceso denegado. ' . ($err['message'] ?? '')];
    }
    ftp_pasv($conn, true);

    $remoteRoot = $config['ftp']['root'] ?? '/';
    $localRoot = $config['dir'];
    $exclude = $config['exclude'] ?? GLOBAL_EXCLUDE;

    if ($mode === 'backup') {
        audit_log('BACKUP_START', $config['name'], 'PROCESSING');
        $date = date('Ymd_His');
        $backupPath = BACKUP_DIR . "/sites/{$config['name']}_$date";
        if (!is_dir($backupPath)) @mkdir($backupPath, 0777, true);
        
        if ($stream) send_progress(15, "CALCULANDO VOLUMEN DE DATOS...");
        $stats = ['total' => 0, 'current' => 0];
        count_remote_vault($conn, $remoteRoot, $exclude, $stats);

        if ($stream) send_progress(20, "INICIANDO DESCARGA INTEGRAL EN: " . basename($backupPath));
        download_recursive_vault($conn, $remoteRoot, $backupPath, $stream, 20, 100, false, $exclude, $stats);
        ftp_close($conn);
        audit_log('BACKUP_COMPLETE', $config['name'], 'SUCCESS');
        return ['status' => 'success', 'message' => 'DEPÓSITO DE SEGURIDAD COMPLETADO.'];
    }

    if ($mode === 'pull') {
        audit_log('PULL_START', $config['name'], 'PROCESSING');
        if ($stream) send_progress(15, "ESCANEANDO SERVIDOR REMOTO...");
        $stats = ['total' => 0, 'current' => 0];
        count_remote_vault($conn, $remoteRoot, $exclude, $stats);
        
        if ($stream) send_progress(20, "INICIANDO ACTUALIZACIÓN DESDE SERVIDOR (PULL)...");
        download_recursive_vault($conn, $remoteRoot, $localRoot, $stream, 20, 95, true, $exclude, $stats);
        
        // El backup debe incluir todo el proyecto
        if ($stream) send_progress(96, "GENERANDO COPIA DE SEGURIDAD LOCAL INTEGRAL...");
        $date = date('Ymd_His');
        $localBk = BACKUP_DIR . "/sites/{$config['name']}_FULL_$date";
        @mkdir($localBk, 0777, true);
        array_copy_recursive($localRoot, $localBk, $exclude);
        
        if ($stream) send_progress(100, "VINCULACIÓN (PULL) COMPLETADA.");
        ftp_close($conn);
        audit_log('PULL_COMPLETE', $config['name'], 'SUCCESS');
        return ['status' => 'success', 'message' => 'Sincronización (Pull) exitosa.'];
    }

    if ($mode === 'push') {
        audit_log('PUSH_START', $config['name'], 'PROCESSING');
        $stateFile = $localRoot . '/.sync_state.json';
        $lastState = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
        $currentState = [];
        scan_local_dir_recursive($localRoot, $currentState, $exclude, $localRoot);
        
        $toPush = [];
        foreach ($currentState as $file => $mtime) {
            if (!isset($lastState[$file]) || $lastState[$file] < $mtime) {
                $toPush[] = $file;
            }
        }

        if (empty($toPush)) {
            if ($stream) send_progress(100, "SIN CAMBIOS DETECTADOS. TODO ACTUALIZADO.");
            ftp_close($conn);
            return ['status' => 'success', 'message' => 'Todo al día.'];
        }

        $total = count($toPush);
        
        $isConfirm = isset($_GET['confirm']) && $_GET['confirm'] == '1';
        if (!$isConfirm) {
            if ($stream) {
                echo "data: " . json_encode(['status' => 'require_auth', 'type' => 'push', 'files' => $toPush, 'msg' => 'AUTORIZACIÓN REQUERIDA (' . $total . ' archivos)']) . "\n\n";
                @flush();
                ftp_close($conn);
                exit;
            }
            ftp_close($conn);
            return ['status' => 'require_auth', 'files' => $toPush];
        }

        if ($stream) {
            send_progress(18, "AUTORIZACIÓN CONFIRMADA. PREPARANDO ENVÍO...");
            
            // Enviar lista completa de archivos afectados
            echo "data: " . json_encode(['status' => 'preview', 'files' => $toPush]) . "\n\n";
            @flush();
            
            $fileNames = array_map('basename', $toPush);
            $previewList = count($fileNames) > 10 
                ? implode(', ', array_slice($fileNames, 0, 10)) . " y " . (count($fileNames)-10) . " más..."
                : implode(', ', $fileNames);
            
            send_progress(20, "LISTA DE MISIÓN: " . $previewList);
            usleep(800000); 
        }
        
        $skipFiles = isset($_GET['skip']) && !empty($_GET['skip']) ? explode(',', $_GET['skip']) : [];
        if (!empty($skipFiles)) {
            // Guardar permanentemente en .peonignore
            $peonIgnore = $localRoot . '/.peonignore';
            $existingIgnores = file_exists($peonIgnore) ? file($peonIgnore, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            $newIgnores = array_unique(array_merge($existingIgnores, $skipFiles));
            file_put_contents($peonIgnore, implode("\n", $newIgnores) . "\n");
        }

        $pushedCount = 0;
        foreach ($toPush as $idx => $file) {
            $pct = 20 + round((($idx + 1) / $total) * 75);
            
            if (in_array($file, $skipFiles)) {
                if ($stream) send_progress($pct, "IGNORADO PERMANENTEMENTE: " . $file);
                // Evitamos actualizar el state para file si lo ignoramos a la fuerza
                continue;
            }
            
            if ($stream) send_progress($pct, "TRANSFERIENDO: " . $file);
            
            // Asegurar directorios remotos
            $parts = explode('/', $file);
            array_pop($parts);
            $curr = $remoteRoot;
            foreach ($parts as $part) {
                $curr = rtrim($curr, '/') . '/' . $part;
                if (!@ftp_chdir($conn, $curr)) {
                    @ftp_mkdir($conn, $curr);
                    @ftp_chdir($conn, $remoteRoot);
                }
            }
            
            $remoteFile = rtrim($remoteRoot, '/') . '/' . $file;
            if (@ftp_put($conn, $remoteFile, $localRoot . '/' . $file, FTP_BINARY)) {
                $lastState[$file] = $currentState[$file];
                $pushedCount++;
            } else {
                if ($stream) send_progress($pct, "ERR_TRANSF: " . $file);
            }
        }

        file_put_contents($stateFile, json_encode($lastState));
        ftp_close($conn);
        audit_log('PUSH_COMPLETE', $config['name'], 'SUCCESS', "$pushedCount files updated");
        if ($stream) send_progress(100, "OPERACIÓN FINALIZADA. $pushedCount archivos actualizados.");
        return ['status' => 'success', 'message' => "OPERACIÓN FINALIZADA. $pushedCount cambios procesados."];
    }

    ftp_close($conn);
    return ['status' => 'error', 'message' => 'Modo de sincronización no válido.'];
}

function download_recursive_vault($conn, $remoteDir, $localDir, $stream, $startPct, $endPct, $onlyNewer = false, $exclude = [], &$stats = null) {
    if (!is_dir($localDir)) @mkdir($localDir, 0777, true);
    
    // Intento con MLSD (Moderno)
    $items = @ftp_mlsd($conn, $remoteDir);
    
    // Fallback a NLIST (Compatibilidad con Hostinger y servidores antiguos)
    if ($items === false) {
        $files = @ftp_nlist($conn, $remoteDir);
        if ($files === false) return;
        $items = [];
        foreach ($files as $file) {
            $base = basename($file);
            if ($base === '.' || $base === '..') continue;
            
            // Detección de tipo manual para NLIST
            $isDir = false;
            if (@ftp_chdir($conn, rtrim($remoteDir, '/') . '/' . $base)) {
                $isDir = true;
                @ftp_chdir($conn, $remoteDir);
            }
            $items[] = ['name' => $base, 'type' => ($isDir ? 'dir' : 'file')];
        }
    }

    if (empty($items)) return;

    foreach ($items as $item) {
        $name = $item['name'];
        if ($name === '.' || $name === '..') continue;
        if (should_exclude($name, $remoteDir . '/' . $name, $exclude)) continue;

        $remotePath = rtrim($remoteDir, '/') . '/' . $name;
        $localPath = $localDir . '/' . $name;
        
        if ($item['type'] === 'dir') {
            download_recursive_vault($conn, $remotePath, $localPath, $stream, $startPct, $endPct, $onlyNewer, $exclude, $stats);
        } else {
            if ($stats) {
                $stats['current']++;
                if ($stream && $stats['total'] > 0) {
                    $pct = $startPct + round(($stats['current'] / $stats['total']) * ($endPct - $startPct));
                    $isNew = !file_exists($localPath);
                    $msg = $isNew ? "NUEVO RECURSO: " . $name : "ACTUALIZANDO: " . $name;
                    
                    // Solo enviar mensaje si hay progreso significativo o cada 10 archivos para evitar saturar el canal
                    if ($stats['current'] % 10 === 0) {
                        send_progress($pct, $msg);
                        // Pulso de vida táctico
                        echo ": Pulse handshake\n\n";
                        @flush();
                    }
                }
            }

            $isNew = !file_exists($localPath);
            if ($onlyNewer && !$isNew) {
                 $remoteTime = @ftp_mdtm($conn, $remotePath);
                 if ($remoteTime !== -1 && $remoteTime <= filemtime($localPath)) continue;
            }
            
            @ftp_get($conn, $localPath, $remotePath, FTP_BINARY);
        }
    }
}

function count_remote_vault($conn, $remoteDir, $exclude, &$stats) {
    $items = @ftp_mlsd($conn, $remoteDir);
    if ($items === false) return;
    foreach ($items as $item) {
        $name = $item['name'];
        if ($name === '.' || $name === '..') continue;
        if (should_exclude($name, $remoteDir . '/' . $name, $exclude)) continue;
        if ($item['type'] === 'dir') count_remote_vault($conn, rtrim($remoteDir, '/') . '/' . $name, $exclude, $stats);
    }
}

function scan_local_dir_recursive($dir, &$files, $exclude, $baseDir) {
    $items = @array_diff(scandir($dir), ['.', '..']);
    if (!$items) return;
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        $relPath = str_replace([$baseDir . '/', $baseDir], '', $path);
        
        foreach ($exclude as $ex) {
            if ($relPath === $ex || strpos($relPath, $ex . '/') === 0) continue 2;
        }

        if (is_dir($path)) {
            scan_local_dir_recursive($path, $files, $exclude, $baseDir);
        } else {
            $files[$relPath] = filemtime($path);
        }
    }
}

/**
 * Ejecuta la purga completa del directorio de habilidades
 */
function perform_purge_cleanup() {
    global $ANTIGRAVITY_SKILLS_PATH;
    $path = $ANTIGRAVITY_SKILLS_PATH;
    
    if (empty($path) || !is_dir($path)) {
        return ['status' => 'error', 'message' => "La ruta de habilidades no fue localizada o ya está vacía.\n\nRUTA: $path"];
    }

    // Usamos rmdir_recursive definido anteriormente
    if (rmdir_recursive($path)) {
        // Re-creamos la carpeta skills vacía para evitar errores de sistema, 
        // pero sin el contenido para forzar la detección de "no skills"
        @mkdir($path, 0777, true);
        return ['status' => 'success', 'message' => 'PROTOCOLO DE PURGA FINALIZADO. Las habilidades del agente han sido desmanteladas correctamente.'];
    }

    return ['status' => 'error', 'message' => 'ERROR_CRÍTICO: No se pudo completar la purga. Verifique los permisos de Laragon/Sistema sobre la carpeta .gemini.'];
}

function array_copy_recursive($src, $dst, $exclude = []) {
    $dir = opendir($src);
    @mkdir($dst);
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

/**
 * Sincronización de Base de Datos Remota (Protocolo PULL SQL)
 */
function ftp_pull_remote_db($config, $stream = false) {
    if (empty($config['ftp']['host']) || $config['ftp']['host'] === 'LOCAL_ONLY') {
        if ($stream) {
            echo "data: " . json_encode(['status' => 'request_config', 'type' => 'ftp', 'msg' => 'ERROR: Configuración FTP no detectada. Ingreso manual requerido.']) . "\n\n";
            @flush();
            exit;
        }
        return ['status' => 'error', 'message' => 'Configuración FTP no detectada.'];
    }
    
    if ($stream) send_progress(10, "ESTABLECIENDO PROTOCOLO DE CONEXIÓN FTP...");
    
    $ftpHost = $config['ftp']['host'];
    $conn = null;
    
    // Protocolo Seguro (Explicit SSL) si está disponible
    if (function_exists('ftp_ssl_connect')) {
        $conn = @ftp_ssl_connect($ftpHost);
    }
    
    // Fallback a conexión estándar
    if (!$conn) {
        $conn = @ftp_connect($ftpHost);
    }
    
    if (!$conn) {
        $err = error_get_last();
        return ['status' => 'error', 'message' => 'No se pudo conectar al host táctico. ' . ($err['message'] ?? '')];
    }
    
    if (!@ftp_login($conn, $config['ftp']['user'], $config['ftp']['pass'])) {
        $err = error_get_last();
        ftp_close($conn);
        return ['status' => 'error', 'message' => 'Autenticación fallida. Acceso denegado. ' . ($err['message'] ?? '')];
    }
    ftp_pasv($conn, true);

    $remoteDir = $config['ftp']['root'] ?? '/';
    $dbDir = BACKUP_DIR . '/db';
    if (!is_dir($dbDir)) @mkdir($dbDir, 0777, true);

    if ($stream) send_progress(20, "ESCANEANDO SERVIDOR EN BUSCA DE BACKUPS SQL...");
    
    // Intentar listar archivos remotos (usando nlist para máxima compatibilidad)
    $nlist = @ftp_nlist($conn, $remoteDir);
    if (!$nlist) {
        ftp_close($conn);
        return ['status' => 'error', 'message' => 'No se pudieron listar los archivos remotos o directorio vacío.'];
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
        return ['status' => 'error', 'message' => 'No se encontraron archivos .sql en la raíz del servidor remoto.'];
    }

    // Ordenar por fecha (descendente) para obtener el más reciente
    usort($sqlFiles, function($a, $b) { return $b['mtime'] <=> $a['mtime']; });
    $targetFile = $sqlFiles[0]['name'];

    if ($stream) send_progress(40, "BACKUP DETECTADO: $targetFile. INICIANDO DESCARGA...");
    
    $localPath = $dbDir . '/' . $targetFile;
    if (@ftp_get($conn, $localPath, $remoteDir . '/' . $targetFile, FTP_BINARY)) {
        if ($stream) send_progress(80, "DESCARGA COMPLETADA. INICIANDO PROTOCOLO DE IMPORTACIÓN LOCAL...", 'processing');
        
        // Importación automática
        $importResult = import_backup_db($config['name'], $targetFile, $stream);
        
        ftp_close($conn);
        return [
            'status' => 'success', 
            'message' => "Base de Datos sincronizada e importada: $targetFile.\n\nResultado importación: " . ($importResult['message'] ?? 'OK')
        ];
    }

    ftp_close($conn);
    return ['status' => 'error', 'message' => "Fallo al descargar el archivo SQL desde el servidor."];
}

/**
 * Inyecta una Skill remota desde Sixlan tras validación de licencia
 */
    return ['status' => 'error', 'message' => $data['message'] ?? 'Error en inyección remota.'];
}

/**
 * Descarga y despliega el paquete completo de inteligencia táctica (ZIP)
 */
function install_intelligence_pack($stream = false) {
    if ($stream) send_progress(10, "Iniciando despliegue de arsenal de inteligencia...");
    
    $env = getEnvData();
    $key = $env['SIXLAN_LICENSE'] ?? '';
    
    if (empty($key)) {
        return ['status' => 'error', 'message' => 'Se requiere licencia táctica activa para descargar el paquete completo.'];
    }

    $url = SIXLAN_HUB_URL . "?action=get_tactical_pack&key=" . urlencode($key);
    $tempZip = __DIR__ . '/tmp_pack_' . time() . '.zip';
    
    if ($stream) send_progress(30, "Conectando con Sixlan Hub y descargando paquete táctico...");

    $ctx = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
    $content = @file_get_contents($url, false, $ctx);
    
    if (!$content || strpos($content, '{"status":"error"') === 0) {
        $msg = $content ? json_decode($content, true)['message'] : 'Error de conexión con el Hub central.';
        return ['status' => 'error', 'message' => $msg];
    }

    file_put_contents($tempZip, $content);
    unset($content); // Liberar memoria

    if ($stream) send_progress(60, "Descomprimiendo unidades tácticas y habilitando sectores especializados...", 'processing');

    $zip = new ZipArchive();
    $res = $zip->open($tempZip);
    if ($res === TRUE) {
        $extractPath = __DIR__ . '/code/skills/';
        if (!is_dir($extractPath)) @mkdir($extractPath, 0777, true);
        
        $zip->extractTo($extractPath);
        $zip->close();
        @unlink($tempZip);

        if ($stream) send_progress(100, "DESPLIEGUE FINALIZADO: El arsenal táctico está 100% operativo.");
        return ['status' => 'success', 'message' => 'Paquete de inteligencia instalado con éxito.'];
    } else {
        @unlink($tempZip);
        return ['status' => 'error', 'message' => 'Fallo al procesar el contenedor táctico (ZIP corrupto o incompleto).'];
    }
}

?>
