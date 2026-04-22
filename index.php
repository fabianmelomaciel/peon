<?php
require_once 'core.php';
$extra_css = 'dashboard.css';
require_once 'header.php';
$targetProject = $_GET['project'] ?? null;

// Inyección de Datos Tácticos al Puente JS
inject_system_data($floors, $agentsRaw, $agentCount, $env, $diag);
?>

<script type="text/babel">
    const FileList = React.memo(({ files, t }) => {
        const { StrategicIcon } = window;
        return (
            <div className="bg-black/60 p-6 rounded-2xl border border-white/5 space-y-2 mb-8 max-h-[40vh] overflow-y-auto custom-scrollbar shadow-inner">
                <h3 className="text-[10px] font-black uppercase text-[var(--gold)] tracking-widest mb-4 flex items-center gap-2">ARCHIVOS AFECTADOS ({files?.length || 0}):</h3>
                {files?.slice(0, 300).map((f, i) => (
                    <label key={`${f}-${i}`} className="flex items-center gap-3 p-2 rounded-lg hover:bg-white/5 cursor-pointer transition-all border-b border-white/5">
                        <input type="checkbox" defaultChecked value={f} className="push-skip-cb w-3 h-3 accent-[var(--gold)] cursor-pointer" />
                        <div className="text-[10px] font-mono text-white/40 flex items-center gap-2 flex-1">
                            <StrategicIcon name="file-code" className="w-3 h-3 text-[var(--gold)]/40" />
                            <span className="truncate" title={f}>{f}</span>
                        </div>
                    </label>
                ))}
                {files?.length > 300 && (
                    <div className="p-4 text-center text-[10px] text-white/20 font-black uppercase tracking-widest">
                        + {files.length - 300} {t('more_files') || 'ARCHIVOS ADICIONALES'}
                    </div>
                )}
            </div>
        );
    });

    const App = () => {
        // Global context recovery
        const { 
            TacticalHeader, SystemHub, CodexPeon, MissionHUD, StrategicIcon, 
            TRANSLATIONS, ALL_AGENTS = [], SOURCE_PATH, TARGET_PATH, 
            ROOT_PATH, OS_FAMILY, PEON_MD_EXISTS, AgentDossier,
            AGENT_COUNT, SECTOR_COUNT 
        } = window;

        const installTacticalPack = async () => {
            if (!confirm("¿INICIAR DESPLIEGUE MASIVO DE INTELIGENCIA?\n\nEsto descargará e instalará todos los agentes especializados desde Sixlan Hub.")) return;
            
            const agent = { name: "SIXLAN_HUB", role: "VAULT_SYNCHRONIZER", avatar: "https://api.dicebear.com/7.x/identicon/svg?seed=HUB" };
            const mission = { agent, type: "DEPLOY", logs: ["ESTABLECIENDO CONEXIÓN CON BÓVEDA CENTRAL..."], progress: 5, statusMsg: "CONNECTING" };
            setActiveMission(mission);
            
            try {
                const response = await fetch(`projects_sync.php?action=install_intelligence_pack&stream=1`);
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split("\n");
                    buffer = lines.pop();
                    for (const line of lines) {
                        if (!line.trim() || !line.startsWith('data: ')) continue;
                        try {
                            const data = JSON.parse(line.substring(6));
                            if (data.progress !== undefined) setActiveMission(prev => ({ ...prev, progress: data.progress }));
                            if (data.msg) setActiveMission(prev => ({ ...prev, logs: [...(prev.logs || []), data.msg].slice(-50), statusMsg: data.msg.substring(0,30) }));
                            if (data.status === 'success') {
                                setActiveMission(prev => ({ ...prev, progress: 100, statusMsg: "SYNC_COMPLETE" }));
                                setTimeout(() => { setActiveMission(null); window.location.reload(); }, 2000);
                                return;
                            }
                        } catch (e) {}
                    }
                }
            } catch (err) {
                console.error("Pack installation failed:", err);
                setActiveMission(prev => ({ ...prev, statusMsg: "FAILED", logs: [...prev.logs, "FALLO CRÍTICO EN LA DESCARGA."] }));
            }
        };

        // Configuration
        const TARGET_PROJECT_NAME = "<?php echo addslashes((string)($targetProject ?? '')); ?>";
        const agents = React.useMemo(() => {
            if (!ALL_AGENTS) return [];
            if (Array.isArray(ALL_AGENTS)) return ALL_AGENTS;
            return Object.values(ALL_AGENTS).flat();
        }, [ALL_AGENTS]);

        // Safe Storage Helper
        const storage = {
            getItem: (key) => { try { return localStorage.getItem(key); } catch (e) { return null; } },
            setItem: (key, val) => { try { localStorage.setItem(key, val); } catch (e) { } }
        };

        const getInitialLang = () => {
            const saved = storage.getItem('peon_lang');
            if (saved) return saved;
            const browser = navigator.language || 'es';
            if (browser.startsWith('en')) return 'en';
            if (browser.startsWith('pt')) return 'pt';
            return 'es';
        };

        // ═══════════════════════════════════════
        // STATE
        // ═══════════════════════════════════════
        const [lang, setLangState] = React.useState(getInitialLang());
        const setLang = (l) => { storage.setItem('peon_lang', l); setLangState(l); };
        const [hasLicense, setHasLicense] = React.useState(() => {
            const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            return isLocal || !!window.SIXLAN_LICENSE || storage.getItem('peon_license_valid') === '1';
        });
        const [licenseKeyInput, setLicenseKeyInput] = React.useState('');
        const [checkingLicense, setCheckingLicense] = React.useState(false);
        const [view, setView] = React.useState('grid');
        const [selectedAgent, setSelectedAgent] = React.useState(null);
        const [selectedProject, setSelectedProject] = React.useState(null);
        const [showSystemHub, setShowSystemHub] = React.useState(false);
        const [showManual, setShowManual] = React.useState(false);
        const [projects, setProjects] = React.useState([]);
        const [loading, setLoading] = React.useState(false);
        const [activeMission, setActiveMission] = React.useState(null);
        const [editingProject, setEditingProject] = React.useState(null);
        const [systemStatus, setSystemStatus] = React.useState({ status: 'operational', count: 0 });
        const [backups, setBackups] = React.useState([]);
        const missionSource = React.useRef(null);
        const lastAuthFiles = React.useRef([]);

        const closeMissionSource = React.useCallback(() => {
            if (missionSource.current) {
                console.log("MOTOR_LINK: Cerrando canal de telemetría previo.");
                missionSource.current.close();
                missionSource.current = null;
            }
        }, []);

        const emergencyReset = React.useCallback(() => {
            console.warn("PROTOCOL_RESET: Limpieza forzada.");
            setView('grid');
            setActiveMission(null);
            setEditingProject(null);
            setSelectedAgent(null);
            setLoading(false);
            setShowManual(false);
            setShowSystemHub(false);
        }, []);
        window.peonEmergencyReset = emergencyReset;

        const t = (key) => {
            let str = (TRANSLATIONS && TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) || key;
            if (typeof str === 'string') {
                str = str.replace('{{agent_count}}', AGENT_COUNT);
                str = str.replace('{{sector_count}}', SECTOR_COUNT);
            }
            return str;
        };

        // ═══════════════════════════════════════
        // LOGIC
        // ═══════════════════════════════════════
        const loadProjects = React.useCallback(() => {
            fetch('projects_sync.php?action=list')
                .then(r => r.json())
                .then(data => { if (data.status === 'success') setProjects(data.projects); })
                .catch(err => console.error("Load projects failed:", err));
        }, []);

        const runMission = React.useCallback(async ({ type, agent, endpoint, isRetry = false }) => {
            setEditingProject(null);
            setSelectedAgent(null);
            setView('grid');
            if (!isRetry) {
                setActiveMission({
                    project: 'MISSION_CONTROL', type, agent,
                    logs: [t('status_mission_start') + `: ${type}...`, t('status_handshake')],
                    progress: 5, statusMsg: 'READY', isMinimized: false,
                    previewFiles: []
                });
            }
            setLoading(true);
            try {
                const response = await fetch(endpoint);
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split("\n");
                    buffer = lines.pop();
                    for (const line of lines) {
                        if (!line.trim()) continue;
                        if (!line.startsWith('data: ')) continue;
                        try {
                            const rawData = line.substring(6).trim();
                            if (!rawData.startsWith('{')) {
                                console.warn("PEON_RAW_MSG:", rawData);
                                continue;
                            }
                            const data = JSON.parse(rawData);
                            
                            // Protocolo de Solicitud de Configuración (DB/Manual)
                            if (data.status === 'request_config' && data.type === 'db') {
                                setView('db_prompt'); 
                                return; // Detener flujo para manual
                            }
                            
                            // Protocolo de Vista Previa de Sincronización
                            if (data.status === 'preview' && data.files) {
                                setActiveMission(prev => ({ ...prev, previewFiles: data.files }));
                            }

                            if (data.progress !== undefined) {
                                setActiveMission(prev => prev ? { ...prev, progress: data.progress } : prev);
                            }
                            if (data.msg) {
                                setActiveMission(prev => prev ? { 
                                    ...prev, 
                                    logs: [...(prev.logs || []), data.msg].slice(-50), 
                                    statusMsg: data.msg.substring(0, 30) 
                                } : prev);
                            }
                            if (data.status === 'success' || data.done) {
                                setLoading(false);
                                setActiveMission(prev => prev ? { ...prev, progress: 100, statusMsg: t('mission_complete') } : prev);
                                loadProjects();
                                setTimeout(() => setActiveMission(null), 2000);
                                return;
                            }
                        } catch (e) { console.error("Parse event error", e); }
                    }
                }
            } catch (err) {
                console.error("Mission failed:", err);
                setActiveMission(prev => prev ? { ...prev, logs: [...(prev.logs || []), t('status_error')], statusMsg: "FAILED" } : prev);
            } finally {
                setLoading(false);
            }
        }, [t, loadProjects]);

            const runProjectAction = (project, action, isConfirm = false, skipFiles = []) => {
            if (!action) return;
            const pName = typeof project === 'string' ? project : (project?.projectName || project?.name || 'PROJECT_UNKNOWN');
            
            console.log(`EJECUTANDO_ACCION: ${action} en ${pName} (Confirm: ${isConfirm})`);
            
            closeMissionSource();
            setEditingProject(null);
            setSelectedAgent(null);
            setShowManual(false);
            setShowSystemHub(false);

            if (!isConfirm) {
                setView('grid');
                setActiveMission({
                    agent: { name: pName, technical_name: 'project_engine' },
                    type: action.toUpperCase(),
                    projectName: pName,
                    logs: [t('status_mission_start') + ` ${action.toUpperCase()} ${t('status_in_project')}: ${pName}...`],
                    progress: 5,
                    statusMsg: t('status_connecting')
                });
            } else {
                setActiveMission(prev => prev ? { ...prev, statusMsg: 'DESPLEGANDO...', isMinimized: false } : null);
            }

            const pParam = encodeURIComponent(pName);
            const streamUrl = `projects_sync.php?action=${action}&project=${pParam}&stream=1` + (isConfirm ? '&confirm=1' : '') + (skipFiles.length > 0 ? `&skip=${encodeURIComponent(skipFiles.join(','))}` : '');
            
            // Timeout de seguridad: si en 5 minutos no termina, desbloquear la UI
            const safetyTimer = setTimeout(() => {
                console.warn('SAFETY_TIMEOUT: Mission took too long, unlocking UI');
                closeMissionSource();
                setLoading(false);
                setActiveMission(prev => prev ? { ...prev, statusMsg: 'TIMEOUT', progress: 100, logs: [...(prev.logs||[]), 'OPERACIÓN FINALIZADA (timeout de seguridad). Recarga para ver el estado.'] } : null);
            }, 5 * 60 * 1000);
            
            try {
                const es = new EventSource(streamUrl);
                missionSource.current = es;
                let errorCount = 0;

                es.onmessage = (e) => {
                    try {
                        const rawData = (e.data || '').trim();
                        if (!rawData || !rawData.startsWith('{')) return;
                        
                        const data = JSON.parse(rawData);
                        
                        if (data.status === 'request_config') {
                            if (data.type === 'db') setView('db_prompt');
                            else if (data.type === 'ftp') setView('ftp_prompt');
                            clearTimeout(safetyTimer);
                            closeMissionSource();
                            return;
                        }

                        if (data.status === 'require_auth') {
                            lastAuthFiles.current = data.files || [];
                            setActiveMission(prev => ({
                                ...prev, 
                                previewFiles: data.files || [], 
                                totalFiles: data.total_files || (data.files || []).length,
                                requireAuth: true 
                            }));
                            setView('auth_prompt');
                            clearTimeout(safetyTimer);
                            closeMissionSource();
                            return;
                        }

                        if (data.msg || data.message || data.progress) {
                            setActiveMission(prev => {
                                if (!prev) return prev;
                                const msg = data.msg || data.message;
                                const newLogs = msg ? [...prev.logs, msg].slice(-50) : prev.logs;
                                return {
                                    ...prev,
                                    progress: data.progress || prev.progress,
                                    logs: newLogs,
                                    statusMsg: data.status === 'success' ? t('mission_complete') : (data.status === 'error' ? 'ERROR' : t('status_processing'))
                                };
                            });
                        }
                        
                        // Solo cerrar en éxito o error FATAL (no en warnings de archivos individuales)
                        if (data.progress === 100 || data.status === 'success' || data.status === 'error') {
                            if (data.status === 'error' && (data.message || data.msg || '').includes('Autenticaci')) {
                                setView('ftp_prompt');
                            }
                            clearTimeout(safetyTimer);
                            closeMissionSource();
                            setLoading(false);
                            setTimeout(loadProjects, 1500);
                        }
                    } catch (err) {
                        console.warn("TELEMETRY_PARSE_ERROR", err);
                    }
                };

                es.onerror = (err) => {
                    errorCount++;
                    console.warn(`MISSION_LINK_ERR #${errorCount}`, err.type);
                    // EventSource re-conecta automáticamente. Solo forzar cierre después de 3 errores seguidos
                    if (errorCount >= 3) {
                        console.error('MISSION_LINK_LOST: Too many errors, closing.');
                        clearTimeout(safetyTimer);
                        setActiveMission(prev => prev ? { 
                            ...prev, 
                            statusMsg: 'CONNECTION_LOST', 
                            progress: 100,
                            logs: [...(prev.logs||[]), 'Se perdió la conexión con el servidor. Verifica el estado del proyecto.']
                        } : null);
                        setLoading(false);
                        closeMissionSource();
                    }
                };
            } catch (err) {
                console.error("CRITICAL_MISSION_FAULT", err);
                clearTimeout(safetyTimer);
                setActiveMission(null);
                setLoading(false);
                setView('grid');
            }
        };

        const scanNetwork = React.useCallback(() => {
            setSystemStatus({ status: 'scanning', count: 0 });
            
            runMission({
                type: t('status_mapping'),
                agent: { name: "SISTEMA" },
                endpoint: 'projects_sync.php?action=scan&stream=1'
            });

            // Re-verificar estado real después de unos segundos
            setTimeout(() => {
                fetch('installer_utility.php?action=status')
                    .then(r => r.json())
                    .then(data => setSystemStatus(data))
                    .catch(() => setSystemStatus({ status: 'operational' }));
            }, 5000);
        }, [runMission]);

        const uninstallSystem = () => {
             if (confirm(t('confirm_uninstall') || '¿Desinstalar sistema?')) {
                 window.location.reload();
             }
        };

        const initParticles = () => {
            const c = document.getElementById('particle-canvas');
            if (!c) return;
            const ctx = c.getContext('2d');
            let w, h, particles = [], mouse = { x: -1000, y: -1000 };
            const resize = () => { w = c.width = window.innerWidth; h = c.height = window.innerHeight; };
            window.addEventListener('resize', resize); resize();
            document.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });
            for (let i = 0; i < 150; i++) particles.push({
                x: Math.random() * w, y: Math.random() * h,
                vx: (Math.random() - .5) * .5, vy: (Math.random() - .5) * .5,
                r: Math.random() * 2 + 0.5, a: Math.random() * .2 + .05
            });
            const draw = () => {
                ctx.clearRect(0, 0, w, h);
                particles.forEach((p, i) => {
                    let dx = mouse.x - p.x, dy = mouse.y - p.y, dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 180) {
                        let force = (180 - dist) / 180 * .8;
                        p.vx += dx / dist * force * .15; p.vy += dy / dist * force * .15;
                        p.a = Math.min(p.a + .02, .3);
                    } else { p.a += (Math.random() * .1 + .02 - p.a) * .02; }
                    p.vx *= .98; p.vy *= .98; p.x += p.vx; p.y += p.vy;
                    if (p.x < 0) p.x = w; else if (p.x > w) p.x = 0;
                    if (p.y < 0) p.y = h; else if (p.y > h) p.y = 0;
                    ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(0,230,118,${p.a})`; ctx.fill();
                    if (dist < 200) {
                        for (let j = i + 1; j < particles.length; j++) {
                            let p2 = particles[j];
                            let d2 = Math.sqrt((p.x - p2.x) ** 2 + (p.y - p2.y) ** 2);
                            if (d2 < 100) {
                                ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p2.x, p2.y);
                                ctx.strokeStyle = `rgba(0,230,118,${.08 * (1 - d2 / 100)})`;
                                ctx.lineWidth = .5; ctx.stroke();
                            }
                        }
                    }
                });
                requestAnimationFrame(draw);
            };
            draw();
        };

        // ═══════════════════════════════════════
        // EFFECTS
        // ═══════════════════════════════════════
        React.useEffect(() => {
            const urlParams = new URLSearchParams(window.location.search);
            const key = urlParams.get('key');
            const action = urlParams.get('action');
            if (action === 'activate' && key) {
                setCheckingLicense(true);
                fetch(`projects_sync.php?action=verify_license&key=${encodeURIComponent(key)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            storage.setItem('peon_license_valid', '1');
                            setHasLicense(true);
                            window.location.href = 'index.php'; // Full reload to sync PHP
                        } else {
                            alert("FAIL_ACT: " + data.message);
                        }
                    })
                    .finally(() => setCheckingLicense(false));
            }
        }, []);

        React.useEffect(() => {
            if (hasLicense) loadProjects();
            initParticles();
        }, [hasLicense, loadProjects, lang]);

        React.useEffect(() => {
            if (TARGET_PROJECT_NAME && projects.length > 0) {
                const target = projects.find(p => p.name.toLowerCase() === TARGET_PROJECT_NAME.toLowerCase());
                if (target) { setView('projects'); setSelectedProject(target); }
            }
        }, [projects]);


        const closeProject = () => {
            setSelectedProject(null);
            if (TARGET_PROJECT_NAME) window.history.pushState({}, '', 'dashboard');
        };

        const verifyLicense = async (e) => {
            e.preventDefault();
            if (!licenseKeyInput) return;
            setCheckingLicense(true);
            try {
                const res = await fetch(`projects_sync.php?action=verify_license&key=${encodeURIComponent(licenseKeyInput)}`);
                const data = await res.json();
                if (data.status === 'success') {
                    setHasLicense(true);
                    storage.setItem('peon_license_valid', '1');
                    alert(data.message || 'Licencia validada exitosamente.');
                } else {
                    alert(data.message || 'Licencia inválida. Acceso denegado.');
                    storage.removeItem('peon_license_valid');
                }
            } catch (err) {
                alert('Error de conexión con Servidor Maestro.');
            }
            setCheckingLicense(false);
        };

        if (!hasLicense) {
            return (
                <div className="flex flex-col min-h-screen items-center justify-center p-6 bg-[#05050A] overflow-hidden">
                    {/* Atmospheric Elements */}
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(239,68,68,0.05),transparent_70%)]"></div>
                    <div className="absolute inset-0 bg-[url('assets/img/noise.png')] opacity-[0.03] mix-blend-overlay pointer-events-none"></div>
                    
                    {/* Floating HUD Elements for flavor */}
                    <div className="absolute top-10 left-10 text-[8px] font-mono text-red-500/20 uppercase tracking-[0.5em] hidden md:block">
                        <div className="flex items-center gap-2 mb-1"><span className="w-1 h-1 bg-red-500/40 rounded-full"></span> SEC_AUTH_REQUIRED</div>
                        <div className="flex items-center gap-2"><span className="w-1 h-1 bg-red-500/40 rounded-full"></span> ENCRYPTED_TUNNEL: OFFLINE</div>
                    </div>
                    <div className="absolute bottom-10 right-10 text-[8px] font-mono text-red-500/20 uppercase tracking-[0.5em] text-right hidden md:block">
                        <div>LATITUDE: 40.7128° N</div>
                        <div>LONGITUDE: 74.0060° W</div>
                    </div>

                    <div className="max-w-xl w-full bg-[#0A0A0F]/80 border border-red-500/20 rounded-[3rem] p-12 md:p-16 text-center relative backdrop-blur-3xl shadow-[0_0_100px_rgba(239,68,68,0.1)] animate-in slide-in-from-bottom duration-1000">
                        <div className="absolute -top-px left-1/2 -translate-x-1/2 w-48 h-px bg-gradient-to-r from-transparent via-red-500/50 to-transparent"></div>
                        
                        <div className="w-24 h-24 bg-red-500/5 rounded-[2rem] flex items-center justify-center mx-auto mb-10 border border-red-500/20 shadow-[inset_0_0_20px_rgba(239,68,68,0.05)] relative group">
                            <div className="absolute inset-0 bg-red-500/10 rounded-[2rem] animate-pulse"></div>
                            <StrategicIcon name="shield-alert" className="w-10 h-10 text-red-500 relative z-10" />
                        </div>

                        <h2 className="text-5xl font-black text-white mb-4 bebas tracking-[0.25em] uppercase leading-none">
                            System <span className="text-red-500">Locked</span>
                        </h2>
                        <div className="h-0.5 w-12 bg-red-500/30 mx-auto mb-6"></div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.4em] text-white/40 mb-12 leading-relaxed max-w-xs mx-auto">
                            Protocolo de seguridad activo.<br/>Licencia comercial <span className="text-white/60">Sixlan Hub</span> no detectada.
                        </p>
                        
                        <form onSubmit={verifyLicense} className="space-y-6">
                            <div className="relative group">
                                <input 
                                    type="text" 
                                    value={licenseKeyInput}
                                    onChange={(e) => setLicenseKeyInput(e.target.value.toUpperCase())}
                                    placeholder="SLX-PEON-XXXX-XXXX" 
                                    className="w-full bg-white/[0.02] border border-white/5 p-6 rounded-2xl text-center text-white font-mono text-xl tracking-[0.3em] outline-none focus:border-red-500/30 transition-all placeholder:text-white/10 shadow-inner group-hover:bg-white/[0.04]"
                                />
                                <div className="absolute inset-0 rounded-2xl border border-red-500/20 opacity-0 group-focus-within:opacity-100 transition-opacity pointer-events-none blur-sm"></div>
                            </div>
                            
                            <button 
                                disabled={checkingLicense || !licenseKeyInput}
                                type="submit"
                                className="w-full py-6 rounded-2xl bg-red-500 text-[#05050A] font-black text-[11px] uppercase tracking-[0.5em] transition-all hover:bg-red-400 hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 flex items-center justify-center gap-3 shadow-[0_20px_50px_rgba(239,68,68,0.25)]"
                            >
                                {checkingLicense ? (
                                    <>
                                        <div className="w-4 h-4 border-2 border-[#05050A]/20 border-t-[#05050A] animate-spin rounded-full"></div>
                                        <span>ENLZ_VERIFICANDO...</span>
                                    </>
                                ) : (
                                    <>
                                        <StrategicIcon name="key" className="w-4 h-4" />
                                        <span>AUTORIZAR ACCESO</span>
                                    </>
                                )}
                            </button>
                        </form>

                        <div className="mt-12 pt-10 border-t border-white/5 grid grid-cols-1 gap-6">
                            <div className="flex flex-col items-center gap-4">
                                <span className="text-[9px] text-white/20 uppercase tracking-[0.4em] font-black">Transferencia de Créditos Requerida</span>
                                <a 
                                    href="http://localhost/sixlan/index.php#pricing"
                                    className="w-full py-5 rounded-2xl bg-white/[0.03] border border-white/10 text-white font-black text-[10px] uppercase tracking-[0.4em] hover:bg-[var(--gold)] hover:text-black hover:border-[var(--gold)] transition-all flex items-center justify-center gap-3 group"
                                >
                                    <StrategicIcon name="shopping-cart" className="w-4 h-4 text-[var(--gold)] group-hover:text-black transition-colors" />
                                    ADQUIRIR LICENCIA TÁCTICA
                                </a>
                            </div>
                        </div>

                        <div className="mt-12 flex items-center justify-center gap-6 opacity-20">
                            <StrategicIcon name="shield-check" className="w-4 h-4" />
                            <span className="text-[8px] font-mono tracking-widest">SIXLAN_DRM_v2.4.0</span>
                            <StrategicIcon name="lock" className="w-4 h-4" />
                        </div>
                    </div>
                </div>
            );
        }

        return (
            <div className="flex flex-col min-h-screen">
                <TacticalHeader 
                    t={t} 
                    scanNetwork={scanNetwork} 
                    systemStatus={systemStatus} 
                    lang={lang} 
                    setLang={setLang} 
                    setShowManual={setShowManual} 
                    setShowSystemHub={setShowSystemHub} 
                    onReset={emergencyReset}
                />
                
                <main className="flex-1 pt-20 md:pt-28 px-4 md:px-8 pb-12 animate-in overflow-x-hidden">
                    {/* HERO TACTICO */}
                    <div className="max-w-7xl mx-auto mb-16 md:mb-24">
                        <div className="flex flex-col md:flex-row md:items-end justify-between gap-8">
                            <div className="max-w-3xl">
                                <h2 className="text-[10px] font-black text-[var(--gold)] tracking-[0.4em] uppercase mb-4 animate-pulse">{t('hero_tagline')}</h2>
                                <h1 className="text-4xl md:text-6xl font-black text-white bebas tracking-wider leading-none mb-6">
                                    {t('mission') || 'MISIÓN ESTRATÉGICA'}
                                </h1>
                                <p className="text-lg md:text-xl text-white/60 leading-relaxed font-medium">
                                    {t('hero_desc')}
                                </p>
                            </div>
                            <div className="flex gap-4">
                                <div className="p-6 rounded-3xl bg-white/[0.03] border border-white/5 text-center min-w-[120px]">
                                    <div className="text-3xl font-black text-white mb-1">{AGENT_COUNT}</div>
                                    <div className="text-[8px] font-black text-white/30 tracking-widest uppercase">{t('agents') || 'AGENTES'}</div>
                                </div>
                                <div className="p-6 rounded-3xl bg-white/[0.03] border border-white/5 text-center min-w-[120px]">
                                    <div className="text-3xl font-black text-[var(--gold)] mb-1">{SECTOR_COUNT.toString().padStart(2, '0')}</div>
                                    <div className="text-[8px] font-black text-white/30 tracking-widest uppercase">{t('sector_label') || 'SECTORES'}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-center mb-10 md:mb-16">
                        <div className="flex bg-white/[0.03] border border-white/5 p-1 md:p-1.5 rounded-full backdrop-blur-md">
                            <button onClick={() => setView('grid')} className={`flex items-center gap-2 md:gap-3 px-4 md:px-8 py-2 md:py-3 rounded-full transition-all font-bold text-[9px] md:text-[10px] uppercase tracking-widest ${view === 'grid' ? 'bg-[#FFB300] text-[#05050A] shadow-lg' : 'text-white/30 hover:text-white/60'}`}>
                                <StrategicIcon name="layout-grid" className="w-3.5 h-3.5 md:w-4 md:h-4" /> {t('agents_menu')}
                            </button>
                            <button onClick={() => setView('projects')} className={`flex items-center gap-2 md:gap-3 px-4 md:px-8 py-2 md:py-3 rounded-full transition-all font-bold text-[9px] md:text-[10px] uppercase tracking-widest ${view === 'projects' ? 'bg-[#FFB300] text-[#05050A] shadow-lg' : 'text-white/30 hover:text-white/60'}`}>
                                <StrategicIcon name="folder-git" className="w-3.5 h-3.5 md:w-4 md:h-4" /> {t('projects_menu')}
                            </button>
                        </div>
                    </div>

                    {view === 'grid' ? (
                        <div className="bento-grid-center max-w-7xl mx-auto">
                            {agents.map((agent, i) => (
                                <div key={agent.technical_name || i} onClick={() => setSelectedAgent({...agent, name: (agent.names && agent.names[lang]) || agent.name || agent.technical_name})} className={`bento-item cursor-pointer group ${i % 5 === 0 ? 'col-span-12 md:col-span-6' : 'col-span-12 md:col-span-4 lg:col-span-3'}`} style={{ padding: '1.75rem' }}>
                                    <div className="flex justify-between items-start mb-6">
                                        <div className="w-14 h-14 rounded-2xl bg-[var(--surface-hover)] border border-white/5 flex items-center justify-center group-hover:border-[var(--gold)]/30 group-hover:bg-[var(--gold)]/5 transition-all">
                                            <StrategicIcon name={agent.technical_name} className="w-7 h-7 opacity-40 group-hover:opacity-100 transition-all" />
                                        </div>
                                        <div className="text-[var(--gold)]/40 group-hover:text-[var(--gold)] transition-all text-xl">{agent.badge}</div>
                                    </div>
                                    <h3 className="text-2xl font-bold text-white mb-2 group-hover:text-[var(--gold)] transition-all flex items-center gap-2">
                                        {(agent.names && agent.names[lang]) || agent.name || agent.technical_name}
                                    </h3>
                                    <p className="text-[10px] text-[var(--gold)] font-bold tracking-widest uppercase mb-4 opacity-60 group-hover:opacity-100">{agent.role}</p>
                                    <p className="text-xs text-white/40 font-medium h-12 overflow-hidden line-clamp-2 leading-relaxed italic">"{agent.quote}"</p>
                                </div>
                            ))}
                        </div>
                    ) : projects.length > 0 ? (
                        <div className="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-in pb-20">
                            {projects.map((proj, i) => (
                                <div key={proj.name} className="relative group animate-in" style={{ animationDelay: `${i * 0.1}s` }}>
                                    <div className="absolute -inset-0.5 bg-gradient-to-br from-[var(--gold)]/20 to-transparent rounded-[2.5rem] opacity-0 group-hover:opacity-100 transition-all duration-500 blur-sm"></div>
                                    <div className="relative h-full bg-[#0A0A0F] border border-white/5 p-8 rounded-[2.5rem] hover:border-[var(--gold)]/30 hover:bg-[#0D0D14] transition-all duration-500 flex flex-col shadow-2xl overflow-hidden">
                                        <div className="absolute top-0 right-0 w-48 h-48 bg-[var(--gold)]/[0.02] blur-3xl rounded-full -mr-24 -mt-24 pointer-events-none group-hover:bg-[var(--gold)]/5 transition-all"></div>
                                        <div className="flex justify-between items-start mb-10 relative z-10">
                                            <div className="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center group-hover:bg-[var(--gold)]/10 group-hover:border-[var(--gold)]/30 transition-all shadow-inner">
                                                <StrategicIcon name="folder-git" className="w-8 h-8 text-[var(--gold)] opacity-40 group-hover:opacity-100 group-hover:scale-110 transition-all" />
                                            </div>
                                            <div className="flex flex-col items-end gap-3">
                                                <div className="px-4 py-1.5 rounded-full bg-[var(--gold)]/10 border border-[var(--gold)]/20 text-[9px] font-black text-[var(--gold)] uppercase tracking-widest shadow-[0_0_15px_rgba(255,179,0,0.1)]">
                                                    {proj.host === 'LOCAL_ONLY' ? t('local_node') : t('remote_vault')}
                                                </div>
                                                <button 
                                                    onClick={() => setEditingProject(proj)}
                                                    className="w-11 h-11 rounded-xl bg-white/5 border border-white/10 text-white/20 hover:text-[var(--gold)] hover:border-[var(--gold)]/30 hover:bg-[var(--gold)]/5 transition-all flex items-center justify-center group/btn"
                                                    title="CONFIGURACIÓN"
                                                >
                                                    <StrategicIcon name="settings" className="w-5 h-5 group-hover/btn:rotate-90 transition-transform" />
                                                </button>
                                            </div>
                                        </div>

                                        <div className="flex-1 mb-8">
                                            <div className="text-[9px] font-black text-[var(--gold)]/40 uppercase tracking-[0.2em] mb-1">{t('id_project')}</div>
                                            <h3 className="text-2xl font-black text-white mb-2 group-hover:text-[var(--gold)] transition-all tracking-tighter uppercase truncate leading-none">
                                                {proj.name}
                                            </h3>
                                            <p className="text-[10px] text-white/20 font-black uppercase tracking-widest leading-relaxed line-clamp-1 mb-4">
                                                {proj.host === 'LOCAL_ONLY' ? t('status_disconnected') : `${t('endpoint')}: ${proj.host}`}
                                            </p>
                                        </div>

                                        <div className="space-y-3 pt-8 border-t border-white/5 relative z-10">
                                            <div className="grid grid-cols-2 gap-3">
                                                <button 
                                                    onClick={() => runProjectAction(proj, 'pull')}
                                                    disabled={proj.host === 'LOCAL_ONLY'}
                                                    className="flex items-center justify-center gap-2 py-4 rounded-2xl bg-white/[0.03] border border-white/5 hover:border-emerald-500/40 hover:bg-emerald-500/5 text-emerald-500/60 hover:text-emerald-500 transition-all disabled:opacity-20 disabled:pointer-events-none group/action"
                                                >
                                                    <StrategicIcon name="download-cloud" className="w-4 h-4 group-hover/action:-translate-y-0.5 transition-transform" />
                                                    <span className="text-[9px] font-black uppercase tracking-widest">PULL</span>
                                                </button>
                                                <button 
                                                    onClick={() => runProjectAction(proj, 'push')}
                                                    disabled={proj.host === 'LOCAL_ONLY'}
                                                    title={proj.host === 'LOCAL_ONLY' ? "Configuración FTP requerida (clic en ajustes)" : "Iniciar despliegue"}
                                                    className="flex items-center justify-center gap-2 py-4 rounded-2xl bg-white/[0.03] border border-white/5 hover:border-orange-500/40 hover:bg-orange-500/5 text-orange-500/60 hover:text-orange-500 transition-all disabled:opacity-20 disabled:cursor-not-allowed group/action"
                                                >
                                                    <StrategicIcon name="upload-cloud" className="w-4 h-4 group-hover/action:translate-y-0.5 transition-transform" />
                                                    <span className="text-[9px] font-black uppercase tracking-widest">PUSH</span>
                                                </button>
                                            </div>
                                            <button 
                                                onClick={() => runProjectAction(proj, 'db_local')}
                                                className="w-full flex items-center justify-center gap-3 py-4 rounded-2xl bg-white/[0.02] border border-white/5 hover:border-blue-500/40 hover:bg-blue-500/5 text-blue-500/40 hover:text-blue-500 transition-all group/db"
                                            >
                                                <StrategicIcon name="database" className="w-4 h-4 group-hover/db:scale-110 transition-transform" />
                                                <span className="text-[9px] font-black uppercase tracking-[0.3em]">{t('db_snapshot')}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="max-w-7xl mx-auto flex flex-col items-center justify-center py-40 animate-in text-center">
                            <StrategicIcon name="folder-search" className="w-20 h-20 opacity-20 mb-8 animate-pulse text-[var(--gold)]" />
                            <h2 className="text-2xl font-bold text-white/20 uppercase tracking-[0.4em] mb-4">{t('status_no_projects')}</h2>
                            <p className="text-white/10 text-xs tracking-widest uppercase font-black">{t('status_empty_desc')}</p>
                        </div>
                    )}

                    {/* TACTICAL BRIEFING SECTION */}
                    <div className="max-w-7xl mx-auto mt-32 mb-20 animate-in">
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                            <div>
                                <h2 className="text-3xl md:text-5xl font-black text-white bebas tracking-wider mb-8 uppercase">
                                    {t('briefing_title')}
                                </h2>
                                <div className="space-y-6 text-lg text-white/50 leading-relaxed">
                                    <p>{t('briefing_p1')}</p>
                                    <p dangerouslySetInnerHTML={{ __html: t('briefing_p2').replace("'Skill'", `<strong class="text-[var(--gold)]">Skill</strong>`) }} />
                                    <p>{t('briefing_p3')}</p>
                                </div>
                                
                                <div className="mt-12 p-8 rounded-3xl bg-[var(--gold)]/5 border border-[var(--gold)]/20">
                                    <div className="flex items-center gap-4 mb-4">
                                        <div className="w-10 h-10 rounded-xl bg-[var(--gold)]/20 flex items-center justify-center">
                                            <StrategicIcon name="info" className="w-5 h-5 text-[var(--gold)]" />
                                        </div>
                                        <h4 className="text-sm font-black text-white uppercase tracking-widest">{t('logs_arquitecto') || 'LOGS_ARQUITECTO'}</h4>
                                    </div>
                                    <p className="text-sm text-white/40 italic leading-relaxed">
                                        {t('briefing_quote') || `"Este lugar terminó siendo una infraestructura táctica con unidades de élite que ejecutan con precisión quirúrgica."`}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <h3 className="text-xs font-black text-[var(--gold)] tracking-[0.4em] uppercase mb-8">{t('how_it_works')}</h3>
                                {[1, 2, 3, 4].map(step => (
                                    <div key={step} className="p-8 rounded-3xl bg-white/[0.02] border border-white/5 hover:border-[var(--gold)]/30 transition-all group">
                                        <h4 className="text-lg font-black text-white mb-2 group-hover:text-[var(--gold)] transition-all uppercase tracking-tight">
                                            {t(`step_${step}_title`)}
                                        </h4>
                                        <p className="text-sm text-white/40 leading-relaxed">
                                            {t(`step_${step}_desc`)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </main>

                {/* TACTICAL FOOTER */}
                <footer className="mt-20 border-t border-white/5 bg-black/40 backdrop-blur-3xl no-print">
                    <div className="max-w-7xl mx-auto px-4 md:px-12 py-16">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">
                            <div className="col-span-1 lg:col-span-2">
                                <div className="flex items-center gap-3 mb-6">
                                    <div className="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center">
                                        <StrategicIcon name="cpu" className="w-5 h-5 text-[var(--gold)] opacity-40" />
                                    </div>
                                    <h3 className="text-xl font-black text-white bebas tracking-wider">PEON <span className="text-[var(--gold)]">v1.0</span></h3>
                                </div>
                                <p className="text-sm text-white/40 leading-relaxed max-w-md">
                                    {t('footer_tagline')}
                                </p>
                                <div className="flex items-center gap-6 mt-8">
                                    <a href="https://github.com/FabianMelo" target="_blank" className="flex items-center gap-2 text-white/20 hover:text-[var(--gold)] transition-all">
                                         <StrategicIcon name="github" className="w-4 h-4" />
                                         <span className="text-[10px] font-black uppercase tracking-widest">{t('github_label')}</span>
                                    </a>
                                    <a href="https://linkedin.com/in/fabian-melo-aab54971" target="_blank" className="flex items-center gap-2 text-white/20 hover:text-[var(--gold)] transition-all">
                                         <StrategicIcon name="linkedin" className="w-4 h-4" />
                                         <span className="text-[10px] font-black uppercase tracking-widest">{t('linkedin_label')}</span>
                                    </a>
                                </div>
                            </div>

                            <div>
                                <h4 className="text-[10px] font-black text-white tracking-[0.4em] uppercase mb-6">{t('protocols')}</h4>
                                <ul className="space-y-4 text-xs font-bold text-white/20 uppercase tracking-widest">
                                    <li className="hover:text-[var(--gold)] cursor-pointer transition-all">{t('project_sync')}</li>
                                    <li className="hover:text-[var(--gold)] cursor-pointer transition-all">{t('db_snapshot')}</li>
                                    <li className="hover:text-[var(--gold)] cursor-pointer transition-all">{t('tactical_hierarchy')}</li>
                                    <li className="hover:text-[var(--gold)] cursor-pointer transition-all">{t('mcp_handshake')}</li>
                                </ul>
                            </div>

                            <div>
                                <h4 className="text-[10px] font-black text-white tracking-[0.4em] uppercase mb-6">{t('environment')}</h4>
                                <ul className="space-y-4 text-xs font-bold text-white/20 uppercase tracking-widest">
                                    <li className="flex items-center gap-2">
                                        <span className="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                        {t('laragon_ready')}
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <span className="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                        {t('antigravity_sync')}
                                    </li>
                                    <li className="flex items-center gap-2 text-white/10 italic">
                                        {t('hero_tagline')}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div className="pt-12 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-6">
                            <p className="text-[9px] font-black text-white/20 uppercase tracking-[0.2em]">
                                © 2026 PEON STRATEGIC OPERATIONS. {t('footer_made_with')}
                            </p>
                            <p className="text-[8px] italic text-white/10 uppercase tracking-widest">
                                {t('footer_disclaimer')}
                            </p>
                        </div>
                    </div>
                </footer>

                {selectedAgent && (
                    <AgentDossier 
                        agent={selectedAgent} 
                        onClose={() => setSelectedAgent(null)} 
                        runMission={runMission}
                        t={t} 
                    />
                )}
                {editingProject && (
                    <ProjectModal 
                        project={editingProject} 
                        onClose={() => setEditingProject(null)} 
                        t={t}
                        backups={backups}
                        loadBackups={(name) => {
                            fetch(`projects_sync.php?action=list_backups&project=${name}`)
                                .then(r => r.json())
                                .then(data => { if (data.status === 'success') setBackups(data.backups); });
                        }}
                        runOp={runProjectAction}
                        runMission={runMission}
                    />
                )}
                {showManual && <CodexPeon t={t} lang={lang} onClose={() => setShowManual(false)} onUninstall={uninstallSystem} />}
                {showSystemHub && <SystemHub t={t} lang={lang} setLang={setLang} onClose={() => setShowSystemHub(false)} onUninstall={uninstallSystem} installTacticalPack={installTacticalPack} />}
                {activeMission && view === 'grid' && <MissionHUD mission={activeMission} onToggle={() => setActiveMission(prev => ({...prev, isMinimized: !prev.isMinimized}))} onClose={() => setActiveMission(null)} t={t} />}

                {view === 'db_prompt' && (
                    <div className="modal-overlay fixed inset-0 z-[5000] flex items-center justify-center p-6 bg-black/90 backdrop-blur-2xl overflow-y-auto custom-scrollbar" onClick={() => setView('grid')}>
                        <div className="modal-content !max-w-xl p-12 border-[var(--gold)]/30 my-auto" onClick={e => e.stopPropagation()}>
                            <h2 className="text-3xl font-black text-white bebas tracking-widest mb-4">TACTICAL OVERRIDE: DATABASE</h2>
                            <p className="text-[10px] uppercase tracking-widest text-[#ef4444] font-black mb-8">No se detectó configuración en .env. Ingreso manual requerido.</p>
                            <div className="space-y-4 mb-8">
                                <input id="db_name" className="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-white outline-none focus:border-[var(--gold)]" placeholder="DATABASE NAME" />
                                <div className="grid grid-cols-2 gap-4">
                                    <input id="db_user" className="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-white outline-none focus:border-[var(--gold)]" placeholder="USER (root)" />
                                    <input id="db_pass" type="password" className="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-white outline-none focus:border-[var(--gold)]" placeholder="PASS" />
                                </div>
                            </div>
                            <div className="flex gap-4">
                                <button onClick={() => setView('grid')} className="flex-1 py-4 rounded-xl bg-white/5 text-white/40 font-black text-[10px] uppercase tracking-[0.2em]">ABORTAR</button>
                                <button 
                                    id="btn_test_db"
                                    onClick={(e) => {
                                        const n = document.getElementById('db_name').value;
                                        const u = document.getElementById('db_user').value || 'root';
                                        const p = document.getElementById('db_pass').value;
                                        if(!n) return alert("Nombre de DB requerido.");
                                        const btn = e.currentTarget;
                                        btn.innerText = "PROBANDO...";
                                        fetch(`projects_sync.php?action=test_db&name=${n}&user=${u}&pass=${p}`)
                                            .then(r => r.json())
                                            .then(res => {
                                                alert(res.message);
                                                btn.innerText = "TEST";
                                            });
                                    }}
                                    className="flex-1 py-4 rounded-xl bg-blue-500/20 text-blue-400 font-black text-[10px] uppercase tracking-[0.2em]"
                                >
                                    TEST
                                </button>
                                <button 
                                    onClick={() => {
                                        const n = document.getElementById('db_name').value;
                                        const u = document.getElementById('db_user').value || 'root';
                                        const p = document.getElementById('db_pass').value;
                                        if(!n) return alert("Nombre de DB requerido.");
                                        const endpoint = `projects_sync.php?action=db_local&project=${activeMission.projectName}&db_name=${n}&db_user=${u}&db_pass=${p}&stream=1`;
                                        setView('grid');
                                        runMission({ ...activeMission, endpoint, isRetry: true });
                                    }} 
                                    className="flex-1 py-4 rounded-xl bg-[var(--gold)] text-[var(--obsidian)] font-black text-[10px] uppercase tracking-[0.2em]"
                                >
                                    CONECTAR
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {view === 'ftp_prompt' && (
                    <div className="modal-overlay fixed inset-0 z-[5000] flex items-center justify-center p-6 bg-black/90 backdrop-blur-2xl overflow-y-auto custom-scrollbar" onClick={() => setView('grid')}>
                        <div className="modal-content !max-w-xl p-12 border-[var(--gold)]/30 my-auto" onClick={e => e.stopPropagation()}>
                            <h2 className="text-3xl font-black text-white bebas tracking-widest mb-4">TACTICAL OVERRIDE: FTP</h2>
                            <p className="text-[10px] uppercase tracking-widest text-[#ef4444] font-black mb-8">No se detectó configuración FTP en Peón. Ingreso manual requerido.</p>
                            <div className="space-y-4 mb-8">
                                <input id="ftp_host" className="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-white outline-none focus:border-[var(--gold)]" placeholder="HOST (ej. ftp.sixlan.com)" />
                                <div className="grid grid-cols-2 gap-4">
                                    <input id="ftp_user" className="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-white outline-none focus:border-[var(--gold)]" placeholder="USER" />
                                    <input id="ftp_pass" type="password" className="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-white outline-none focus:border-[var(--gold)]" placeholder="PASS" />
                                </div>
                            </div>
                            <div className="flex gap-4">
                                <button onClick={() => setView('grid')} className="flex-1 py-4 rounded-xl bg-white/5 text-white/40 font-black text-[10px] uppercase tracking-[0.2em]">ABORTAR</button>
                                <button 
                                    onClick={(e) => {
                                        const h = document.getElementById('ftp_host').value;
                                        const u = document.getElementById('ftp_user').value;
                                        const p = document.getElementById('ftp_pass').value;
                                        if(!h || !u || !p) return alert("Todos los campos FTP son requeridos.");
                                        
                                        const btn = e.currentTarget;
                                        const originalText = btn.innerText;
                                        btn.disabled = true;
                                        btn.innerText = "VERIFICANDO ENLACE...";
                                        
                                        // 1. Probar conexión antes de guardar
                                        fetch(`projects_sync.php?action=test_ftp&host=${encodeURIComponent(h)}&user=${encodeURIComponent(u)}&pass=${encodeURIComponent(p)}`)
                                        .then(r => r.json())
                                        .then(testRes => {
                                            if (testRes.status === 'success') {
                                                // 2. Si conecta, guardar configuración
                                                fetch(`projects_sync.php?action=save_project_config&project=${activeMission.projectName}&host=${encodeURIComponent(h)}&user=${encodeURIComponent(u)}&pass=${encodeURIComponent(p)}&root=/`)
                                                .then(r => r.json())
                                                .then(saveRes => {
                                                    if (saveRes.status === 'success') {
                                                        loadProjects();
                                                        setView('grid');
                                                        runProjectAction({ name: activeMission.projectName }, activeMission.type.toLowerCase());
                                                    } else {
                                                        alert("Error guardando: " + saveRes.message);
                                                        btn.disabled = false;
                                                        btn.innerText = originalText;
                                                    }
                                                });
                                            } else {
                                                // 3. Si falla conexión, mostrar error y mantener modal
                                                alert("FALLO TÁCTICO: " + testRes.message + "\n\nPor favor, verifica las credenciales e intenta de nuevo.");
                                                btn.disabled = false;
                                                btn.innerText = originalText;
                                                btn.classList.add('animate-shake');
                                                setTimeout(() => btn.classList.remove('animate-shake'), 500);
                                            }
                                        }).catch(err => {
                                            alert("Error de red o servidor inalcanzable.");
                                            btn.disabled = false;
                                            btn.innerText = originalText;
                                        });
                                    }} 
                                    className="flex-1 py-4 rounded-xl bg-[var(--gold)] text-[var(--obsidian)] font-black text-[10px] uppercase tracking-[0.2em] transition-all"
                                >
                                    VERIFICAR Y CONECTAR
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {view === 'auth_prompt' && (
                    <div className="modal-overlay fixed inset-0 z-[5000] flex items-center justify-center p-4 bg-black/95 backdrop-blur-2xl overflow-y-auto custom-scrollbar" onClick={() => { setView('grid'); setActiveMission(null); }}>
                        <div className="modal-content !max-w-2xl !w-full p-8 md:p-12 border-[var(--gold)]/30 my-8 relative" onClick={e => e.stopPropagation()}>
                            <div className="absolute -top-4 -right-4 w-24 h-24 bg-[var(--gold)]/10 blur-3xl rounded-full pointer-events-none"></div>
                            
                            <h2 className="text-3xl font-black text-white bebas tracking-widest mb-4 flex items-center justify-between">
                                TACTICAL OVERRIDE: PUSH
                                <div className="flex items-center gap-4">
                                    <span className="text-xl font-black text-[var(--gold)] bebas tracking-wider">{activeMission?.progress || 0}%</span>
                                    <span className="text-[12px] px-3 py-1 bg-red-500/20 text-red-500 rounded-lg">AUTH_REQ</span>
                                </div>
                            </h2>
                            <p className="text-[10px] uppercase tracking-widest text-white/40 font-black mb-6 border-l-2 border-[var(--gold)] pl-3">
                                PROYECTO: <span className="text-white">{activeMission?.projectName}</span> | {activeMission?.totalFiles || activeMission?.previewFiles?.length || 0} OBJETIVOS DETECTADOS
                            </p>
                            
                            {activeMission?.isDeploying ? (
                                <div className="space-y-6 animate-in fade-in duration-500">
                                    <div className="bg-black/40 p-6 rounded-2xl border border-white/5 relative overflow-hidden">
                                        <div className="absolute top-0 left-0 h-1 bg-[var(--gold)] transition-all duration-300" style={{ width: `${activeMission?.progress || 0}%` }}></div>
                                        <div className="flex justify-between items-end mb-4">
                                            <span className="text-[10px] font-black text-white/40 uppercase tracking-widest">{activeMission?.statusMsg || 'INICIANDO...'}</span>
                                            <span className="text-3xl font-black text-[var(--gold)] bebas">{activeMission?.progress || 0}%</span>
                                        </div>
                                        <div className="h-48 overflow-y-auto custom-scrollbar font-mono text-[10px] space-y-1 pr-2">
                                            {(activeMission?.logs || []).slice(-10).map((log, i) => (
                                                <div key={i} className="text-white/60 flex gap-2">
                                                    <span className="text-[var(--gold)]">❯</span> {log}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                    {activeMission?.progress === 100 && (
                                        <div className="p-4 bg-emerald-500/20 border border-emerald-500/30 rounded-xl text-emerald-500 text-center text-[10px] font-black uppercase tracking-widest animate-bounce">
                                            MISIÓN COMPLETADA CON ÉXITO
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <FileList files={lastAuthFiles.current} t={t} />
                            )}

                            <div className="flex gap-4">
                                <button onClick={() => { setView('grid'); setActiveMission(null); }} className="flex-1 py-5 rounded-2xl bg-white/5 text-white/40 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-white/10 transition-all">ABORTAR</button>
                                <button 
                                    onClick={() => {
                                        const skip = Array.from(document.querySelectorAll('.push-skip-cb:not(:checked)')).map(cb => cb.value);
                                        console.log("AUTORIZANDO PUSH CON EXCLUSIONES:", skip);
                                        // No cerramos la vista, permitimos que el modal muestre el progreso
                                        setActiveMission(prev => ({ ...prev, isDeploying: true }));
                                        runProjectAction({ name: activeMission.projectName }, activeMission.type.toLowerCase(), true, skip);
                                    }} 
                                    disabled={activeMission?.isDeploying}
                                    className={`flex-[2] py-5 rounded-2xl bg-[var(--gold)] text-[var(--obsidian)] font-black text-[12px] uppercase tracking-[0.3em] hover:brightness-110 transition-all shadow-[0_20px_40px_rgba(255,179,0,0.2)] ${activeMission?.isDeploying ? 'opacity-50 cursor-wait' : 'hover:scale-[1.02] active:scale-[0.98]'}`}
                                >
                                    {activeMission?.isDeploying ? 'DESPLEGANDO OPERACIÓN...' : 'AUTORIZAR DESPLIEGUE TÁCTICO'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {(!PEON_MD_EXISTS || agents.length === 0) && (
                    <div className="fixed inset-0 z-[2000] flex items-center justify-center p-6 backdrop-blur-3xl bg-black/80">
                         <div className="max-w-3xl w-full bg-[#05050A] border border-[var(--gold)]/30 rounded-2-5rem p-12 text-center relative overflow-hidden shadow-[0_0_100px_rgba(255,179,0,0.1)]">
                            <h2 className="text-5xl font-black text-white mb-4 bebas tracking-[0.2em] uppercase">{t('installer_title')}</h2>
                            <p className="text-[10px] font-black uppercase tracking-[0.4em] text-[var(--gold)] mb-8">{t('installer_subtitle')}</p>
                            <button onClick={() => window.location.reload()} className="px-12 py-5 rounded-2xl bg-[var(--gold)] text-[var(--obsidian)] text-[11px] font-black uppercase tracking-[0.4em] transition-all hover:scale-105">
                                {t('installer_retry')}
                            </button>
                         </div>
                    </div>
                )}
            </div>
        );
    };

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<App />);
</script>

<?php require_once 'footer.php'; ?>
