<?php
/**
 * PEON | Utilidad de Instalador
 * Proporciona telemetría rápida sobre el estado del sistema.
 */

require_once 'core.php';

$action = $_GET['action'] ?? 'status';
$response = ['status' => 'error', 'message' => 'Acción no reconocida'];

if ($action === 'status') {
    $skills = getAntigravitySkills();
    $count = count($skills);
    
    $response = [
        'status' => ($count > 0) ? 'operational' : 'offline',
        'count' => $count,
        'os' => PHP_OS_FAMILY,
        'timestamp' => time()
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
