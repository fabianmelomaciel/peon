<!DOCTYPE html>
<html lang="es" class="dark" style="background-color: #05050A;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PEON | <?php echo $env['CEO_NAME']; ?> - Strategic Operations</title>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/lucide@latest" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: '#FFB300',
                        obsidian: '#05050A',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>♟️</text></svg>">
    <link rel="stylesheet" href="assets/css/peon-core.css">
    <?php if (isset($extra_css)): ?>
        <link rel="stylesheet" href="assets/css/<?php echo $extra_css; ?>">
    <?php endif; ?>
    <style>
        body, html { 
            background-color: #05050A !important; 
            color: #C5C6C7; 
            margin: 0; 
            padding: 0; 
        }
        #particle-canvas {
            background: #05050A !important;
        }
        /* Fallback Layout for Tailwind failures */
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .justify-center { justify-content: center; }
        .gap-4 { gap: 1rem; }
        .gap-6 { gap: 1.5rem; }
        .fixed { position: fixed; }
        .inset-0 { top: 0; right: 0; bottom: 0; left: 0; }
        .z-\[1000\] { z-index: 1000; }
        .bg-black\/40 { background-color: rgba(0,0,0,0.4); }
        .backdrop-blur-xl { backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }
        .w-full { width: 100%; }
        .h-full { height: 100%; }
        
        /* Eliminar cualquier rastro de azul por defecto de bots o frameworks */
        .bg-blue-600, .bg-blue-500 { background-color: #FFB300 !important; color: #05050A !important; }

        /* Efecto Radar */
        @keyframes radar-pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 179, 0, 0.4); }
            70% { transform: scale(1); box-shadow: 0 0 0 20px rgba(255, 179, 0, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 179, 0, 0); }
        }
        .radar-active {
            animation: radar-pulse 2s infinite;
            border-color: var(--gold) !important;
            background: rgba(255, 179, 0, 0.1) !important;
        }
    </style>
