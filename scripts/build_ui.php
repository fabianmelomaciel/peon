<?php
/**
 * PEON TACTICAL OS - JavaScript Obfuscator / Build Tool
 * Herramienta nativa para ofuscar el dashboard principal (index.php) y prevenir robos de UI.
 */

$sourceFile = dirname(__DIR__) . '/index.php';
$buildFile = dirname(__DIR__) . '/index_build.php';

if (!file_exists($sourceFile)) {
    die("Error: No se encontró index.php\n");
}

$content = file_get_contents($sourceFile);

// Encontrar todo el bloque de <script type="text/babel"> hasta </script>
$pattern = '/<script type="text\/babel">(.*?)<\/script>/s';
if (preg_match($pattern, $content, $matches)) {
    $jsCode = $matches[1];
    
    // Ofuscación básica (Remover comentarios)
    $jsCode = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $jsCode);
    $jsCode = preg_replace('/^\s*\/\/.*$/m', '', $jsCode);
    
    // Remover saltos de línea y tabulaciones grandes
    $jsCode = str_replace(["\r\n", "\r", "\n", "\t"], " ", $jsCode);
    $jsCode = preg_replace('/\s+/', ' ', $jsCode);
    
    // Cifrar a Base64 y envolver en un evaluador dinámico de Javascript
    $payload = base64_encode(trim($jsCode));
    
    $obfuscatedScript = <<<JS
<script type="text/babel">
    /* PEON TACTICAL OS - LICENSED COMMERCIAL SOFTWARE */
    /* DO NOT REVERSE ENGINEER */
    try {
        const _0x1a2b = atob("$payload");
        const _0xscript = document.createElement("script");
        _0xscript.type = "text/babel";
        _0xscript.text = _0x1a2b;
        document.body.appendChild(_0xscript);
    } catch(e) {
        console.error("PEON_DRM_VIOLATION: Core manipulation detected.");
    }
</script>
JS;

    $newContent = preg_replace($pattern, $obfuscatedScript, $content);
    file_put_contents($buildFile, $newContent);
    echo "¡ÉXITO! Se ha generado index_build.php con el código comercial ofuscado.\n";
} else {
    echo "ERROR: No se encontró el bloque de React Babel en index.php.\n";
}
