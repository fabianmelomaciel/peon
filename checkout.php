<?php
/**
 * PASARELA ESTRATÉGICA PEÓN v1.0
 * Integración con PayPal y Clic404 para monetización del sistema.
 */
require_once 'config.php';
require_once 'vault.php';
require_once 'projects_sync.php';

$paypal_client_id = PAY_PAL_CLIENT_ID;
$paypal_mode = "live";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>PEÓN | Centro de Licenciamiento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Grotesk:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        :root { --gold: #FFB300; --obsidian: #05050A; }
        body { background: var(--obsidian); color: white; font-family: 'Space Grotesk', sans-serif; }
        .bebas { font-family: 'Bebas Neue', cursive; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-4xl w-full grid grid-cols-1 md:grid-cols-2 gap-12 animate-in">
        
        {/* Lado Izquierdo: Oferta Strategica */}
        <div class="space-y-8">
            <div class="flex items-center gap-4">
                <div class="p-4 bg-white/5 border border-white/10 rounded-2xl">
                    <span class="text-4xl text-[var(--gold)]">♟️</span>
                </div>
                <div>
                    <h1 class="text-4xl font-black bebas tracking-widest">PEÓN <span class="text-[var(--gold)]">LICENSING</span></h1>
                    <p class="text-[10px] uppercase tracking-widest text-white/40">Suministro de Inteligencia v1.0</p>
                </div>
            </div>

            <div class="space-y-4">
                <div class="glass p-6 rounded-3xl space-y-2 border-l-4 border-[var(--gold)]">
                    <h3 class="font-bold text-lg uppercase bebas tracking-wider">Plan Estratégico Mensual</h3>
                    <p class="text-white/60 text-sm">Acceso total a los 17 agentes especialistas, sincronización ilimitada y blindaje de secretos.</p>
                    <div class="text-3xl font-black text-[var(--gold)] pt-4">$49.99 <span class="text-xs text-white/20 uppercase">/ Mes</span></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="glass p-4 rounded-2xl text-center">
                        <div class="text-[var(--gold)] font-bold mb-1">UNLIMITED</div>
                        <div class="text-[8px] uppercase tracking-widest text-white/40 font-black">Sincronizaciones</div>
                    </div>
                    <div class="glass p-4 rounded-2xl text-center">
                        <div class="text-[var(--gold)] font-bold mb-1">AI CORE</div>
                        <div class="text-[8px] uppercase tracking-widest text-white/40 font-black">17 Especialistas</div>
                    </div>
                </div>
            </div>

            <div class="bg-[var(--gold)]/5 p-6 rounded-3xl border border-[var(--gold)]/20">
                <p class="text-[10px] leading-relaxed text-white/60 italic">"El costo de la inacción es mayor que cualquier inversión estratégica. Peón no es un gasto, es el despliegue de tu ventaja competitiva."</p>
                <div class="mt-4 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/10 overflow-hidden">
                        <img src="https://api.dicebear.com/7.x/shapes/svg?seed=Fabian&backgroundColor=FFB300" alt="CEO" class="w-full h-full object-cover">
                    </div>
                    <span class="text-[9px] font-black uppercase text-white/40 tracking-widest">Fabian Melo - CEO Strategic</span>
                </div>
            </div>
        </div>

        {/* Lado Derecho: Checkout */}
        <div class="glass p-8 md:p-12 rounded-[3.5rem] border-[var(--gold)]/20 flex flex-col justify-center">
            <h2 class="text-2xl font-black bebas tracking-widest mb-8 text-center text-white">PROCEDER AL <span class="text-[var(--gold)]">DESPLIEGUE</span></h2>
            
            <div id="paypal-button-container" class="w-full min-h-[150px]"></div>
            
            <div class="mt-8 pt-8 border-t border-white/5 space-y-4">
                <div class="flex items-center gap-3 text-white/40">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span class="text-[9px] font-black uppercase tracking-widest">Transacción Blindada por Clic404 SSL</span>
                </div>
                <button onclick="window.location.href='dashboard'" class="w-full py-4 text-[10px] font-black uppercase tracking-widest text-white/20 hover:text-white transition-all underline underline-offset-8 decoration-[var(--gold)]/20">VOLVER AL CENTRO DE MANDO</button>
                <div class="pt-4 border-t border-white/5">
                    <button onclick="simulatePurchase()" class="w-full py-3 rounded-xl bg-white/5 border border-white/10 text-[var(--gold)]/40 font-black text-[9px] uppercase tracking-[.4em] hover:bg-white/10 transition-all">SIMULACIÓN TÁCTICA: SALTAR PAGO</button>
                </div>
            </div>
        </div>
    </div>

    {/* Scripts de Terceros */}
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo $paypal_client_id; ?>&currency=USD"></script>
    <script>
        function simulatePurchase() {
            const buyerEmail = 'ceo-test@sixlan.com';
            console.log('Iniciando simulación de compra...');
            fetch(`projects_sync.php?action=verify_license_purchase&email=${encodeURIComponent(buyerEmail)}`)
            .then(res => res.json())
            .then(resData => {
                if (resData.status === 'success') {
                    alert('🧪 SIMULACIÓN EXITOSA: Llave generada: ' + resData.data.license_key);
                    window.location.href = 'index.php?action=activate&key=' + resData.data.license_key;
                }
            });
        }
        paypal.Buttons({
            style: {
                color: 'gold',
                shape: 'pill',
                label: 'pay',
                height: 50
            },
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '49.99',
                            description: 'PEÓN STRATEGIC OS - LICENCIA MENSUAL'
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    const buyerEmail = details.payer.email_address;
                    
                    // Handshake táctico con Sixlan para generar licencia real
                    fetch(`projects_sync.php?action=verify_license_purchase&email=${encodeURIComponent(buyerEmail)}`)
                    .then(res => res.json())
                    .then(resData => {
                        if (resData.status === 'success') {
                            alert('🔥 MISIÓN ACTIVADA: Tu llave táctica es: ' + resData.data.license_key);
                            window.location.href = 'index.php?action=activate&key=' + resData.data.license_key;
                        } else {
                            alert('⚠️ PAGO RECIBIDO PERO ERROR EN LICENCIA: Contacte a soporte Sixlan.');
                            window.location.href = 'index.php';
                        }
                    });
                });
            }
        }).render('#paypal-button-container');
    </script>
</body>
</html>
