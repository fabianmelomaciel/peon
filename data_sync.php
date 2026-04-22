<?php
/**
 * Peón Agente Syncer v3.0
 * Fabian Melo - dynamic indexing of Antigravity skills.
 */

require_once 'env_discovery.php';

$diag = PeonEnv::getDiagnostics();
$ANTIGRAVITY_SKILLS_PATH = $diag['skills_path'];

function getEnvData() {
    $envPath = __DIR__ . "/.env";
    $diag = PeonEnv::getDiagnostics();
    $data = ['CEO_NAME' => $diag['user']]; // Dynamic Default
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $key = trim($name);
            $val = trim($value, " \t\n\r\0\x0B\"'");
            
            // Descifrar si está blindado
            $data[$key] = PeonVault::isEncrypted($val) ? PeonVault::decrypt($val) : $val;
        }
    }
    return $data;
}

/**
 * Retorna el Dossier Maestro de Metadatos para los agentes
 */
function getAgentMasterDossier() {
    return [
        'orquestador-maestro' => [
            'badge' => '♟️', 'role' => 'Director Operativo (CSO)', 'seed' => 'tactical-lead-shane', 'avatarStyle' => 'notionists',
            'quote' => 'La eficiencia no es negociable. La estrategia es el único camino al éxito.',
            'bio' => 'Líder de despliegue táctico. Especialista en orquestación de recursos múltiples y sincronización de fases de misión en tiempo real.',
            'serious' => 'Orquestador maestro de proyectos multi-agent. Detecta complejidad estructural, selecciona unidades especializadas y garantiza una ejecución precisa e iterativa.',
            'examples' => ['Orquestar despliegue de proyecto complejo', 'Diseñar fases operativas', 'Control de calidad estratégico']
        ],
        'arquitecto-de-conexiones' => [
            'badge' => '🔌', 'role' => 'Oficial de Integración de Nodos', 'seed' => 'mcp-core-link', 'avatarStyle' => 'bottts',
            'quote' => 'Estableciendo enlace seguro. Nodos externos sincronizados.',
            'bio' => 'Arquitecto de infraestructura de datos. Experto en establecer puentes de comunicación seguros entre la IA y entornos de red heterogéneos.',
            'serious' => 'Construye y optimiza servidores MCP (Model Context Protocol) para habilitar interacción avanzada con APIs, bases de datos y servicios de nube.',
            'examples' => ['Sincronizar base de datos remota', 'Integrar protocolos de red externos', 'Construir pasarela de datos segura']
        ],
        'forjador-de-apps' => [
            'badge' => '⚛️', 'role' => 'Ingeniero de Despliegue de Interfaz', 'seed' => 'frontend-armory', 'avatarStyle' => 'bottts',
            'quote' => 'Arquitectura visual consolidada. Despliegue de interfaz inminente.',
            'bio' => 'Especialista en desarrollo rápido de activos frontend. Consolida arquitecturas complejas en unidades de despliegue ligeras y altamente eficientes.',
            'serious' => 'Desarrolla aplicaciones comerciales robustas usando React, Tailwind CSS y componentes de alta fidelidad, optimizados para rendimiento extremo.',
            'examples' => ['Desplegar consola de mando interactiva', 'Construir dashboard analítico', 'Forjar interfaz de usuario reactiva']
        ],
        'centinela-de-calidad' => [
            'badge' => '🔍', 'role' => 'Auditor de Integridad Operativa', 'seed' => 'qa-sentinel-v1', 'avatarStyle' => 'bottts',
            'quote' => 'Ningún error pasará desapercibido bajo mi vigilancia.',
            'bio' => 'Estratega de aseguramiento de calidad. Ejecuta auditorías rigurosas para garantizar que cada componente cumpla con los estándares de misión más exigentes.',
            'serious' => 'Implementa protocolos de testing automatizado con Playwright para verificar integridad visual, funcional y lógica en tiempo real.',
            'examples' => ['Auditar flujo de misión crítico', 'Verificar integridad de la interfaz', 'Detectar anomalías lógicas']
        ],
        'maestro-de-arte-ia' => [
            'badge' => '🌀', 'role' => 'Generador de Visualización Algorítmica', 'seed' => 'art-combat-math', 'avatarStyle' => 'bottts',
            'quote' => 'El caos es solo una geometría que aún no comprendemos.',
            'bio' => 'Científico visual. Transformar datos crudos y ecuaciones en visualizaciones dinámicas que facilitan la comprensión de sistemas complejos.',
            'serious' => 'Crea visualizaciones de datos y arte generativo interactivo usando algoritmos avanzados basados en p5.js y lógica matemática pura.',
            'examples' => ['Generar mapeo dinámico de datos', 'Sistema de partículas informativas', 'Visualización de flujos estratégicos']
        ],
        'maestro-desarrollador' => [
            'badge' => '💻', 'role' => 'Especialista en Sistemas de Combate Lógico', 'seed' => 'core-dev-ops', 'avatarStyle' => 'bottts',
            'quote' => 'Lógica robusta, despliegue seguro. El código es la ley.',
            'bio' => 'Ingeniero de backend senior. Diseña y forja los cimientos lógicos sobre los que se construyen todas las operaciones estratégicas del sistema.',
            'serious' => 'Desarrolla lógica de negocio compleja, optimizaciones de servidor y arquitecturas de base de datos usando PHP, Java, Python y SQL.',
            'examples' => ['Optimizar motor de procesamiento global', 'Forjar seguridad de base de datos', 'Arquitectar lógica de servidor']
        ],
        'guardian-de-identidad' => [
            'badge' => '👑', 'role' => 'Oficial de Estandarización de Marca', 'seed' => 'brand-protocol-z', 'avatarStyle' => 'lorelei',
            'quote' => 'La coherencia visual es la base de nuestra autoridad estratégica.',
            'bio' => 'Custodio de la integridad visual. Asegura que cada activo generado mantenga una identidad de marca impecable y profesional.',
            'serious' => 'Define y aplica directrices de marca (Brand Books) que sirven como ley visual para todos los demás agentes desplegados.',
            'examples' => ['Establecer protocolo de identidad visual', 'Generar normas de marca unificadas', 'Definir estética estratégica']
        ],
        'estratega-visual' => [
            'badge' => '🎭', 'role' => 'Operador de Despliegue Estético', 'seed' => 'visual-tactics-x', 'avatarStyle' => 'lorelei',
            'quote' => 'Adaptando estética operativa para máximo impacto profesional.',
            'bio' => 'Diseñador de respuesta rápida. Aplica marcos visuales pre-diseñados para asegurar que cada entregable tenga una presentación de élite.',
            'serious' => 'Gestiona temas visuales dinámicos y paletas de colores estratégicas para documentos, presentaciones y activos digitales.',
            'examples' => ['Aplicar modo de visualización ejecutiva', 'Estilizar reporte estratégico', 'Unificar paleta de misión']
        ],
        'disenador-de-elite' => [
            'badge' => '🖌️', 'role' => 'Estratega de Composición Visual Superior', 'seed' => 'elite-designer-prime', 'avatarStyle' => 'lorelei',
            'quote' => 'Cada píxel debe tener un propósito estratégico.',
            'bio' => 'Especialista en diseño de alto impacto. Combina filosofía estética y composición técnica para crear piezas de diseño que comunican superioridad.',
            'serious' => 'Desarrolla conceptos visuales avanzados, posters estratégicos y arte corporativo partiendo de una narrativa conceptual sólida.',
            'examples' => ['Diseñar poster estratégico premium', 'Crear ilustración conceptual de misión', 'Generar activos visuales de élite']
        ],
        'especialista-en-datos' => [
            'badge' => '📊', 'role' => 'Analista de Infraestructura Cuantitativa', 'seed' => 'data-ops-alpha', 'avatarStyle' => 'pixel-art',
            'quote' => 'Los datos no mienten, pero solo la estrategia los hace útiles.',
            'bio' => 'Maestro en manipulación de activos numéricos. Extrae valor táctico de grandes conjuntos de datos usando herramientas de hoja de cálculo avanzadas.',
            'serious' => 'Procesamiento, limpieza y modelado de datos complejos en formatos Excel y CSV, incluyendo generación de KPIs estratégicos.',
            'examples' => ['Modelar proyección estratégica compleja', 'Audit de integridad de datos masivos', 'Generar dashboard analítico Dinámico']
        ],
        'redactor-maestro' => [
            'badge' => '📄', 'role' => 'Oficial de Documentación Estratégica', 'seed' => 'master-scribe-docx', 'avatarStyle' => 'pixel-art',
            'quote' => 'La precisión técnica en la redacción es el mapa del éxito.',
            'bio' => 'Arquitecto de documentos profesionales. Asegura que cada informe técnico y estratégico cumpla con los estándares de redacción de élite.',
            'serious' => 'Creación y gestión avanzada de documentación corporativa en formato Word (.docx) con estructuras jerárquicas impecables.',
            'examples' => ['Redactar informe de misión técnica', 'Estructurar manual operativo extenso', 'Forjar contrato estratégico']
        ],
        'presentador-ejecutivo' => [
            'badge' => '🎬', 'role' => 'Estratega de Comunicación de Impacto', 'seed' => 'executive-deck-pro', 'avatarStyle' => 'pixel-art',
            'quote' => 'Una presentación poderosa es la mejor arma de persuasión.',
            'bio' => 'Especialista en visualización de estrategias. Transforma conceptos complejos en narrativas visuales persuasivas para la toma de decisiones.',
            'serious' => 'Diseño y edición estratégica de presentaciones PowerPoint (.pptx) enfocadas en síntesis de información y narrativa de negocio.',
            'examples' => ['Crear deck de inversión estratégico', 'Transformar informe técnico a visual', 'Rediseñar narrativa corporativa']
        ],
        'gestor-documental' => [
            'badge' => '🗄️', 'role' => 'Operador de Activos Digitales Consolidados', 'seed' => 'pdf-protocol-arch', 'avatarStyle' => 'pixel-art',
            'quote' => 'Consolidando información técnica para acceso estratégico universal.',
            'bio' => 'Custodio de protocolos documentales. Gestiona la integridad, seguridad y accesibilidad de los activos portátiles (PDF) de la organización.',
            'serious' => 'Procesamiento avanzado de archivos PDF: consolidación de misiones, OCR técnico, encriptación y protección de activos.',
            'examples' => ['Extraer inteligencia de documentos OCR', 'Combinar misiones operativas', 'Audit de seguridad documental']
        ],
        'secretario-de-actas' => [
            'badge' => '🎙️', 'role' => 'Oficial de Sintetización Operativa', 'seed' => 'ops-record-intel', 'avatarStyle' => 'notionists',
            'quote' => 'Filtrando el ruido para extraer las acciones críticas de la misión.',
            'bio' => 'Analista de reuniones y comunicaciones. Extrae la inteligencia crítica de largas sesiones operativas para garantizar el seguimiento de misiones.',
            'serious' => 'Transforma transcripciones crudas y notas de campo en resúmenes tácticos con puntos de acción y responsables definidos.',
            'examples' => ['Extraer puntos de acción críticos', 'Sintetizar sesión estratégica', 'Crear informe de retrospectiva de misión']
        ],
        'mentor-de-redaccion' => [
            'badge' => '📝', 'role' => 'Asesor de Calidad Editorial Estratégica', 'seed' => 'editorial-mentor-v2', 'avatarStyle' => 'notionists',
            'quote' => 'La claridad es una ventaja competitiva en cualquier despliegue.',
            'bio' => 'Experto en co-creación de textos. Guía el proceso de redacción desde el contexto inicial hasta la validación final de legibilidad estratégica.',
            'serious' => 'Flujo de trabajo colaborativo para la producción de documentos técnicos de alta calidad con revisiones iterativas de impacto.',
            'examples' => ['Co-crear propuesta técnica compleja', 'Refinar manifiesto estratégico', 'Asegurar legibilidad de informes']
        ],
        'vocero-de-equipo' => [
            'badge' => '📢', 'role' => 'Oficial de Enlace de Comunicaciones Corporativas', 'seed' => 'pr-ops-corporate', 'avatarStyle' => 'notionists',
            'quote' => 'Comunicando con autoridad para mantener la cohesión operativa.',
            'bio' => 'Estratega de comunicaciones internas. Asegura que cada actualización de estado y comunicado oficial refuerce la visión de la organización.',
            'serious' => 'Genera informes de estado, newsletters institucionales y reportes de incidentes con tono profesional y oficial.',
            'examples' => ['Redactar reporte de estado semanal', 'Crear comunicado oficial de misión', 'Escribir informe de resiliencia operativa']
        ],
        'creador-de-talento' => [
            'badge' => '🔨', 'role' => 'Ingeniero de Forja de Nuevas Capacidades', 'seed' => 'talent-forge-engineer', 'avatarStyle' => 'big-smile',
            'quote' => 'Si falta una capacidad, la forjamos en el acto.',
            'bio' => 'Arquitecto de agentes personalizados. Diseña y despliega nuevas unidades de Skill adaptadas a los requerimientos específicos de la misión.',
            'serious' => 'Diseña arquitecturas de Skills completas, desde la lógica básica hasta scripts avanzados y empaquetado profesional.',
            'examples' => ['Forjar skill automatizada personalizada', 'Diseñar unidad técnica especializada', 'Implementar protocolo de nueva capacidad']
        ]
    ];
}

