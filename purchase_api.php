<?php
/**
 * PEÓN PURCHASE API
 * Procesa la compra, genera la licencia y envía el correo táctico.
 */
require_once 'core.php';
require_once 'mail_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$email = $_GET['email'] ?? '';

if ($action === 'purchase' && !empty($email)) {
    // 1. Llamar a Sixlan para generar la licencia oficial
    $sixlanUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/sixlan/api/license.php?action=purchase&email=' . urlencode($email);
    
    // Fallback local si la URL falla
    $licenseFile = dirname(__DIR__) . '/sixlan/api/license.php';
    if (file_exists($licenseFile)) {
        $_POST['email'] = $email;
        $_REQUEST['action'] = 'purchase';
        ob_start();
        include $licenseFile;
        $res = ob_get_clean();
        $data = json_decode($res, true);
    } else {
        $res = @file_get_contents($sixlanUrl);
        $data = json_decode($res, true);
    }
    
    if (isset($data['status']) && $data['status'] === 'success') {
        $license = $data['data']['license_key'] ?? $data['license'] ?? 'SLX-PEON-ERROR';
        
        // 2. Enviar Correo Táctico
        PeonMail::sendLicense($email, $license);
        
        echo json_encode(['status' => 'success', 'license' => $license, 'message' => 'Licencia generada y enviada a ' . $email]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Fallo en el servidor de licencias Sixlan.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Solicitud inválida.']);
}
?>
