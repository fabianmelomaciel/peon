<?php
/**
 * SYNC ENGINE | Intelligence Module
 * Remote skill injection and tactical pack installation.
 */

function install_intelligence_pack($stream = false) {
    if ($stream) send_progress(10, "Iniciando despliegue de arsenal de inteligencia...");
    
    $env = getEnvData();
    $key = $env['SIXLAN_LICENSE'] ?? '';
    
    if (empty($key)) {
        return ['status' => 'error', 'message' => 'Se requiere licencia táctica activa para descargar el paquete completo.'];
    }

    $url = SIXLAN_HUB_URL . "?action=get_tactical_pack&key=" . urlencode($key);
    $tempZip = __DIR__ . '/../tmp_pack_' . time() . '.zip';
    
    if ($stream) send_progress(30, "Conectando con Sixlan Hub y descargando paquete táctico...");

    $ctx = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
    $content = @file_get_contents($url, false, $ctx);
    
    if (!$content || strpos($content, '{"status":"error"') === 0) {
        $msg = $content ? json_decode($content, true)['message'] : 'Error de conexión con el Hub central.';
        return ['status' => 'error', 'message' => $msg];
    }

    file_put_contents($tempZip, $content);
    unset($content); 

    if ($stream) send_progress(60, "Descomprimiendo unidades tácticas y habilitando sectores especializados...", 'processing');

    $zip = new ZipArchive();
    $res = $zip->open($tempZip);
    if ($res === TRUE) {
        $diag = PeonEnv::getDiagnostics();
        $extractPath = $diag['skills_path'] . '/';
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

function inject_remote_skill($skillName, $stream = false) {
    if ($stream) send_progress(10, "Iniciando inyección remota de skill: $skillName...");
    
    $env = getEnvData();
    $key = $env['SIXLAN_LICENSE'] ?? '';
    
    if (empty($key)) return ['status' => 'error', 'message' => 'Licencia táctica requerida.'];
    
    $url = SIXLAN_HUB_URL . "?action=get_skill&key=" . urlencode($key) . "&skill=" . urlencode($skillName);
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $response = @file_get_contents($url, false, $ctx);
    
    if (!$response) return ['status' => 'error', 'message' => 'Error de conexión con el Hub central.'];
    
    $data = json_decode($response, true);
    if (($data['status'] ?? '') !== 'success') return ['status' => 'error', 'message' => $data['message'] ?? 'Error en inyección remota.'];
    
    if ($stream) send_progress(100, "Skill '$skillName' inyectada correctamente.");
    return ['status' => 'success', 'message' => "Skill '$skillName' desplegada con éxito."];
}