function getAntigravitySkills() {
    global $ANTIGRAVITY_SKILLS_PATH;
    $skillsDir = $ANTIGRAVITY_SKILLS_PATH;
    $agents = [];
    $AGENT_METADATA = getAgentMasterDossier();

    if (!is_dir($skillsDir)) return [];

    $folders = array_diff(scandir($skillsDir), ['.', '..']);
    
    foreach ($folders as $folder) {
        $skillPath = $skillsDir . "/" . $folder . "/SKILL.md";
        if (file_exists($skillPath)) {
            $content = file_get_contents($skillPath);
            
            $nameFromSkill = $folder;
            if (preg_match('/name:\s*(.*)/i', $content, $m)) $nameFromSkill = trim($m[1]);
            
            $agentData = $AGENT_METADATA[$folder] ?? [
                'badge' => '♟️',
                'role' => $nameFromSkill,
                'names' => ['es' => $nameFromSkill, 'en' => $nameFromSkill, 'pt' => $nameFromSkill],
                'seed' => md5($folder),
                'avatarStyle' => 'bottts',
                'quote' => 'Protocolo activo.',
                'bio' => 'Agente táctico detectado en el entorno local.',
                'serious' => 'Funcionalidad genérica detectada. Pendiente de sincronización maestra.',
                'examples' => ['Tarea 01', 'Tarea 02']
            ];

            $description = "";
            if (preg_match('/description:\s*>(.*?)(?=\n[a-z]|$)/is', $content, $m)) {
                $description = trim($m[1]);
            } elseif (preg_match('/description:\s*(.*)/i', $content, $m)) {
                $description = trim($m[1]);
            }

            $finalName = $agentData['name'] ?? $nameFromSkill;
            $agents[] = array_merge([
                'name' => $finalName,
                'names' => ['es' => $finalName, 'en' => $finalName, 'pt' => $finalName],
                'technical_name' => $folder,
                'description' => $description,
                'folder' => $folder
            ], $agentData);
        }
    }
    return $agents;
}

