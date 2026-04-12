<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

// 1. LÓGICA DE UBICACIÓN
if (isset($_POST['set_ubicacion'])) {
    $_SESSION['ubicacion_actual'] = $_POST['ubicacion_selected'];
    header("Location: control_garita_principal.php");
    exit();
}

$nombre_usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'OPERADOR';
$ubicacion_actual = isset($_SESSION['ubicacion_actual']) ? $_SESSION['ubicacion_actual'] : 'NO DEFINIDO';

$mostrar_modal = ($ubicacion_actual === 'NO DEFINIDO') ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#F4F4F4">
    <title>Control de Acceso | Seguridad Civil</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>
    <style>
        :root {
            --g: #C49A2C;
            --gd: #8A6A14;
            --gl: #FBF6E8;
            --gr: rgba(196,154,44,.15);
            --g-grad: linear-gradient(135deg,#7A5A0E,#C49A2C,#E8C85A);
            --ink: #0A0A0A;
            --ink2: #1A1A1A;
            --ink3: #3A3A3A;
            --ink4: #666666;
            --ink5: #999999;
            --ink6: #BBBBBB;
            --bg: #F4F4F4;
            --surf: #FFFFFF;
            --surf2: #FAFAFA;
            --b: rgba(0,0,0,.08);
            --b-gold: rgba(196,154,44,.3);
            --red: #DC2626;
            --green: #16A34A;
            --gb: #F0FDF4;
            --gbo: #86EFAC;
            --font: 'Inter', system-ui, sans-serif;
            --mono: 'JetBrains Mono', monospace;
            --r-sm: 8px;
            --r-md: 12px;
            --r-lg: 16px;
            --r-xl: 20px;
            --r-2xl: 24px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; -webkit-text-size-adjust: 100%; }
        body {
            font-family: var(--font);
            font-size: 14px;
            line-height: 1.5;
            background: var(--bg);
            color: var(--ink);
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            -webkit-tap-highlight-color: transparent;
        }
        a { text-decoration: none; color: inherit; }
        button { font-family: var(--font); border: none; cursor: pointer; background: none; }

        /* ═══ TOPBAR ═══ */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 200;
            background: rgba(255,255,255,.97);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid rgba(0,0,0,.07);
            box-shadow: 0 1px 0 rgba(255,255,255,1), 0 2px 16px rgba(0,0,0,.05);
        }
        .topbar-inner {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 16px;
            height: 60px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-back {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink4);
            padding: 7px 14px;
            border-radius: var(--r-sm);
            border: 1px solid var(--b);
            background: var(--surf);
            transition: all .2s;
            flex-shrink: 0;
        }
        .btn-back:hover { color: var(--g); border-color: var(--b-gold); background: var(--gl); transform: translateX(-2px); }
        .btn-back i { font-size: 11px; }

        .topbar-logos {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }
        .topbar-logo { height: 32px; width: auto; object-fit: contain; }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 500;
            color: var(--ink4);
            padding: 6px 12px;
            border: 1px solid var(--b);
            border-radius: var(--r-sm);
            background: var(--surf);
            transition: all .2s;
            flex-shrink: 0;
        }
        .btn-logout:hover { color: var(--red); border-color: rgba(252,165,165,.4); background: #FEF2F2; }
        .btn-logout svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

        .topbar-gold { height: 2px; background: linear-gradient(90deg, #0A0A0A 0%, #C49A2C 20%, #E8C85A 50%, #C49A2C 80%, transparent 100%); opacity: .7; }

        /* ═══ MAIN ═══ */
        .main-wrap {
            max-width: 480px;
            margin: 0 auto;
            padding: 18px 14px 40px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            width: 100%;
        }

        /* ═══ CONTEXT CARD (Ubicación) ═══ */
        .ctx-card {
            background: var(--surf);
            border: 1px solid rgba(0,0,0,.07);
            border-radius: var(--r-2xl);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(0,0,0,.06);
        }
        .ctx-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, #0A0A0A 0%, #C49A2C 35%, #E8C85A 60%, transparent 100%);
            border-radius: var(--r-2xl) var(--r-2xl) 0 0;
        }
        .ctx-ico {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: var(--gl);
            border: 1px solid var(--b-gold);
            display: flex; align-items: center; justify-content: center;
            color: var(--gd);
            font-size: 18px;
            flex-shrink: 0;
        }
        .ctx-info { flex: 1; min-width: 0; }
        .ctx-label { font-size: 8px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--ink5); margin-bottom: 2px; }
        .ctx-value { font-size: 15px; font-weight: 800; color: var(--ink); letter-spacing: -.2px; }
        .ctx-btn {
            font-size: 10px;
            font-weight: 700;
            color: var(--ink4);
            padding: 7px 14px;
            border-radius: var(--r-sm);
            border: 1px solid var(--b);
            background: var(--surf2);
            transition: all .2s;
            text-transform: uppercase;
            letter-spacing: .5px;
            flex-shrink: 0;
        }
        .ctx-btn:hover { background: var(--ink); color: #fff; border-color: var(--ink); transform: translateY(-1px); }

        /* ═══ SECTION LABEL ═══ */
        .sec-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 2px;
            margin-top: 4px;
        }
        .sec-txt { font-size: 9px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: var(--ink); white-space: nowrap; }
        .sec-line { flex: 1; height: 1px; background: rgba(0,0,0,.1); }

        /* ═══ MODULE CARDS ═══ */
        .mod-list { display: flex; flex-direction: column; gap: 7px; }

        .mcard {
            background: var(--surf);
            border: 1px solid rgba(0,0,0,.07);
            border-radius: var(--r-xl);
            display: flex;
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
            transition: transform .22s, box-shadow .22s, border-color .22s;
            position: relative;
        }
        .mcard:hover { border-color: var(--b-gold); box-shadow: 0 4px 16px rgba(196,154,44,.12); transform: translateX(2px); }
        .mcard:active { transform: scale(.98); }

        .mcard-accent { width: 3px; flex-shrink: 0; border-radius: var(--r-xl) 0 0 var(--r-xl); }
        .mcard-body { flex: 1; padding: 14px 16px; display: flex; align-items: center; gap: 14px; }
        .mcard-ico {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 1px 4px rgba(0,0,0,.07);
        }
        .mcard-text { flex: 1; min-width: 0; }
        .mcard-t { font-size: 13px; font-weight: 700; color: var(--ink); line-height: 1.25; }
        .mcard-s { font-size: 10px; color: var(--ink4); margin-top: 2px; }
        .mcard-arr { margin-left: auto; color: #D8D4CE; font-size: 14px; transition: all .2s; flex-shrink: 0; }
        .mcard:hover .mcard-arr { color: var(--g); transform: translateX(3px); }

        .mcard-badge {
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .5px;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            position: absolute;
            top: 10px;
            right: 14px;
        }

        /* ═══ FOOTER ═══ */
        .footer-wrap {
            margin-top: auto;
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .footer-text { font-size: 8px; font-weight: 600; letter-spacing: 2.5px; color: var(--ink6); text-transform: uppercase; }
        .footer-gold { color: var(--g); }

        /* ═══ MODAL ═══ */
        .modal-overlay {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 480px) {
            .modal-overlay { align-items: flex-end; }
            .modal-content { border-radius: var(--r-2xl) var(--r-2xl) 0 0 !important; max-width: 100% !important; width: 100% !important; }
        }

        .modal-content {
            background: var(--surf);
            width: 90%;
            max-width: 420px;
            border-radius: var(--r-2xl);
            padding: 32px 24px;
            text-align: center;
            animation: modalIn 0.3s ease-out;
            box-shadow: 0 24px 80px rgba(0,0,0,.2);
            position: relative;
            overflow: hidden;
        }
        .modal-content::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--g-grad);
            border-radius: var(--r-2xl) var(--r-2xl) 0 0;
        }
        @keyframes modalIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            background: var(--gl);
            border: 1px solid var(--b-gold);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            color: var(--gd);
            font-size: 24px;
        }
        .modal-title { font-size: 16px; font-weight: 800; color: var(--ink); margin-bottom: 6px; letter-spacing: -.2px; }
        .modal-sub { font-size: 11px; color: var(--ink5); margin-bottom: 24px; }

        .loc-option {
            background: var(--surf2);
            border: 1px solid rgba(0,0,0,.08);
            padding: 16px 18px;
            border-radius: var(--r-lg);
            width: 100%;
            margin-bottom: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all .22s;
            text-align: left;
            color: var(--ink);
            font-family: var(--font);
            font-size: 14px;
        }
        .loc-option:hover { background: var(--surf); border-color: var(--b-gold); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(196,154,44,.12); }
        .loc-option:active { transform: scale(.98); }

        .loc-ico {
            width: 40px; height: 40px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .loc-text { flex: 1; }
        .loc-name { font-weight: 700; font-size: 13px; letter-spacing: .2px; }
        .loc-desc { font-size: 10px; color: var(--ink5); margin-top: 1px; }
        .loc-arr { color: #D8D4CE; font-size: 14px; transition: all .2s; }
        .loc-option:hover .loc-arr { color: var(--g); transform: translateX(3px); }

        .modal-footer-text { margin-top: 16px; font-size: 8px; color: var(--ink6); font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
    </style>
</head>
<body>

    <!-- MODAL DE SELECCIÓN DE PUNTO DE CONTROL -->
    <div id="modalLocation" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-icon"><i class="fa-solid fa-location-dot"></i></div>
            <h2 class="modal-title">Seleccione Punto de Control</h2>
            <p class="modal-sub">Elija la ubicación donde operará durante su turno</p>

            <form method="POST">
                <input type="hidden" name="set_ubicacion" value="1">

                <button type="submit" name="ubicacion_selected" value="GARITA PRINCIPAL" class="loc-option">
                    <div class="loc-ico" style="background: var(--gl); color: var(--gd);"><i class="fa-solid fa-tower-observation"></i></div>
                    <div class="loc-text">
                        <div class="loc-name">Garita Principal</div>
                        <div class="loc-desc">Control vehicular y peatonal</div>
                    </div>
                    <div class="loc-arr"><i class="fa-solid fa-chevron-right"></i></div>
                </button>

                <button type="button" class="loc-option" onclick="alert('Módulo en Desarrollo.\nPróximamente disponible.');">
                    <div class="loc-ico" style="background: var(--gb); color: var(--green);"><i class="fa-solid fa-mountain-sun"></i></div>
                    <div class="loc-text">
                        <div class="loc-name">Bocamina / Interior</div>
                        <div class="loc-desc">Control de acceso interior mina</div>
                    </div>
                    <span class="mcard-badge" style="background: var(--gb); color: var(--green); position: static;">Pronto</span>
                </button>
            </form>
            <div class="modal-footer-text">Seguridad Civil · Hochschild</div>
        </div>
    </div>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-inner">
            <a href="panel.php" class="btn-back">
                <i class="fa-solid fa-chevron-left"></i>
                <span>Volver</span>
            </a>
            <div class="topbar-logos">
                <img src="Assets Index/logo.png" alt="Logo" class="topbar-logo">
                <img src="Assets Index/seguridadcivil.png" alt="Seguridad Civil" class="topbar-logo">
            </div>
            <a href="logout.php" class="btn-logout">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Salir
            </a>
        </div>
        <div class="topbar-gold"></div>
    </div>

    <div class="main-wrap">

        <!-- CONTEXT CARD -->
        <div class="ctx-card">
            <div class="ctx-ico"><i class="fa-solid fa-location-dot"></i></div>
            <div class="ctx-info">
                <div class="ctx-label">Ubicación Actual</div>
                <div class="ctx-value"><?php echo htmlspecialchars($ubicacion_actual); ?></div>
            </div>
            <button class="ctx-btn" onclick="abrirModal()">Cambiar</button>
        </div>

        <!-- GESTIÓN OPERATIVA -->
        <div class="sec-row">
            <span class="sec-txt">Gestión Operativa</span>
            <div class="sec-line"></div>
        </div>

        <div class="mod-list">
            <a href="control_garita.php" class="mcard">
                <div class="mcard-accent" style="background: var(--g);"></div>
                <div class="mcard-body">
                    <div class="mcard-ico" style="background: var(--gl); color: var(--gd);">
                        <i class="fa-solid fa-tower-observation"></i>
                    </div>
                    <div class="mcard-text">
                        <div class="mcard-t">Control de Garita Principal</div>
                        <div class="mcard-s">Registro de unidades, conductores y acompañantes</div>
                    </div>
                    <div class="mcard-arr"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
            </a>
        </div>

        <!-- CONTROL AUXILIAR -->
        <div class="sec-row" style="margin-top: 4px;">
            <span class="sec-txt">Control Auxiliar</span>
            <div class="sec-line"></div>
        </div>

        <div class="mod-list">
            <a href="control_personal.php" class="mcard">
                <div class="mcard-accent" style="background: var(--ink);"></div>
                <div class="mcard-body">
                    <div class="mcard-ico" style="background: var(--surf2); color: var(--ink);">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div class="mcard-text">
                        <div class="mcard-t">Personal y Visitas</div>
                        <div class="mcard-s">Registro de acceso peatonal y contratistas</div>
                    </div>
                    <div class="mcard-arr"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
            </a>

            <a href="#" onclick="alert('Módulo en construcción')" class="mcard">
                <div class="mcard-accent" style="background: var(--ink5);"></div>
                <div class="mcard-body">
                    <div class="mcard-ico" style="background: var(--bg); color: var(--ink5);">
                        <i class="fa-solid fa-book"></i>
                    </div>
                    <div class="mcard-text">
                        <div class="mcard-t">Cuaderno de Novedades</div>
                        <div class="mcard-s">Registro digital del turno actual</div>
                    </div>
                    <div class="mcard-arr"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <span class="mcard-badge" style="background: var(--bg); color: var(--ink5);">Pronto</span>
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer-wrap">
        <div class="footer-text">Control de Acceso Integral</div>
        <div class="footer-text footer-gold">Hochschild Mining · 2026</div>
    </div>

    <script>
        const modal = document.getElementById('modalLocation');
        const show = <?php echo $mostrar_modal; ?>;

        if(show) {
            modal.style.display = 'flex';
        }

        function abrirModal() {
            modal.style.display = 'flex';
        }

        window.onclick = function(event) {
            if (event.target == modal && !show) {
                modal.style.display = "none";
            }
        }
    </script>

</body>
</html>
