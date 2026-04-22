<?php
/**
 * PEON | Núcleo de Operaciones
 * Lógica compartida para Dashboard y Organigrama.
 */

require_once 'config.php';
require_once 'env_discovery.php';
$diag = PeonEnv::getDiagnostics();

require_once 'vault.php';
require_once 'data_sync.php';

// Licensing Handshake
function is_system_authorized($env) {
    if (defined('SLX_GODMODE') && SLX_GODMODE) return true;
    return !empty($env['SIXLAN_LICENSE']) || (isset($_SESSION['authorized']) && $_SESSION['authorized']);
}

// Auto-inicialización de .env si no existe
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    $initialContent = "CEO_NAME=\"{$diag['user']}\"\n";
    @file_put_contents($envPath, $initialContent);
}

// Auto-inicialización de .htaccess si no existe
$htaccessPath = __DIR__ . '/.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccessContent = "RewriteEngine On\nRewriteBase /peon/\n\n# Security Layer\n<FilesMatch \"^\\.|\.(env|log|sql|json|bak|config|swp|txt|md)|htaccess|htpasswd$\">\n    Require all denied\n</FilesMatch>\n\n# Tactical Routes\nRewriteRule ^dashboard$ index.php [L]\nRewriteRule ^hierarchy$ organigrama.php [L]\n\n# Fallback\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . dashboard [L]\n";
    @file_put_contents($htaccessPath, $htaccessContent);
}

$env = getEnvData();
PeonEnv::applyOverrides($env); // Aplicar configuraciones personalizadas desde .env
$diag = PeonEnv::getDiagnostics(); // Refrescar diagnósticos con overrides

// Inicialización de Datos Tácticos Globales con Blindaje
$agentsRaw = getAntigravitySkills() ?: [];
$floors = getFloors($agentsRaw) ?: [];
$agentCount = count($agentsRaw);

/**
 * Helper para inyectar datos en JS
 */
function inject_system_data($floors, $agentsRaw, $agentCount, $env, $diag) {
    ?>
    <script>
        // Emergency Secure Injection
        (function() {
            try {
                window.FLOORS = <?php echo json_encode($floors, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE) ?: '[]'; ?>;
                window.ALL_AGENTS = <?php echo json_encode($agentsRaw, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE) ?: '[]'; ?>;
                window.AGENT_COUNT = <?php echo (int)$agentCount; ?>;
                window.SECTOR_COUNT = <?php echo is_array($floors) ? count($floors) : 0; ?>;
                window.CEO_NAME = "<?php echo addslashes($env['CEO_NAME'] ?? 'COMMANDER'); ?>";
                window.TARGET_PATH = "<?php echo addslashes($diag['skills_path'] ?? ''); ?>";
                window.PEON_MD_EXISTS = <?php echo ($diag['peon_md_exists'] ?? false) ? 'true' : 'false'; ?>;
                window.SIXLAN_LICENSE = "<?php echo addslashes($env['SIXLAN_LICENSE'] ?? ''); ?>";
            } catch(e) { console.error("PEON_CRITICAL: Metadata Bridge Failed"); }
        })();
    </script>
    <?php
}

/**
 * Registra eventos tácticos en el log del sistema
 */
function audit_log($action, $projectName, $status, $details = '') {
    $logDir = __DIR__ . '/backups';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    $logFile = $logDir . '/audit.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = PeonEnv::getUserName();
    $entry = "[$timestamp] [$user] ACTION: $action | PROJECT: $projectName | STATUS: $status | DETAILS: $details\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

?>