// Map agents to floors for UI
function getFloors($agents) {
    $floors = [
        ['id' => 5, 'name_key' => 'sector_5_name', 'dept_key' => 'sector_5_dept', 'emoji' => '🧠', 'color' => '#00E676', 'style' => 'engineering', 'agentes' => []],
        ['id' => 4, 'name_key' => 'sector_4_name', 'dept_key' => 'sector_4_dept', 'emoji' => '🎨', 'color' => '#FFB300', 'style' => 'design', 'agentes' => []],
        ['id' => 3, 'name_key' => 'sector_3_name', 'dept_key' => 'sector_3_dept', 'emoji' => '🤓', 'color' => '#636E72', 'style' => 'geeks', 'agentes' => []],
        ['id' => 2, 'name_key' => 'sector_2_name', 'dept_key' => 'sector_2_dept', 'emoji' => '👔', 'color' => '#00E676', 'style' => 'executive', 'agentes' => []],
        ['id' => 1, 'name_key' => 'sector_1_name', 'dept_key' => 'sector_1_dept', 'emoji' => '🔨', 'color' => '#FFB300', 'style' => 'hr', 'agentes' => []],
    ];

    foreach ($agents as $agent) {
        $f = 3; 
        $tName = $agent['technical_name'];
        if (in_array($tName, ['arquitecto-de-conexiones', 'forjador-de-apps', 'centinela-de-calidad', 'maestro-de-arte-ia', 'maestro-desarrollador'])) $f = 5;
        elseif (in_array($tName, ['guardian-de-identidad', 'estratega-visual', 'disenador-de-elite'])) $f = 4;
        elseif (in_array($tName, ['especialista-en-datos', 'redactor-maestro', 'presentador-ejecutivo', 'gestor-documental'])) $f = 3;
        elseif (in_array($tName, ['secretario-de-actas', 'mentor-de-redaccion', 'vocero-de-equipo'])) $f = 2;
        elseif (in_array($tName, ['creador-de-talento', 'orquestador-maestro'])) $f = 1;

        foreach ($floors as &$floor) {
            if ($floor['id'] == $f) {
                $floor['agentes'][] = $agent;
            }
        }
    }
    return $floors;
}
?>
