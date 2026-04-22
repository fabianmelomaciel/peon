<?php
require_once 'projects_sync.php';

echo "--- TEST DE INYECCIÓN DE INTELIGENCIA (PROTOTIPO) ---\n";

echo "\n1. Intentando inyectar skill con licencia VÁLIDA...\n";
$res = inject_remote_skill('orquestador-maestro', false);
print_r($res);

echo "\n2. Verificando si el archivo SKILL.md se ha creado...\n";
$filePath = __DIR__ . '/code/skills/orquestador-maestro/SKILL.md';
if (file_exists($filePath)) {
    echo "CONTENIDO INYECTADO:\n" . file_get_contents($filePath) . "\n";
} else {
    echo "ERROR: El archivo no se creó.\n";
}

// Para testear error, temporalmente cambiamos la licencia en el ENV cargado (en memoria)
echo "\n3. Intentando inyectar con licencia ERRÓNEA...\n";
putenv("SIXLAN_LICENSE=LLAVE-FALSA-123");
$resFail = inject_remote_skill('centinela-de-calidad', false);
print_r($resFail);
