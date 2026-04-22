<?php
/**
 * PEON SYNC ENGINE v3.7 | Constant-First Secure Router
 * Corregido error fatal: Las constantes deben definirse antes de cargar los módulos.
 */

// 1. TELEMETRÍA BASE
function debug_sync($msg) {
    $log = __DIR__ . '/backups/debug_sync.log';
    if (!is_dir(dirname($log))) @mkdir(dirname($log), 0777, true);
    @file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

$isStream = (($_GET['stream'] ?? '0') == '1');

// 2. ERROR TRAPPING
function hud_error_handler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    $msg = "ERR: $errstr en $errfile:$errline";
    debug_sync("PHP_ERR: $msg");
    echo "data: " . json_encode(['status' => 'error', 'msg' => "⚠️ " . $errstr]) . "\n\n";
    @flush(); return true;
}

function hud_fatal_handler() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        $msg = "FATAL: {$error['message']} en {$error['file']}:{$error['line']}";
        debug_sync($msg);
        echo "data: " . json_encode(['status' => 'error', 'msg' => "🚫 FATAL: " . basename($error['file']) . ":" . $error['line']]) . "\n\n";
        @flush();
    }
}

set_error_handler("hud_error_handler");
register_shutdown_function("hud_fatal_handler");

// 3. HANDSHAKE SSE
if ($isStream) {
    header('Content-Type: text/event-stream');
    header('X-Accel-Buffering: no');
    header('Cache-Control: no-cache');
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    while (ob_get_level() > 0) ob_end_flush();
    ob_implicit_flush(true);
    echo str_repeat(" ", 2048) . "\n";
    echo ": handshake\n\n";
    echo "data: " . json_encode(['progress' => 5, 'msg' => 'CENTRO_DE_MANDO_ONLINE']) . "\n\n";
    @flush();
} else {
    header('Content-Type: application/json');
}

// 4. GLOBAL CONSTANTS (Mover al inicio para evitar errores de "Undefined Constant")
const GLOBAL_EXCLUDE = ['.git', '.antigravity', '.agents', '.agent', 'node_modules', 'BACKUP', 'tmp'];
const BACKUP_DIR = __DIR__ . '/backups';

// 5. RESOURCE ALLOCATION
@ini_set('memory_limit', '512M');
@set_time_limit(0);
@ignore_user_abort(true);

// 6. DEPENDENCIES
try {
    debug_sync("CARGANDO_SISTEMA...");
    require_once 'core.php'; 
    require_once 'sync_engine/core_logic.php';
    require_once 'sync_engine/scanner.php';
    require_once 'sync_engine/ftp_engine.php';
    require_once 'sync_engine/db_engine.php';
    require_once 'sync_engine/intelligence.php';
    debug_sync("SISTEMA_LISTO");
} catch (Exception $e) {
    debug_sync("EXCEPTION_DEP: " . $e->getMessage());
    echo "data: " . json_encode(['status' => 'error', 'msg' => "DEP_FAIL: " . $e->getMessage()]) . "\n\n";
    @flush(); exit;
}

$diag = PeonEnv::getDiagnostics();
define('PROJECTS_BASE_DIR', !empty($diag['root']) ? $diag['root'] : (__DIR__ . '/..'));
define('MYSQLDUMP_PATH', $diag['mysqldump_bin']);
define('MYSQL_PATH', $diag['mysql_bin']);

if (!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR, 0777, true);

// 7. ACTION ROUTING
$action = $_GET['action'] ?? null;
$response = ['status' => 'error', 'message' => 'Acción desconocida'];

