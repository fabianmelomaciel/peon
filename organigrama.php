<?php
require_once 'core.php';
$extra_css = 'organigrama.css';
require_once 'header.php';
?>

<canvas id="particle-canvas"></canvas>
<div id="organigrama-root"></div>

<script type="text/babel">
    const { 
        TacticalHeader, SystemHub, CodexPeon, MissionHUD, StrategicIcon, 
        TRANSLATIONS, ALL_AGENTS = [], FLOORS = [], CEO_NAME = "Fabian Melo",
        SOURCE_PATH, TARGET_PATH, ROOT_PATH, OS_FAMILY, PEON_MD_EXISTS, AgentDossier, AGENT_COUNT, SECTOR_COUNT 
    } = window;

    const OrganigramaApp = () => {
        const [lang, setLangState] = React.useState(localStorage.getItem('peon_lang') || 'es');
        const setLang = (l) => { localStorage.setItem('peon_lang', l); setLangState(l); };
        const [selectedAgent, setSelectedAgent] = React.useState(null);
        const [showSystemHub, setShowSystemHub] = React.useState(false);
        const [showManual, setShowManual] = React.useState(false);
        const [systemStatus, setSystemStatus] = React.useState({ status: 'operational', count: 0 });
        const [activeMission, setActiveMission] = React.useState(null);
        const [wirePaths, setWirePaths] = React.useState([]);

        const viewportRef = React.useRef(null);
        const ceoRef = React.useRef(null);
        const pmRef = React.useRef(null);
        const floorRefs = React.useRef([]);

        const t = (key) => {
            let str = (TRANSLATIONS && TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) || key;
            if (typeof str === 'string') {
                str = str.replace('{{agent_count}}', AGENT_COUNT);
                str = str.replace('{{sector_count}}', SECTOR_COUNT);
            }
            return str;
        };

        // Estilos de impresión inyectados dinámicamente
        React.useEffect(() => {
            const style = document.createElement('style');
            style.id = 'print-style-organigrama';
            style.innerHTML = `
                @media print {
                    @page { size: landscape; margin: 0; }
                    body { background: #fff !important; color: #000 !important; }
                    #organigrama-root { padding: 0 !important; margin: 0 !important; }
                    .org-viewport { 
                        width: 100% !important; 
                        height: 100vh !important; 
                        padding: 10mm !important; 
                        background: none !important;
                        justify-content: center !important;
                        transform: scale(0.85);
                        transform-origin: top center;
                    }
                    .no-print, .TacticalHeader, .FloatingDock, button, .SystemHub, .MissionHUD { display: none !important; }
                    .org-card { 
                        background: #fff !important; 
                        border: 1.5px solid #000 !important; 
                        color: #000 !important; 
                        box-shadow: none !important; 
                        backdrop-filter: none !important; 
                        width: 200px !important;
                        padding: 1rem !important;
                        border-radius: 12px !important;
                    }
                    .org-card.ceo { width: 280px !important; border-width: 3px !important; }
                    .org-path { 
                        stroke: #000 !important; 
                        stroke-width: 1px !important; 
                        stroke-dasharray: none !important; 
                        animation: none !important; 
                        opacity: 1 !important;
                    }
                    .org-name, .org-label, .org-role { color: #000 !important; opacity: 1 !important; }
                    .org-avatar { filter: grayscale(100%); border: 1px solid #000 !important; width: 50px !important; height: 50px !important; margin-bottom: 0.5rem !important; }
                    .agent-satellites { gap: 0.25rem !important; margin-top: 1rem !important; }
                    .agent-sat { background: #fff !important; border: 1px solid #ccc !important; color: #000 !important; padding: 0.2rem 0.5rem !important; font-size: 7px !important; }
                    .particle-canvas, #particle-canvas { display: none !important; }
                    .station-glow { display: none !important; }
                }
            `;
            document.head.appendChild(style);
            return () => {
                const existing = document.getElementById('print-style-organigrama');
                if (existing) existing.remove();
            };
        }, []);

        const scanNetwork = () => {
            fetch('installer_utility.php?action=status')
                .then(r => r.json())
                .then(data => setSystemStatus(data))
                .catch(() => setSystemStatus({ status: 'error' }));
        };

        const updateWires = React.useCallback(() => {
            if (!viewportRef.current || !ceoRef.current || !pmRef.current) return;

            const vRect = viewportRef.current.getBoundingClientRect();
            const getPos = (el, type = 'center') => {
                const r = el.getBoundingClientRect();
                if (r.width === 0 || r.height === 0) return null; // Avoid 0/0 pointing

                const x = r.left - vRect.left + r.width / 2;
                let y = r.top - vRect.top + r.height / 2;
                
                if (type === 'top') y = r.top - vRect.top;
                if (type === 'bottom') y = r.top - vRect.top + r.height;

                return { x, y };
            };

            const ceoPos = getPos(ceoRef.current, 'bottom');
            const pmPos = getPos(pmRef.current, 'center');
            
            if (!ceoPos || !pmPos) return;

            const paths = [];

            // CEO to PM
            paths.push(`M ${ceoPos.x} ${ceoPos.y} L ${pmPos.x} ${pmPos.y - 40}`); // Point slightly above PM center

            // PM to Floors
            floorRefs.current.forEach(fEl => {
                if (fEl) {
                    const fPos = getPos(fEl, 'top'); // Target Top-Center for cleaner tree
                    if (fPos) {
                        const cpY = pmPos.y + (fPos.y - pmPos.y) / 2;
                        paths.push(`M ${pmPos.x} ${pmPos.y} C ${pmPos.x} ${cpY}, ${fPos.x} ${cpY}, ${fPos.x} ${fPos.y}`);
                    }
                }
            });

            setWirePaths(paths);
        }, []);

        React.useEffect(() => {
            // Delay for layout stability and entry animations
            const timer = setTimeout(updateWires, 1000);
            window.addEventListener('resize', updateWires);
            return () => {
                clearTimeout(timer);
                window.removeEventListener('resize', updateWires);
            };
        }, [updateWires]);

        const runMission = (agent, type) => {
            setActiveMission({
                agent, type,
                logs: [`${t('status_mission_start')}: ${type}...`],
                progress: 10,
                statusMsg: t('status_running')
            });
            setTimeout(() => {
                setActiveMission(prev => ({ ...prev, progress: 100, statusMsg: t('status_completed') }));
                setTimeout(() => setActiveMission(null), 2000);
            }, 3000);
        };

        const pmAgent = React.useMemo(() => {
            const list = Array.isArray(ALL_AGENTS) ? ALL_AGENTS : Object.values(ALL_AGENTS).flat();
            return list.find(a => a.technical_name === 'orquestador-maestro');
        }, []);

        const OrgNode = ({ agent, label, isCeo, color, nodeRef }) => (
            <div className="org-node-container animate-in-fade relative mb-8" ref={nodeRef}>
                <div 
                    className={`org-card ${isCeo ? 'ceo' : ''} cursor-pointer hover:scale-105 transition-all`}
                    onClick={() => agent && setSelectedAgent({...agent, name: (agent.names && agent.names[lang]) || agent.name})}
                >
                    <div className="org-label" style={{ color: color || 'var(--gold)' }}>{label}</div>
                    <img 
                        src={isCeo ? `https://api.dicebear.com/7.x/shapes/svg?seed=CEO_STRATEGIC&backgroundColor=05050A` : `https://api.dicebear.com/7.x/bottts-neutral/svg?seed=${agent?.technical_name}&backgroundColor=05050A`} 
                        className="org-avatar" 
                        alt={label} 
                    />
                    <h2 className="org-name">{isCeo ? CEO_NAME.toUpperCase() : (t(agent?.technical_name) || agent?.name || '').toUpperCase()}</h2>
                    <p className="text-[8px] text-white/30 uppercase font-black tracking-widest mt-1">{isCeo ? t('humano_ceo') : (t(agent?.technical_name + '-role') || agent?.role || t('estrategia_central')).toUpperCase()}</p>
                    <div className="station-glow" style={{ '--floor-color': color || 'var(--gold)' }}></div>
                </div>
            </div>
        );

        return (
            <div className="flex flex-col min-h-screen bg-[#05050A]">
                <TacticalHeader 
                    t={t} 
                    scanNetwork={scanNetwork} 
                    systemStatus={systemStatus} 
                    lang={lang} 
                    setLang={setLang} 
                    setShowManual={setShowManual} 
                    setShowSystemHub={setShowSystemHub} 
                />

                <main className="flex-1 pt-32 md:pt-40 pb-32 px-4 md:px-8 overflow-x-hidden">
                    <div className="org-viewport w-full max-w-7xl mx-auto relative" ref={viewportRef}>
                        <svg className="org-connectors pointer-events-none">
                            {wirePaths.map((p, i) => (
                                <React.Fragment key={i}>
                                    <path d={p} className="org-path active" />
                                    <path d={p} className="data-pulse" />
                                </React.Fragment>
                            ))}
                        </svg>

                        {/* 01. EL HUMANO (CEO) */}
                        <div className="flex justify-center w-full z-10">
                            <OrgNode 
                                label={t('ceo_role') || 'DIRECCIÓN GENERAL'} 
                                isCeo={true} 
                                color="#FFB300" 
                                nodeRef={ceoRef} 
                            />
                        </div>
                        
                        <div className="h-16 md:h-24"></div>

                        {/* 02. ORQUESTADOR MAESTRO (PM) */}
                        <div className="flex justify-center w-full z-10">
                            <OrgNode 
                                label={t('pm_role') || 'PROJECT MANAGER'} 
                                agent={pmAgent} 
                                color="#00E676" 
                                nodeRef={pmRef} 
                            />
                        </div>
                        
                        <div className="h-20 md:h-32"></div>

                        {/* 03. PISOS OPERATIVOS */}
                        <div className="flex flex-wrap justify-center gap-6 md:gap-8 px-4 z-10 w-full">
                            {Array.isArray(FLOORS) && FLOORS.map((floor, fIdx) => (
                                <div key={floor.id || fIdx} className="flex flex-col items-center">
                                    <div 
                                        className={`org-card department floor-piso-${floor.id} w-full md:!w-[260px] !p-6`}
                                        ref={el => floorRefs.current[fIdx] = el}
                                    >
                                        <div className="org-label text-center" style={{ color: floor.color }}>{t(floor.name_key)}</div>
                                        <div className="text-white/40 text-[8px] font-black tracking-widest uppercase mb-4 text-center truncate">{t(floor.dept_key)}</div>
                                        
                                        <div className="agent-satellites space-y-2">
                                            {(floor.agentes || [])
                                                .filter(a => a.technical_name !== 'orquestador-maestro') // Filter PM duplicate
                                                .map((agent) => (
                                                <div 
                                                    key={agent.technical_name} 
                                                    onClick={() => setSelectedAgent(agent)}
                                                    className="agent-sat !bg-white/5 hover:!bg-[var(--gold)]/20 border border-white/5 hover:border-[var(--gold)]/40 transition-all rounded-xl p-3 flex items-center gap-3"
                                                >
                                                    <span className="text-base opacity-60">{agent.badge}</span>
                                                    <span className="text-[9px] font-bold text-white/70 truncate">{(t(agent.technical_name) || agent.name || '').toUpperCase()}</span>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="station-glow" style={{'--floor-color': floor.color}}></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </main>

                {selectedAgent && (
                    <AgentDossier 
                        agent={selectedAgent} 
                        onClose={() => setSelectedAgent(null)} 
                        runMission={runMission}
                        t={t} 
                    />
                )}
                {showManual && <CodexPeon t={t} lang={lang} onClose={() => setShowManual(false)} onUninstall={() => {}} />}
                {showSystemHub && <SystemHub t={t} lang={lang} setLang={setLang} onClose={() => setShowSystemHub(false)} onUninstall={() => {}} />}
                {activeMission && <MissionHUD mission={activeMission} onToggle={() => {}} onClose={() => setActiveMission(null)} t={t} />}
            </div>
        );
    };

    const root = ReactDOM.createRoot(document.getElementById('organigrama-root'));
    root.render(<OrganigramaApp />);
</script>

<style>
    .org-viewport { position: relative; }
    .org-connectors { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; overflow: visible; }
    .org-card.ceo { border: 2px solid var(--gold); background: radial-gradient(circle at center, rgba(255,179,0,0.1), transparent); }
    .org-name { font-family: 'Space Grotesk', sans-serif; font-weight: 700; color: white; margin-top: 0.5rem; text-align: center; font-size: 1.1rem; }
    .org-avatar { width: 56px; height: 56px; border-radius: 14px; border: 2px solid rgba(255,255,255,0.1); padding: 4px; background: #05050A; margin: 0 auto; }
    .agent-sat { cursor: pointer; border: 1px solid transparent; }
    .org-label { font-size: 10px; font-weight: 900; letter-spacing: 0.1em; margin-bottom: 0.5rem; text-transform: uppercase; }
</style>

<?php require_once 'footer.php'; ?>