</head>
<body class="bg-black overflow-x-hidden">
    <canvas id="particle-canvas" style="position: fixed; inset: 0; z-index: -1;"></canvas>
    <div id="root"></div>

    <?php inject_system_data($floors, $agentsRaw, $agentCount, $env, $diag); ?>

    <!-- Protocolos de Idioma Independientes -->
    <script src="lang/es.js?v=1.0.1"></script>
    <script src="lang/en.js?v=1.0.1"></script>
    <script src="lang/pt.js?v=1.0.1"></script>

    <script>
        // Consolidación de Diccionarios Tácticos (Síncrono para evitar race conditions)
        window.TRANSLATIONS = {
            es: window.TRANSLATIONS_ES || {},
            en: window.TRANSLATIONS_EN || {},
            pt: window.TRANSLATIONS_PT || {}
        };
    </script>

    <script type="text/babel">
        /**
         * COMPONENTES COMPARTIDOS - PEON
         */
        const { useState, useEffect, useRef, useCallback, useMemo } = React;

        const ICON_MAP = {
            'orquestador-maestro': 'cpu',
            'creador-de-talento': 'hammer',
            'skill-forge': 'hammer',
            'centinela-de-calidad': 'shield-check',
            'webapp-testing': 'shield-check',
            'maestro-desarrollador': 'terminal',
            'master-developer': 'terminal',
            'disenador-de-elite': 'palette',
            'estratega-visual': 'layout',
            'theme-factory': 'layout',
            'especialista-en-datos': 'bar-chart',
            'gestor-documental': 'file-text',
            'forjador-de-apps': 'box',
            'web-artifacts-builder': 'box',
            'redactor-maestro': 'edit-3',
            'mentor-de-redaccion': 'book-open',
            'vocero-de-equipo': 'megaphone',
            'internal-comms': 'megaphone',
            'secretario-de-actas': 'mic',
            'reuniones-summary': 'mic',
            'arquitecto-de-conexiones': 'share-2',
            'mcp-builder': 'share-2',
            'guardian-de-identidad': 'key',
            'brand-father': 'key',
            'presentador-ejecutivo': 'tv',
            'maestro-de-arte-ia': 'sparkles',
            'algorithmic-art': 'sparkles',
            'github': 'terminal',
            'linkedin': 'user-check',
            'peon': 'star',
            'canvas-design': 'image',
            'doc-coauthoring': 'users',
            'docx': 'file-code',
            'pdf': 'file-text',
            'pptx': 'presentation',
            'xlsx': 'table'
        };

        /**
         * StrategicIcon / PeonIcon: Renderizador seguro de iconos Lucide para React.
         * Evita el error 'removeChild' manejando su propio ciclo de vida via Ref.
         */
        const StrategicIcon = ({ name, className = "w-4 h-4", color = "currentColor" }) => {
            const iconRef = useRef(null);
            // Normalización: 1) buscar en mapa por nombre normalizado, 2) por nombre original, 3) usar nombre directo de Lucide
            const normalizedName = (name || '').toLowerCase();
            const iconName = ICON_MAP[normalizedName] || ICON_MAP[name] || normalizedName || 'info'; 
            
            useEffect(() => {
                if (iconRef.current && window.lucide) {
                    iconRef.current.innerHTML = '';
                    const i = document.createElement('i');
                    i.setAttribute('data-lucide', iconName);
                    iconRef.current.appendChild(i);
                    try {
                        window.lucide.createIcons({
                            attrs: { 
                                class: className,
                                stroke: color
                            },
                            nameAttr: 'data-lucide'
                        });
                    } catch (e) {
                        console.warn("Lucide render failed for:", iconName);
                    }
                }
            }, [iconName, className, color]);

            return <span ref={iconRef} className="inline-flex items-center justify-center pointer-events-none" />;
        };

        const t = (key) => {
            let str = (TRANSLATIONS && TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) || key;
            if (typeof str === 'string') {
                str = str.replace('{{agent_count}}', AGENT_COUNT);
                str = str.replace('{{sector_count}}', SECTOR_COUNT);
            }
            return str;
        };

        const TacticalHeader = ({ t, scanNetwork, systemStatus, lang, setLang, setShowManual, setShowSystemHub }) => (
            <header className="fixed top-0 left-0 right-0 h-20 bg-black/40 backdrop-blur-xl border-b border-white/5 z-[1000] px-4 md:px-8 flex items-center justify-between animate-in">
                <div className="flex items-center gap-3 md:gap-6">
                    <div className="w-12 h-12 md:w-16 md:h-16 logo-container bg-white/5 border border-white/10 p-2 flex items-center justify-center cursor-pointer hover:border-[#FFB300]/40 transition-all shadow-[inset_0_0_10px_rgba(255,179,0,0.05)]" onClick={() => window.location.href = 'dashboard'} style={{ minWidth: '48px', minHeight: '48px' }}>
                        <span className="text-3xl md:text-4xl filter drop-shadow-[0_0_8px_rgba(255,179,0,0.3)] select-none">♟️</span>
                    </div>
                    <div className="hidden lg:block">
                        <h1 className="text-sm md:text-xl font-bold text-white tracking-[0.3em] bebas">PEON <span className="text-[#FFB300]">v1.0</span></h1>
                        <div className="flex items-center gap-3 mt-1">
                            <div className="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-white/5 border border-white/10">
                                <span className={`w-1.5 h-1.5 rounded-full ${systemStatus.status === 'operational' ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]' : 'bg-amber-500 animate-pulse'}`}></span>
                                <span className="text-[7px] font-black uppercase text-white/50 tracking-widest">{AGENT_COUNT} {t('active') || 'ACTIVOS'}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2 md:gap-4 lg:gap-6">
                    {/* SELECTOR DE IDIOMA EN CABECERA */}
                    <div className="flex gap-1 bg-white/5 p-1 rounded-xl mr-2 md:mr-4">
                        {['es', 'en', 'pt'].map(l => (
                            <button 
                                key={l} 
                                onClick={() => setLang(l)} 
                                className={`px-2 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest transition-all ${lang === l ? 'bg-[#FFB300] text-[#05050A]' : 'text-white/20 hover:text-white/40'}`}
                            >
                                {l}
                            </button>
                        ))}
                    </div>

                    <nav className="flex gap-2 md:gap-4 mr-2 md:mr-4 border-r border-white/10 pr-2 md:pr-4">
                        <button 
                            onClick={() => window.location.href='checkout.php'}
                            title="Desplegar Licencia Maestra"
                            className={`p-2.5 md:px-6 md:py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 text-[#05050A] bg-[#FFB300] hover:scale-105 shadow-[0_0_20px_rgba(255,179,0,0.3)]`}
                        >
                            <StrategicIcon name="key" className="w-4 h-4 text-inherit" />
                            <span className="hidden sm:inline">GET LICENSE</span>
                        </button>

                        <button 
                            onClick={async () => {
                                if (confirm('¿Automatizar despliegue del Núcleo IA hacia GitHub Comercial?')) {
                                    alert('Iniciando transferencia Git. Ver la consola o HUD para progreso (Simulado).');
                                    // Aquí llamaríamos a un endpoint backend real para hacer exec('git push')
                                    try {
                                        let res = await fetch('projects_sync.php?action=deploy_github');
                                        let data = await res.json();
                                        alert(data.message || 'Despliegue finalizado.');
                                    } catch (e) {
                                        alert('Error lanzando despliegue autónomo.');
                                    }
                                }
                            }}
                            title="Desplegar Núcleo a GitHub"
                            className={`p-2.5 md:px-4 md:py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 text-[#00E676] bg-[#00E676]/10 hover:bg-[#00E676] hover:text-[#05050A] shadow-[0_0_15px_rgba(0,230,118,0.2)]`}
                        >
                            <StrategicIcon name="github" className="w-4 h-4 text-inherit" />
                            <span className="hidden sm:inline">PUBLICAR EN GITHUB</span>
                        </button>

                        <button 
                            onClick={() => window.location.href='hierarchy'} 
                            title={t('hierarchy')}
                            className={`p-2.5 md:px-4 md:py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 ${window.location.pathname.includes('hierarchy') ? 'bg-[#FFB300] text-[#05050A] shadow-lg' : 'text-white/40 hover:text-white hover:bg-white/5'}`}
                        >
                            <StrategicIcon name="network" className="w-4 h-4 text-inherit" />
                            <span className="hidden sm:inline">{t('hierarchy')}</span>
                        </button>
                    </nav>
                    <button onClick={() => setShowManual(true)} className="p-2.5 md:px-6 md:py-2 rounded-full bg-white/5 border border-white/10 text-[10px] font-black text-white/60 hover:text-white hover:bg-white/10 transition-all uppercase tracking-widest flex items-center gap-2">
                         <StrategicIcon name="book-open" className="w-4 h-4" color="#FFB300" />
                         <span className="hidden sm:inline">{t('codex')}</span>
                    </button>
                    <button onClick={scanNetwork} className={`p-2.5 md:px-6 md:py-2 rounded-full border border-[#FFB300]/40 text-[#FFB300] font-black text-[10px] uppercase tracking-[0.2em] bg-[#FFB300]/5 hover:bg-[#FFB300]/20 hover:scale-105 active:scale-95 transition-all shadow-[0_0_20px_rgba(255,179,0,0.1)] ${systemStatus.status === 'scanning' ? 'radar-active' : ''}`}>
                        <StrategicIcon name="radar" className={`w-4 h-4 inline-block md:mr-2 ${systemStatus.status === 'scanning' ? 'animate-spin' : 'animate-pulse'}`} />
                        <span className="hidden sm:inline">{systemStatus.status === 'scanning' ? t('scanning') : t('scan_net')}</span>
                    </button>
                </div>
            </header>
        );

        const SystemHub = ({ t, lang, setLang, onClose, onUninstall, installTacticalPack }) => {
            const [copied, setCopied] = useState(false);
            const getOS = () => {
                const ua = window.navigator.userAgent;
                if (ua.indexOf("Win") !== -1) return "Windows";
                if (ua.indexOf("Mac") !== -1) return "MacOS";
                if (ua.indexOf("Linux") !== -1) return "Linux";
                return "Unix";
            };

            const os = getOS();
            const getCommand = () => {
                const path = TARGET_PATH;
                if (os === "Windows") return `Remove-Item -Recurse -Force "${path}"`;
                return `rm -rf "${path}"`;
            };

            const copyCommand = (text) => {
                navigator.clipboard.writeText(text);
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            };

            return (
                <div className="modal-overlay" onClick={onClose}>
                    <div className="modal-content !max-w-4xl p-16 animate-in" onClick={e => e.stopPropagation()}>
                        <div className="flex justify-between items-start mb-12">
                            <div>
                                <h2 className="text-5xl font-bold text-white mb-1 bebas tracking-wider">{t('system_hub') || 'SYSTEM HUB'}</h2>
                                <p className="text-[10px] font-black uppercase tracking-[0.4em] text-white/20">{t('system_hub_desc') || 'CONFIGURACIÓN CENTRAL DE OPERACIONES'}</p>
                            </div>
                            <button onClick={onClose} className="p-4 rounded-2xl hover:bg-white/5 text-white/20 hover:text-white">
                                <StrategicIcon name="x" className="w-8 h-8" />
                            </button>
                        </div>

                        <div className="space-y-12 h-[500px] overflow-y-auto custom-scrollbar pr-6">
                            <div className="bg-white/[0.03] p-8 rounded-3xl border border-white/5">
                                <h3 className="text-[var(--gold)] font-black text-[10px] uppercase tracking-widest mb-6">ACTUALIZACIÓN TÁCTICA</h3>
                                <div className="flex flex-col md:flex-row items-center gap-6">
                                    <div className="p-4 bg-[var(--gold)]/10 rounded-2xl border border-[var(--gold)]/20">
                                         <StrategicIcon name="package" className="w-10 h-10 text-[var(--gold)]" />
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-white/60 text-xs leading-relaxed mb-4">Sincroniza el arsenal completo de habilidades desde Sixlan Hub. Esto descargará e instalará automáticamente el paquete de inteligencia Pro.</p>
                                        <button 
                                            onClick={() => { onClose(); installTacticalPack(); }}
                                            className="w-full md:w-auto px-8 py-3 rounded-xl bg-[var(--gold)] text-[var(--obsidian)] font-black text-[10px] uppercase tracking-widest hover:scale-105 transition-all shadow-[0_0_30px_rgba(255,179,0,0.2)]"
                                        >
                                            DESPLEGAR ARSENAL DE INTELIGENCIA (ZIP)
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-white/[0.03] p-8 rounded-3xl border border-white/5">
                                <h3 className="text-[var(--gold)] font-black text-[10px] uppercase tracking-widest mb-6">{t('language_selector')}</h3>
                                <div className="flex gap-4">
                                    {['es', 'en', 'pt'].map(l => (
                                        <button key={l} onClick={() => setLang(l)} className={`flex-1 py-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all ${lang === l ? 'bg-[var(--gold)] text-[var(--obsidian)] shadow-lg' : 'bg-white/5 text-white/20 hover:text-white/40'}`}>
                                            {t(l)}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="bg-white/[0.03] p-8 rounded-3xl border border-white/5">
                                <h3 className="text-white font-black text-[10px] uppercase tracking-widest mb-6">{t('purge_manual_title')} ({os})</h3>
                                <div className="space-y-4 mb-8">
                                    <div className="flex items-center gap-4 text-[10px] font-black text-white/40 uppercase tracking-widest">
                                        <StrategicIcon name="file-text" className="w-4 h-4" color="#FFB300" />
                                        {t('target_files_label')}
                                    </div>
                                    <div className="flex items-center gap-4 text-[10px] font-black text-white/40 uppercase tracking-widest">
                                        <StrategicIcon name="terminal" className="w-4 h-4" color="#FFB300" />
                                        {t('decommission_cmd_label')}
                                    </div>
                                </div>
                                <div className="relative group">
                                    <pre className="bg-black/60 p-6 rounded-2xl text-[10px] font-mono text-[var(--gold)] overflow-x-auto whitespace-pre-wrap border border-white/5">
                                        {getCommand()}
                                    </pre>
                                    <button 
                                        onClick={() => copyCommand(getCommand())}
                                        className={`absolute top-4 right-4 px-4 py-2 rounded-lg text-[8px] font-black uppercase tracking-widest transition-all ${copied ? 'bg-green-500 text-white' : 'bg-white/10 text-white/40 hover:bg-white/20'}`}
                                    >
                                        {copied ? t('copied_label') : t('copy_label')}
                                    </button>
                                </div>
                            </div>

                            <div className="bg-red-500/5 p-8 rounded-3xl border border-red-500/10">
                                <h3 className="text-red-500 font-black text-[10px] uppercase tracking-widest mb-4">ZONA DE PELIGRO (DANGER ZONE)</h3>
                                <p className="text-red-500/60 text-xs leading-relaxed mb-8">El Botón de Purga eliminará permanentemente todas las habilidades del agente en la ruta especificada. PEON y sus configuraciones se mantendrán intactos.</p>
                                <button onClick={onUninstall} className="w-full py-4 rounded-xl bg-red-600/20 border border-red-600/30 text-red-500 font-black text-xs uppercase tracking-widest hover:bg-red-600 hover:text-white transition-all">
                                    EJECUTAR PURGA DE HABILIDADES
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            );
        };
        
        const CodexPeon = ({ t, lang, onClose, onUninstall }) => {
            const [copied, setCopied] = useState(false);
            const [copiedInstall, setCopiedInstall] = useState(false);

            const getOS = () => {
                const ua = window.navigator.userAgent;
                if (ua.indexOf("Win") !== -1) return "Windows";
                if (ua.indexOf("Mac") !== -1) return "MacOS";
                if (ua.indexOf("Linux") !== -1) return "Linux";
                return "Unix";
            };

            const os = getOS();
            const getPurgeCommand = () => {
                const path = TARGET_PATH;
                if (os === "Windows") return `Remove-Item -Recurse -Force "${path}"`;
                return `rm -rf "${path}"`;
            };

            const getInstallPrompt = () => t('installer_step_3_prompt') || `Peón, activa el centro de operaciones estratégicas, analiza e instala a mis ${AGENT_COUNT} unidades tácticas.`;

            const copyToClipboard = (text, setter) => {
                navigator.clipboard.writeText(text);
                setter(true);
                setTimeout(() => setter(false), 2000);
            };

            return (
                <div className="modal-overlay" onClick={onClose}>
                    <div className="modal-content !max-w-4xl p-16 animate-in" onClick={e => e.stopPropagation()}>
                        <div className="flex justify-between items-start mb-12">
                            <div>
                                <h2 className="text-5xl font-bold text-white mb-1 bebas tracking-wider">{t('codex_title')}</h2>
                                <p className="text-[10px] font-black uppercase tracking-[0.4em] text-white/20">{t('codex_subtitle')}</p>
                            </div>
                            <button onClick={onClose} className="p-4 rounded-2xl hover:bg-white/5 text-white/20 hover:text-white">
                                <StrategicIcon name="x" className="w-8 h-8" />
                            </button>
                        </div>

                        <div className="space-y-12 h-[500px] overflow-y-auto custom-scrollbar pr-6">
                            <section className="bg-white/[0.03] p-8 rounded-3xl border border-white/5">
                                <h3 className="text-[var(--gold)] font-black text-[10px] uppercase tracking-widest mb-6">{t('codex_protocol_title')}</h3>
                                <p className="text-white/60 text-sm leading-relaxed">
                                    {t('codex_protocol_desc')}
                                </p>
                            </section>

                            <section className="bg-[var(--gold)]/5 p-8 rounded-3xl border border-[var(--gold)]/20">
                                <h3 className="text-[var(--gold)] font-black text-[10px] uppercase tracking-widest mb-4">{t('codex_maintenance_title')}</h3>
                                <div className="space-y-6">
                                    {/* Purge Section */}
                                    <div className="bg-black/40 p-6 rounded-2xl border border-white/5">
                                        <h4 className="text-[10px] font-black text-white uppercase tracking-widest mb-3 flex items-center gap-2">
                                            <StrategicIcon name="trash-2" className="w-4 h-4" color="#ef4444" /> {t('codex_purge_title')}
                                        </h4>
                                        <p className="text-[10px] text-white/40 mb-4 uppercase tracking-wider">{t('codex_purge_desc')}</p>
                                        <div className="relative group">
                                            <pre className="bg-black/60 p-4 rounded-xl text-[10px] font-mono text-[var(--gold)] overflow-x-auto whitespace-pre-wrap border border-white/5">
                                                {getPurgeCommand()}
                                            </pre>
                                            <button 
                                                onClick={() => copyToClipboard(getPurgeCommand(), setCopied)}
                                                className={`absolute top-2 right-2 px-3 py-1.5 rounded-lg text-[8px] font-black uppercase tracking-widest transition-all ${copied ? 'bg-green-500 text-white' : 'bg-white/10 text-white/40 hover:bg-white/20'}`}
                                            >
                                                {copied ? t('copied_label') : t('copy_label')}
                                            </button>
                                        </div>
                                        <button 
                                            onClick={onUninstall}
                                            className="w-full mt-4 py-3 rounded-xl bg-red-600/10 border border-red-600/20 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-600 hover:text-white transition-all"
                                        >
                                            {t('codex_purge_btn')}
                                        </button>
                                    </div>

                                    {/* Install Section */}
                                    <div className="bg-black/40 p-6 rounded-2xl border border-white/5">
                                        <h4 className="text-[10px] font-black text-white uppercase tracking-widest mb-3 flex items-center gap-2">
                                            <StrategicIcon name="download" className="w-4 h-4" color="#22c55e" /> {t('codex_install_title')}
                                        </h4>
                                        <p className="text-[10px] text-white/40 mb-4 uppercase tracking-wider">{t('codex_install_desc')}</p>
                                        <div className="relative group">
                                            <pre className="bg-black/60 p-4 rounded-xl text-[10px] font-mono text-white/60 overflow-x-auto whitespace-pre-wrap border border-white/5 h-32 overflow-y-auto custom-scrollbar">
                                                {getInstallPrompt()}
                                            </pre>
                                            <button 
                                                onClick={() => copyToClipboard(getInstallPrompt(), setCopiedInstall)}
                                                className={`absolute top-2 right-2 px-3 py-1.5 rounded-lg text-[8px] font-black uppercase tracking-widest transition-all ${copiedInstall ? 'bg-green-500 text-white' : 'bg-white/10 text-white/40 hover:bg-white/20'}`}
                                            >
                                                {copiedInstall ? t('copied_label') : t('codex_copy_prompt_btn')}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="grid grid-cols-2 gap-6">
                                <div className="bg-white/[0.03] p-8 rounded-3xl border border-white/5">
                                    <h4 className="text-white font-black text-[10px] uppercase tracking-widest mb-4">{t('codex_structure_title')}</h4>
                                    <ul className="text-white/40 text-[10px] space-y-3 font-bold uppercase tracking-widest">
                                        <li><span className="text-[var(--gold)] mr-2">SECTOR 5:</span> {t('sector_5_name')}</li>
                                        <li><span className="text-[var(--gold)] mr-2">SECTOR 4:</span> {t('sector_4_name')}</li>
                                        <li><span className="text-[var(--gold)] mr-2">SECTOR 3:</span> {t('sector_3_name')}</li>
                                        <li><span className="text-[var(--gold)] mr-2">SECTOR 2:</span> {t('sector_2_name')}</li>
                                        <li><span className="text-[var(--gold)] mr-2">SECTOR 1:</span> {t('sector_1_name')}</li>
                                    </ul>
                                </div>
                                <div className="bg-white/[0.03] p-8 rounded-3xl border border-white/5">
                                    <h4 className="text-white font-black text-[10px] uppercase tracking-widest mb-4">{t('codex_external_systems_title')}</h4>
                                    <p className="text-white/40 text-[10px] font-bold uppercase tracking-widest leading-relaxed">
                                        {t('codex_external_systems_desc')}
                                    </p>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            );
        };
        
        const MissionHUD = ({ mission, onToggle, onClose, t }) => {
            const scrollRef = useRef(null);
            useEffect(() => {
                if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
            }, [mission.logs, mission.isMinimized]);

            useEffect(() => {
                if (mission.progress === 100) {
                    const timer = setTimeout(onClose, 3000);
                    return () => clearTimeout(timer);
                }
            }, [mission.progress, onClose]);

            return (
                <div className={`mission-hud group ${mission.isMinimized ? 'minimized' : ''} border border-white/10 shadow-[0_30px_100px_rgba(0,0,0,0.8)] overflow-hidden`}>
                    {/* CRT Scanline Effect */}
                    <div className="absolute inset-0 pointer-events-none opacity-[0.03] z-50 bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.25)_50%),linear-gradient(90deg,rgba(255,0,0,0.06),rgba(0,255,0,0.02),rgba(0,0,255,0.06))] bg-[length:100%_2px,3px_100%]"></div>
                    
                    <div className="hud-header relative z-10 backdrop-blur-md bg-black/60 border-b border-white/5">
                        <div className="flex items-center gap-4">
                            <div className="relative">
                                <span className="absolute inset-0 bg-[var(--gold)] rounded-full animate-ping opacity-20"></span>
                                <span className="relative w-2.5 h-2.5 rounded-full bg-[var(--gold)] block shadow-[0_0_10px_var(--gold)]"></span>
                            </div>
                            <span className="hud-title-text text-[10px] bebas tracking-[0.2em]">{mission.type} <span className="text-white/40">{t('mission_operation')}</span></span>
                        </div>
                        <div className="flex items-center gap-2">
                            <button onClick={onToggle} className="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/5">
                                <StrategicIcon name={mission.isMinimized ? 'maximize-2' : 'minimize-2'} className="w-3.5 h-3.5 text-white/40" />
                            </button>
                            <button onClick={onClose} className="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center hover:bg-red-500 hover:text-black transition-all border border-red-500/20">
                                <StrategicIcon name="x" className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    {!mission.isMinimized && (
                        <div className="animate-in fade-in zoom-in-95 duration-300 p-6">
                            <div className="hud-agent-card bg-white/[0.02] border border-white/5 p-4 rounded-2xl mb-6 flex items-center gap-4 relative overflow-hidden group/agent">
                                <div className="absolute top-0 right-0 p-2 opacity-10">
                                    <StrategicIcon name="id-card" className="w-8 h-8" />
                                </div>
                                <div className="relative">
                                    <img src={mission.agent.avatar} className="w-12 h-12 rounded-xl bg-black/40 border border-[var(--gold)]/20 shadow-lg group-hover/agent:scale-105 transition-transform" />
                                    <div className="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-[#05050A] shadow-sm"></div>
                                </div>
                                <div className="flex-1">
                                    <h4 className="text-white font-black text-xs uppercase tracking-widest">{mission.agent.name}</h4>
                                    <p className="text-[var(--gold)] text-[8px] font-black uppercase tracking-[0.3em] mt-1">{mission.agent.role}</p>
                                </div>
                            </div>

                            {mission.previewFiles && mission.previewFiles.length > 0 && (
                                <div className="mt-4 p-4 rounded-2xl bg-black/40 border border-white/5 max-h-40 overflow-y-auto custom-scrollbar mb-6 group/files">
                                    <div className="flex items-center justify-between mb-3">
                                        <h5 className="text-[8px] font-black text-[var(--gold)] uppercase tracking-[0.2em]">{t('files_affected') || 'ARCHIVOS_AFECTADOS'}</h5>
                                        <span className="text-[8px] font-mono text-white/20">CTRL_Z: ENABLED</span>
                                    </div>
                                    <div className="space-y-1.5">
                                        {mission.previewFiles.map((f, i) => (
                                            <div key={i} className="text-[9px] font-mono text-white/30 flex items-center gap-3 hover:text-white/60 transition-colors">
                                                <StrategicIcon name="file-code" className="w-3 h-3 text-[var(--gold)]/20" /> 
                                                <span className="truncate">{f}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="bg-black/60 rounded-2xl border border-white/5 p-4 mb-6 relative">
                                <div className="absolute top-2 right-4 text-[8px] font-mono text-white/10 uppercase italic">Live_Telemetry_Feed</div>
                                <div className="telemetry-container custom-scrollbar !h-48 !bg-transparent !p-0" ref={scrollRef}>
                                    {mission.logs?.map((log, i) => (
                                        <div key={i} className="telemetry-line flex items-start gap-3 py-1.5 border-b border-white/[0.02] last:border-0">
                                            <span className="text-[var(--gold)] font-mono text-[8px] opacity-40 shrink-0">[{new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'})}]</span>
                                            <span className="text-[10px] font-medium text-white/50 leading-relaxed font-mono">
                                                <span className="text-emerald-500 mr-2">❯</span>
                                                {log}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="flex justify-between items-end">
                                    <div className="flex flex-col gap-1">
                                        <span className="text-[8px] font-black text-white/20 uppercase tracking-[0.4em]">{t('progress_status')}</span>
                                        <span className="text-[10px] font-black text-white uppercase tracking-widest">{mission.statusMsg}</span>
                                    </div>
                                    <span className="text-2xl font-black text-[var(--gold)] bebas tracking-wider">{mission.progress}%</span>
                                </div>
                                <div className="h-2 bg-white/5 rounded-full overflow-hidden p-0.5 border border-white/5 relative">
                                    <div 
                                        className="h-full bg-gradient-to-r from-[var(--gold)] to-orange-500 rounded-full transition-all duration-500 relative" 
                                        style={{ width: `${mission.progress}%` }}
                                    >
                                        <div className="absolute inset-0 bg-[linear-gradient(90deg,transparent,rgba(255,255,255,0.4),transparent)] animate-[shimmer_2s_infinite]"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {mission.isMinimized && (
                         <div className="p-4 bg-black/80 backdrop-blur-xl flex items-center justify-between">
                            <div className="flex flex-col gap-1 flex-1 mr-6">
                                <div className="flex items-center justify-between mb-1">
                                    <span className="text-[8px] font-black text-white/40 uppercase tracking-widest">{mission.statusMsg}</span>
                                    <span className="text-[9px] font-black text-[var(--gold)]">{mission.progress}%</span>
                                </div>
                                <div className="h-1 bg-white/5 rounded-full overflow-hidden">
                                    <div className="h-full bg-[var(--gold)] transition-all duration-500" style={{ width: `${mission.progress}%` }}></div>
                                </div>
                            </div>
                            <button onClick={onToggle} className="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all">
                                <StrategicIcon name="maximize-2" className="w-3.5 h-3.5 text-white/40" />
                            </button>
                         </div>
                    )}
                </div>
            );
        };
        
        const AgentDossier = ({ agent, onClose, runMission, t }) => {
            if (!agent) return null;
            return (
                <div className="modal-overlay fixed inset-0 z-[5000] flex items-center justify-center p-4 bg-black/90 backdrop-blur-2xl overflow-y-auto custom-scrollbar" onClick={onClose}>
                    <div className="modal-content relative bg-[#05050A] w-full max-w-4xl rounded-[3rem] border border-white/5 shadow-2xl overflow-hidden my-auto" onClick={e => e.stopPropagation()}>
                        <button 
                            onClick={onClose} 
                            className="absolute top-8 right-8 w-12 h-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center hover:bg-red-500/20 hover:text-red-500 transition-all z-[5200]"
                        >
                            <StrategicIcon name="x" className="w-6 h-6" />
                        </button>
                        <div className="relative h-48 bg-gradient-to-br from-[var(--gold)]/10 to-transparent flex items-center justify-center p-8">
                            <img 
                                src={`https://api.dicebear.com/7.x/${agent.avatarStyle || 'bottts'}/svg?seed=${agent.seed}&backgroundColor=05050A`} 
                                className="w-32 h-32 rounded-3xl shadow-2xl border-2 border-[var(--gold)]/30" 
                                style={{ width: '128px', height: '128px', minWidth: '128px', minHeight: '128px', objectFit: 'contain' }}
                            />
                        </div>
                        <div className="p-12 -mt-4 bg-[#08080C] relative rounded-t-[3rem]">
                             <div className="text-center mb-8">
                                <h3 className="text-3xl font-black text-white tracking-tighter uppercase mb-2">{t(agent.technical_name) || (agent.name && agent.name.replace(/-/g, ' ').toUpperCase())}</h3>
                                <p className="text-[var(--gold)] font-black text-[10px] tracking-[0.4em] uppercase">{t(agent.technical_name + '-role') || agent.role}</p>
                            </div>
                            <div className="grid grid-cols-2 gap-8 mb-8">
                                <div className="space-y-4">
                                    <h4 className="text-[10px] font-black text-white/20 uppercase tracking-widest">{t('psycho_profile') || 'PERFIL_PSICOLÓGICO'}</h4>
                                    <p className="text-white/60 text-sm italic leading-relaxed">"{agent.quote}"</p>
                                    <p className="text-white/40 text-xs leading-relaxed">{agent.bio}</p>
                                </div>
                                <div className="space-y-4">
                                    <h5 className="text-[10px] font-black text-white/20 uppercase tracking-widest mb-4">{t('tactical_capabilities') || 'CAPACIDADES_TÁCTICAS'}</h5>
                                    <div className="space-y-2">
                                        {(agent.examples || []).map((ex, i) => (
                                            <div key={i} className="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/5 group hover:border-[var(--gold)]/30 transition-all">
                                                <div className="flex items-center gap-3">
                                                    <span className="w-1.5 h-1.5 rounded-full bg-[var(--gold)]/30"></span>
                                                    <span className="text-[10px] text-white/60 font-medium">{ex}</span>
                                                </div>
                                                <button 
                                                    onClick={() => { runMission(agent, ex); onClose(); }} 
                                                    className="px-4 py-1.5 rounded-lg bg-[var(--gold)]/5 text-[var(--gold)] font-black text-[8px] uppercase tracking-widest opacity-0 group-hover:opacity-100 hover:bg-[var(--gold)] hover:text-black transition-all"
                                                >
                                                    {t('execute_btn')}
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                            <button onClick={onClose} className="w-full py-5 rounded-2xl bg-white/5 border border-white/10 text-white font-black text-[10px] uppercase tracking-[0.5em] hover:bg-[var(--gold)] hover:text-black transition-all">
                                {t('close_dossier') || 'CERRAR_DOSSIER'}
                            </button>
                        </div>
                    </div>
                </div>
            );
        };
        const ProjectModal = ({ project, onClose, runOp, t, backups = [], loadBackups, runMission }) => {
            if (!project) return null;
            const [excludes, setExcludes] = useState(project.excludes || '');
            const [host, setHost] = useState(project.host || 'LOCAL_ONLY');
            const [user, setUser] = useState(project.user || '');
            const [pass, setPass] = useState(project.pass || '');
            const [root, setRoot] = useState(project.root || '/');
            const [dbName, setDbName] = useState(project.db_name || project.dbname || '');
            const [dbUser, setDbUser] = useState(project.db_user || project.dbuser || '');
            const [dbPass, setDbPass] = useState(project.db_pass || project.dbpass || '');
            const [dbHost, setDbHost] = useState(project.db_host || project.dbhost || 'localhost');
            const [isTesting, setIsTesting] = useState(false);
            const [isTestingDb, setIsTestingDb] = useState(false);
            const [testResult, setTestResult] = useState(null);
            const [dbTestResult, setDbTestResult] = useState(null);
            const [isSaving, setIsSaving] = useState(false);
            
            const testConnection = () => {
                setIsTesting(true); setTestResult(null);
                fetch(`projects_sync.php?action=test_ftp&host=${encodeURIComponent(host)}&user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}`)
                    .then(r => r.json()).then(res => setTestResult(res))
                    .catch(() => setTestResult({ status: 'error', message: 'ERROR_SENSOR: Timeout.' }))
                    .finally(() => setIsTesting(false));
            };

            const testDbConnection = () => {
                setIsTestingDb(true); setDbTestResult(null);
                fetch(`projects_sync.php?action=test_db&host=${encodeURIComponent(dbHost)}&user=${encodeURIComponent(dbUser)}&pass=${encodeURIComponent(dbPass)}&db=${encodeURIComponent(dbName)}`)
                    .then(r => r.json()).then(res => setDbTestResult(res))
                    .catch(() => setDbTestResult({ status: 'error', message: 'ERROR_SENSOR_DB: Timeout.' }))
                    .finally(() => setIsTestingDb(false));
            };

            const saveConfig = () => {
                setIsSaving(true);
                const params = new URLSearchParams({
                    action: 'save_project_config', project: project.name, host, user, pass, root, excludes,
                    db_name: dbName, db_user: dbUser, db_pass: dbPass, db_host: dbHost
                });
                fetch(`projects_sync.php?${params.toString()}`).then(r => r.json())
                    .then(res => { if (res.status === 'success') { alert(t('config_saved') || 'Configuración actualizada.'); window.location.reload(); } else { alert(res.message); } })
                    .finally(() => setIsSaving(false));
            };

            const deleteBackup = async (filename, type) => {
                if (!confirm(t('restore_confirm')?.replace('RESTAURACIÓN', 'ELIMINACIÓN') || '¿CONFIRMAR ELIMINACIÓN?')) return;
                try {
                    const res = await fetch(`projects_sync.php?action=delete_backup&project=${project.name}&file=${filename}&type=${type}`);
                    const data = await res.json();
                    if (data.status === 'success') {
                        loadBackups(project.name); // Refresh list
                    } else {
                        alert(data.message);
                    }
                } catch (e) {
                    console.error(e);
                }
            };

            return (
                <div className="fixed inset-0 z-[5000] flex items-center justify-center p-4 md:p-12 bg-black/95 backdrop-blur-2xl animate-in overflow-y-auto custom-scrollbar" onClick={onClose}>
                    <div className="bg-[var(--obsidian)] w-full max-w-7xl md:rounded-[3rem] border border-white/5 shadow-2xl overflow-hidden flex flex-col md:flex-row shadow-[0_50px_100px_rgba(0,0,0,0.5)] my-auto" onClick={e => e.stopPropagation()}>
                        <div className="w-full md:w-96 bg-white/[0.02] border-r border-white/5 p-8 md:p-12 flex flex-col overflow-y-auto custom-scrollbar">
                            <div className="flex items-center gap-6 mb-12">
                                <div className="w-20 h-20 rounded-[2rem] bg-gradient-to-br from-[var(--gold)]/20 to-transparent border border-[var(--gold)]/30 flex items-center justify-center shadow-[0_0_30px_rgba(255,179,0,0.1)]">
                                    <StrategicIcon name="folder-git" className="w-10 h-10 text-[var(--gold)]" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-[10px] tracking-[0.4em] text-[var(--gold)] font-black uppercase mb-1">{t('proj_id')}</h3>
                                    <h2 className="text-3xl font-black text-white uppercase tracking-tighter truncate leading-none">{project.name}</h2>
                                </div>
                            </div>
                            
                            <div className="space-y-6 mb-12">
                                <div className="p-5 rounded-2xl bg-white/[0.03] border border-white/5 space-y-3">
                                    <div className="flex justify-between items-center"><span className="text-[10px] font-black text-white/20 uppercase tracking-widest">ENDPOINT</span> <span className="text-white font-mono text-[10px] text-right truncate w-32">{project.host || 'LOCAL'}</span></div>
                                    <div className="flex justify-between items-center"><span className="text-[10px] font-black text-white/20 uppercase tracking-widest">{t('status_ready').toUpperCase()}</span> <span className={`${project.host === 'LOCAL_ONLY' ? 'text-red-500' : 'text-emerald-500'} font-black text-[10px]`}>{project.host === 'LOCAL_ONLY' ? t('disconnected').toUpperCase() : 'ENCRYPTED_OK'}</span></div>
                                </div>
                            </div>

                            <div className="space-y-3 mt-auto">
                                <h4 className="text-[10px] font-black text-white/20 uppercase tracking-widest mb-4">{t('tactical_ops')}</h4>
                                <div className="grid grid-cols-2 gap-3">
                                    <button disabled={project.host === 'LOCAL_ONLY'} onClick={() => runOp(project.name, 'pull')} className="p-6 rounded-2xl bg-white/[0.03] border border-white/5 hover:border-[var(--gold)]/50 hover:bg-[var(--gold)]/10 disabled:opacity-30 transition-all flex flex-col items-center group">
                                        <StrategicIcon name="download-cloud" className="w-6 h-6 mb-3 text-[var(--gold)] group-hover:scale-110 transition-transform" />
                                        <span className="text-[9px] font-black tracking-widest text-white/60 group-hover:text-white">PULL</span>
                                    </button>
                                    <button disabled={project.host === 'LOCAL_ONLY'} onClick={() => runOp(project.name, 'push')} className="p-6 rounded-2xl bg-white/[0.03] border border-white/5 hover:border-emerald-500/50 hover:bg-emerald-500/10 disabled:opacity-30 transition-all flex flex-col items-center group">
                                        <StrategicIcon name="upload-cloud" className="w-6 h-6 mb-3 text-emerald-500 group-hover:scale-110 transition-transform" />
                                        <span className="text-[9px] font-black tracking-widest text-white/60 group-hover:text-white">PUSH</span>
                                    </button>
                                </div>
                                <button disabled={project.host === 'LOCAL_ONLY'} onClick={() => runOp(project.name, 'backup_full')} className="w-full p-6 rounded-2xl bg-white/[0.03] border border-white/5 hover:border-[var(--gold)]/50 transition-all flex items-center justify-center gap-4 group">
                                    <StrategicIcon name="archive" className="w-5 h-5 text-[var(--gold)] group-hover:rotate-12 transition-transform" />
                                    <span className="text-[10px] font-black tracking-widest text-white uppercase">{t('full_sync_zip')}</span>
                                </button>
                            </div>
                        </div>

                        <div className="flex-1 flex flex-col min-w-0 bg-[#07070C]">
                            <div className="p-8 md:p-12 flex justify-between items-center border-b border-white/5">
                                <div className="flex items-center gap-4">
                                    <StrategicIcon name="settings-2" className="w-6 h-6 text-[var(--gold)]" />
                                    <h4 className="text-xl font-black text-white italic tracking-tighter uppercase">{t('ftp_config')}</h4>
                                </div>
                                <button onClick={onClose} className="w-12 h-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center hover:bg-red-500/20 hover:text-red-500 transition-all">
                                    <StrategicIcon name="x" className="w-6 h-6" />
                                </button>
                            </div>

                            <div className="flex-1 overflow-y-auto p-8 md:p-12 custom-scrollbar">
                                <div className="max-w-4xl mx-auto space-y-12">
                                    <div className="grid grid-cols-1 xl:grid-cols-2 gap-12">
                                        <div className="space-y-6">
                                            <h5 className="text-[10px] font-black text-white/40 uppercase tracking-[0.3em] flex items-center gap-3">
                                                <span className="w-1.5 h-1.5 bg-[var(--gold)] rounded-full animate-pulse"></span> {t('ftp_protocol')}
                                            </h5>
                                            <div className="space-y-4 bg-white/[0.01] border border-white/5 p-8 rounded-[2rem] shadow-2xl">
                                                <div className="space-y-2">
                                                    <label className="text-[9px] font-black text-white/20 uppercase tracking-widest ml-1">{t('host_address')}</label>
                                                    <input value={host} onChange={e => setHost(e.target.value)} className="w-full bg-black/40 border border-white/10 rounded-2xl px-6 py-4 text-xs text-white focus:border-[var(--gold)]/50 outline-none transition-all font-mono" placeholder="ftp.domain.com" />
                                                </div>
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <label className="text-[9px] font-black text-white/20 uppercase tracking-widest ml-1">{t('user')}</label>
                                                        <input value={user} onChange={e => setUser(e.target.value)} className="w-full bg-black/40 border border-white/10 rounded-2xl px-6 py-4 text-xs text-white focus:border-[var(--gold)]/50 outline-none transition-all font-mono" />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <label className="text-[9px] font-black text-white/20 uppercase tracking-widest ml-1">{t('pass')}</label>
                                                        <input type="password" value={pass} onChange={e => setPass(e.target.value)} className="w-full bg-black/40 border border-white/10 rounded-2xl px-6 py-4 text-xs text-white focus:border-[var(--gold)]/50 outline-none transition-all font-mono" />
                                                    </div>
                                                </div>
                                                <button onClick={testConnection} disabled={isTesting} className={`w-full py-4 rounded-2xl font-black text-[10px] tracking-[0.2em] uppercase transition-all flex items-center justify-center gap-3 ${isTesting ? 'bg-white/5' : (testResult?.status === 'success' ? 'bg-emerald-500/20 text-emerald-500 border border-emerald-500/30' : 'bg-white/5 hover:bg-white/10 text-white/40 border border-white/5')}`}>
                                                    {isTesting ? t('verifying') : t('test_ftp_conn')}
                                                </button>
                                                {testResult && <p className={`text-[9px] font-black uppercase text-center ${testResult.status === 'success' ? 'text-emerald-500' : 'text-red-500'}`}>{testResult.message}</p>}
                                            </div>
                                        </div>

                                        <div className="space-y-6">
                                            <h5 className="text-[10px] font-black text-white/40 uppercase tracking-[0.3em] flex items-center gap-3">
                                                <span className="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span> {t('db_protocol')}
                                            </h5>
                                            <div className="space-y-4 bg-white/[0.01] border border-white/5 p-8 rounded-[2rem] shadow-2xl">
                                                <div className="space-y-2">
                                                    <label className="text-[9px] font-black text-white/20 uppercase tracking-widest ml-1">{t('db_name_label')}</label>
                                                    <input value={dbName} onChange={e => setDbName(e.target.value)} className="w-full bg-black/40 border border-white/10 rounded-2xl px-6 py-4 text-xs text-white focus:border-blue-500/50 outline-none transition-all font-mono" placeholder="my_database" />
                                                </div>
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <label className="text-[9px] font-black text-white/20 uppercase tracking-widest ml-1">{t('db_user_label')}</label>
                                                        <input value={dbUser} onChange={e => setDbUser(e.target.value)} className="w-full bg-black/40 border border-white/10 rounded-2xl px-6 py-4 text-xs text-white focus:border-blue-500/50 outline-none transition-all font-mono" />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <label className="text-[9px] font-black text-white/20 uppercase tracking-widest ml-1">{t('db_pass_label')}</label>
                                                        <input type="password" value={dbPass} onChange={e => setDbPass(e.target.value)} className="w-full bg-black/40 border border-white/10 rounded-2xl px-6 py-4 text-xs text-white focus:border-blue-500/50 outline-none transition-all font-mono" />
                                                    </div>
                                                </div>
                                                <button onClick={testDbConnection} disabled={isTestingDb} className={`w-full py-4 rounded-2xl font-black text-[10px] tracking-[0.2em] uppercase transition-all flex items-center justify-center gap-3 ${isTestingDb ? 'bg-white/5' : (dbTestResult?.status === 'success' ? 'bg-blue-500/20 text-blue-500 border border-blue-500/30' : 'bg-white/5 hover:bg-white/10 text-white/40 border border-white/5')}`}>
                                                    {isTestingDb ? t('verifying') : t('test_db_conn')}
                                                </button>
                                                {dbTestResult && <p className={`text-[9px] font-black uppercase text-center ${dbTestResult.status === 'success' ? 'text-blue-500' : 'text-red-500'}`}>{dbTestResult.message}</p>}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                                        <div className="md:col-span-2 space-y-4">
                                            <h5 className="text-[10px] font-black text-white/20 uppercase tracking-widest flex items-center gap-3"><StrategicIcon name="shield" className="w-3 h-3 text-[var(--gold)]" /> {t('tactical_exclusions')}</h5>
                                            <textarea value={excludes} onChange={e => setExcludes(e.target.value)} className="w-full h-48 bg-black/40 border border-white/10 rounded-[2rem] p-8 text-xs font-mono text-white/60 focus:border-[var(--gold)]/50 outline-none custom-scrollbar transition-all" placeholder=".git, node_modules, temp..." />
                                        </div>
                                        <div className="space-y-4">
                                            <h5 className="text-[10px] font-black text-white/20 uppercase tracking-widest flex items-center gap-3"><StrategicIcon name="history" className="w-3 h-3 text-[var(--gold)]" /> {t('backup_audit')}</h5>
                                            <div className="space-y-3 max-h-48 overflow-y-auto custom-scrollbar pr-2">
                                                {backups.length ? backups.slice(0, 4).map((b, i) => (
                                                    <div key={i} className="p-4 bg-white/5 border border-white/5 rounded-2xl flex items-center justify-between group">
                                                        <span className="text-[10px] font-bold text-white/60 truncate w-32">{b.name}</span>
                                                        <div className="flex gap-2 opacity-0 group-hover:opacity-100 transition-all">
                                                            <button onClick={() => window.location.href = `projects_sync.php?action=download_backup&type=${b.type}&file=${b.name}`} className="p-2 text-[var(--gold)] hover:bg-[var(--gold)] hover:text-black rounded-lg transition-all" title={t('restore')}><StrategicIcon name="download" className="w-3 h-3" /></button>
                                                            <button onClick={() => deleteBackup(b.name, b.type)} className="p-2 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition-all" title={t('delete')}><StrategicIcon name="trash-2" className="w-3 h-3" /></button>
                                                        </div>
                                                    </div>
                                                )) : <div className="p-8 text-center text-[8px] font-black text-white/10 uppercase border border-dashed border-white/10 rounded-2xl">{t('no_logs')}</div>}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex justify-end gap-4 pb-12">
                                        <button onClick={onClose} className="px-10 py-5 rounded-2xl bg-white/5 text-white/40 font-black text-[10px] uppercase tracking-[0.3em] hover:bg-white/10 transition-all">{t('abort')}</button>
                                        <button onClick={saveConfig} disabled={isSaving} className="px-12 py-5 rounded-2xl bg-[var(--gold)] text-[var(--obsidian)] font-black text-[10px] uppercase tracking-[0.4em] hover:brightness-110 shadow-[0_20px_40px_rgba(255,179,0,0.2)] flex items-center gap-3 transition-all">
                                            {isSaving ? t('armor') : t('save_strategic_override')} <StrategicIcon name="save" className="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        };

        window.StrategicIcon = StrategicIcon;
        window.TacticalHeader = TacticalHeader;
        window.SystemHub = SystemHub;
        window.CodexPeon = CodexPeon;
        window.MissionHUD = MissionHUD;
        window.AgentDossier = AgentDossier;
        window.ProjectModal = ProjectModal;
    </script>