if ($action) {
    debug_sync("ENRUTANDO: $action");
    try {
        switch ($action) {
            case 'list': $response = ['status' => 'success', 'projects' => get_managed_projects()]; break;
            case 'scan': $response = ['status' => 'success', 'found' => scan_projects($isStream)]; break;
            case 'get_config':
                $project = $_GET['project'] ?? '';
                $response = ['status' => 'success', 'config' => get_project_full_config($project)];
                break;
            case 'test_ftp':
                $conn = @ftp_connect($_GET['host'] ?? '', 21, 10);
                if ($conn && @ftp_login($conn, $_GET['user'] ?? '', $_GET['pass'] ?? '')) { @ftp_close($conn); $response = ['status' => 'success', 'message' => 'FTP OK']; }
                else { $response = ['status' => 'error', 'message' => 'FTP FAIL']; }
                break;
            case 'test_db':
                try {
                    $pdo = new PDO("mysql:host=".($_GET['host'] ?? 'localhost').";dbname=".($_GET['name'] ?? '').";charset=utf8mb4", $_GET['user'] ?? 'root', $_GET['pass'] ?? '', [PDO::ATTR_TIMEOUT => 5]);
                    $response = ['status' => 'success', 'message' => 'DB OK'];
                } catch (Exception $e) { $response = ['status' => 'error', 'message' => $e->getMessage()]; }
                break;
            case 'save_project_config':
                $project = $_GET['project'] ?? '';
                $data = [
                    'ftp_host' => $_GET['host'] ?? '', 'ftp_user' => $_GET['user'] ?? '', 'ftp_pass' => $_GET['pass'] ?? '', 'ftp_root' => $_GET['root'] ?? '/',
                    'db_name' => $_GET['db_name'] ?? '', 'db_user' => $_GET['db_user'] ?? '', 'db_pass' => $_GET['db_pass'] ?? '', 'db_host' => $_GET['db_host'] ?? 'localhost'
                ];
                $response = save_project_tactical_config($project, $data);
                break;
            case 'push':
                $config = get_project_full_config($_GET['project'] ?? '');
                $response = ftp_sync_recursive($config, 'push', $isStream);
                break;
            case 'db_local':
                $config = get_project_full_config($_GET['project'] ?? '');
                $response = backup_local_database($config, $isStream);
                break;
            case 'install_intelligence_pack':
                $response = install_intelligence_pack($isStream);
                break;
            case 'list_backups': $response = list_project_backups($_GET['project'] ?? ''); break;
            case 'delete_backup': $response = delete_backup_safe($_GET['file'] ?? '', $_GET['type'] ?? ''); break;
            case 'download_backup': download_backup_safe($_GET['file'] ?? '', $_GET['type'] ?? ''); exit;
        }
    } catch (Exception $e) {
        debug_sync("EXCEPTION_ACT: " . $e->getMessage());
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// 8. FINAL OUTPUT
if (!$isStream) {
    echo json_encode($response);
} else {
    $finalStatus = $response['status'] ?? 'success';
    $payload = array_merge($response, [
        'progress' => ($finalStatus === 'success' ? 100 : ($response['progress'] ?? 0)),
        'status' => $finalStatus,
        'msg' => $response['message'] ?? ($response['msg'] ?? 'FINALIZADO')
    ]);
    echo "data: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

// HELPERS (Mantenidos)
function list_project_backups($projectName) {
    $dbDir = BACKUP_DIR . '/db'; $siteDir = BACKUP_DIR . '/sites'; $backups = []; $projectName = strtoupper($projectName);
    if (is_dir($dbDir)) { foreach (array_diff(scandir($dbDir), ['.', '..']) as $f) { if (strpos(strtoupper($f), $projectName) === 0 && strpos($f, '.sql') !== false) { $backups[] = ['name' => $f, 'type' => 'db', 'size' => round(filesize($dbDir.'/'.$f)/1024, 2).' KB', 'date' => date('Y-m-d H:i', filemtime($dbDir.'/'.$f))]; } } }
    if (is_dir($siteDir)) { foreach (array_diff(scandir($siteDir), ['.', '..']) as $f) { if (strpos(strtoupper($f), $projectName) === 0) { $backups[] = ['name' => $f, 'type' => 'site', 'size' => is_dir($siteDir.'/'.$f) ? 'DIR' : round(filesize($siteDir.'/'.$f)/1024, 2).' KB', 'date' => date('Y-m-d H:i', filemtime($siteDir.'/'.$f))]; } } }
    return ['status' => 'success', 'backups' => array_reverse($backups)];
}

function delete_backup_safe($filename, $type) {
    $base = BACKUP_DIR . ($type === 'db' ? '/db' : '/sites');
    $path = realpath($base . '/' . $filename);
    if (!$path || strpos($path, realpath($base)) !== 0 || !file_exists($path)) return ['status' => 'error', 'message' => 'Acceso denegado.'];
    if (is_dir($path)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $fileinfo) { ($fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath())); }
        rmdir($path);
    } else { unlink($path); }
    return ['status' => 'success', 'message' => 'Eliminado con éxito.'];
}

function download_backup_safe($filename, $type) {
    $base = BACKUP_DIR . ($type === 'db' ? '/db/' : '/sites/');
    $path = realpath($base . $filename);
    if (!$path || strpos($path, realpath($base)) !== 0 || !file_exists($path)) { header("HTTP/1.0 404 Not Found"); exit; }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($path).'"');
    readfile($path);
    exit;
}
