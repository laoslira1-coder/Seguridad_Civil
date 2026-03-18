<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

// 1. LÓGICA DE UBICACIÓN
if (isset($_POST['set_ubicacion'])) {
    // Guardamos la ubicación en la sesión
    $_SESSION['ubicacion_actual'] = $_POST['ubicacion_selected'];
    
    // CORRECCIÓN: Recargamos ESTA MISMA página para mostrar el panel (dashboard)
    // No redirigimos a control_garita.php automáticamente.
    header("Location: control_garita_principal.php");
    exit();
}

$nombre_usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'OPERADOR';
$ubicacion_actual = isset($_SESSION['ubicacion_actual']) ? $_SESSION['ubicacion_actual'] : 'NO DEFINIDO';

// Si no está definido, mostramos modal al cargar.
$mostrar_modal = ($ubicacion_actual === 'NO DEFINIDO') ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Control de Acceso | Seguridad Civil</title>
    
    <!-- FUENTES IDÉNTICAS A PANEL.PHP -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>
    <style>
        /* --- VARIABLES DEL TEMA --- */
        :root { 
            --h-gold: #c5a059; 
            --h-dark: #1a1c1e; 
            --h-gray-bg: #f4f4f7; 
            --h-white: #ffffff; 
            --text-700: #1e293b;
            --text-500: #475569;
            --text-300: #94a3b8;
            --radius-lg: 24px;
            --radius-md: 16px;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--h-gray-bg);
            color: var(--text-700);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-tap-highlight-color: transparent;
        }

        /* --- HEADER --- */
        .header-main { 
            background: var(--h-white); 
            padding: 15px 20px; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 60px;
        }

        .header-left { flex: 1; }
        .btn-back-panel {
            color: var(--text-500);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-back-panel:hover { color: var(--h-gold); transform: translateX(-3px); }

        .header-center {
            flex: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        .logo-img { 
            height: 45px;
            width: auto;
            object-fit: contain;
        }

        .header-right { flex: 1; display: flex; justify-content: flex-end; }
        .btn-logout {
            color: var(--text-300);
            font-size: 20px;
            transition: 0.3s;
            text-decoration: none;
        }
        .btn-logout:hover { color: #ef4444; transform: scale(1.1); }

        @media (max-width: 480px) {
            .btn-back-panel span { display: none; } 
            .btn-back-panel i { font-size: 18px; }
            .logo-img { height: 35px; }
            .header-main { padding: 10px 15px; }
        }

        /* --- CONTENIDO --- */
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px; 
            flex: 1; 
            width: 100%; 
            box-sizing: border-box; 
        }

        /* BARRA DE CONTEXTO */
        .context-card {
            background: var(--h-white);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            border: 1px solid #edf2f7;
        }

        .context-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .context-icon {
            width: 40px; height: 40px;
            background: rgba(197, 160, 89, 0.1);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: var(--h-gold);
            font-size: 18px;
        }
        .context-text h4 { margin: 0; font-size: 11px; color: var(--text-300); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        .context-text h2 { margin: 2px 0 0; font-size: 14px; color: var(--text-700); font-weight: 700; text-transform: uppercase; }

        .btn-change {
            background: var(--h-dark);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: 0.3s;
        }
        .btn-change:hover { background: var(--h-gold); transform: translateY(-2px); }

        .section-heading {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-300);
            font-weight: 800;
            letter-spacing: 1.5px;
            margin: 10px 0 15px 5px;
        }

        /* --- MENÚ --- */
        .menu-list { display: flex; flex-direction: column; gap: 12px; }
        
        .menu-item { 
            display: flex; align-items: center; 
            background: var(--h-white); padding: 20px; 
            border-radius: var(--radius-md); 
            text-decoration: none; border: 1px solid #edf2f7;
            transition: 0.3s ease;
            cursor: pointer;
        }

        .menu-item:hover { 
            border-color: var(--h-gold); 
            transform: translateY(-2px); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
        }

        .icon-box { 
            width: 50px; height: 50px; 
            background: #f8fafc; border-radius: 14px; 
            display: flex; justify-content: center; align-items: center; 
            margin-right: 18px; flex-shrink: 0;
            transition: 0.3s;
            color: var(--h-gold);
            font-size: 22px; 
        }
        
        .menu-item:hover .icon-box { background: var(--h-gold); color: white; }

        .text-box { flex: 1; }
        .text-box h3 { margin: 0; font-size: 15px; color: var(--text-700); font-weight: 700; }
        .text-box p { margin: 2px 0 0; font-size: 12px; color: var(--text-500); font-weight: 400; }

        .chevron { color: #cbd5e1; font-size: 14px; }

        .footer { background: #f1f5f9; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; gap: 15px; border-top: 1px solid #e2e8f0; }
        .footer-text { font-size: 10px; color: #94a3b8; letter-spacing: 2px; font-weight: 700; }

        /* --- MODAL --- */
        .modal-overlay {
            display: none; 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(26, 28, 30, 0.6); 
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center; 
            justify-content: center;
        }

        @media (max-width: 480px) {
            .modal-overlay { align-items: flex-end; }
            .modal-content { border-bottom-left-radius: 0; border-bottom-right-radius: 0; margin: 0; width: 100%; }
        }

        .modal-content {
            background: var(--h-white);
            width: 90%;
            max-width: 450px;
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-title { font-size: 16px; font-weight: 800; color: var(--text-700); text-transform: uppercase; margin-bottom: 25px; font-family: 'Inter', sans-serif; }

        .location-option {
            background: var(--h-gray-bg);
            border: 1px solid #e2e8f0;
            padding: 18px;
            border-radius: var(--radius-md);
            width: 100%;
            margin-bottom: 12px;
            cursor: pointer;
            display: flex; align-items: center; gap: 15px;
            transition: 0.2s;
            text-align: left;
            color: var(--text-700);
            width: 100%;
        }
        
        .location-option:hover { background: #fff; border-color: var(--h-gold); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .location-option i { color: var(--text-300); font-size: 20px; width: 30px; text-align: center; }
        .location-option:hover i { color: var(--h-gold); }
        .location-option span { font-weight: 700; font-size: 13px; text-transform: uppercase; }

    </style>
</head>
<body>

    <!-- MODAL DE SELECCIÓN DE PUNTO DE CONTROL -->
    <div id="modalLocation" class="modal-overlay">
        <div class="modal-content">
            <h2 class="modal-title">Seleccione Punto de Control</h2>
            
            <form method="POST">
                <input type="hidden" name="set_ubicacion" value="1">
                
                <!-- OPCIÓN 1: GARITA PRINCIPAL -->
                <!-- Al hacer clic, se envía el formulario, se guarda la sesión y se recarga esta página mostrando el panel -->
                <button type="submit" name="ubicacion_selected" value="GARITA PRINCIPAL" class="location-option">
                    <i class="fa-solid fa-tower-observation"></i>
                    <span>GARITA PRINCIPAL</span>
                </button>

                <!-- OPCIÓN 2: BOCAMINA (EN DESARROLLO) -->
                <!-- type="button" evita que se envíe el formulario. No pasa nada más que la alerta. -->
                <button type="button" class="location-option" onclick="alert('Módulo en Desarrollo.\nPróximamente disponible.');">
                    <i class="fa-solid fa-mountain-sun"></i>
                    <span>BOCAMINA / INTERIOR</span>
                </button>
            </form>
            <div style="margin-top:20px; font-size:10px; color:#94a3b8; font-weight:700;">SEGURIDAD CIVIL • HOCHSCHILD</div>
        </div>
    </div>

    <nav class="header-main">
        <div class="header-left">
            <a href="panel.php" class="btn-back-panel">
                <i class="fa-solid fa-chevron-left"></i>
                <span>VOLVER</span>
            </a>
        </div>
        
        <div class="header-center">
            <img src="Assets Index/logo.png" alt="Logo" class="logo-img">
            <img src="Assets Index/seguridadcivil.png" alt="Seguridad Civil" class="logo-img">
        </div>

        <div class="header-right">
            <a href="logout.php" class="btn-logout" title="Cerrar Sesión"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </nav>

    <div class="container">

        <!-- TARJETA DE CONTEXTO (UBICACIÓN ACTUAL) -->
        <div class="context-card">
            <div class="context-info">
                <div class="context-icon">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div class="context-text">
                    <h4>Ubicación Actual</h4>
                    <h2><?php echo htmlspecialchars($ubicacion_actual); ?></h2>
                </div>
            </div>
            <!-- Botón para reabrir el modal y cambiar ubicación -->
            <button class="btn-change" onclick="abrirModal()">Cambiar</button>
        </div>

        <div class="section-heading">Gestión Operativa</div>

        <div class="menu-list">
            <!-- BOTÓN AHORA SÍ LLEVA AL FORMULARIO DE VEHÍCULOS -->
            <a href="control_garita.php" class="menu-item">
                <div class="icon-box">
                    <i class="fa-solid fa-tower-observation"></i>
                </div>
                <div class="text-box">
                    <h3>Control de Garita Principal</h3>
                    <p>Registro de unidades, conductores y acompañantes.</p>
                </div>
                <i class="fa-solid fa-chevron-right chevron"></i>
            </a>
        </div>

        <div class="section-heading">Control Auxiliar</div>

        <div class="menu-list">
            <!-- MÓDULO PERSONAL -->
            <a href="control_personal.php" class="menu-item">
                <div class="icon-box">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div class="text-box">
                    <h3>Personal y Visitas</h3>
                    <p>Registro de acceso peatonal y contratistas.</p>
                </div>
                <i class="fa-solid fa-chevron-right chevron"></i>
            </a>

            <!-- MÓDULO NOVEDADES -->
            <a href="#" onclick="alert('Módulo en construcción')" class="menu-item">
                <div class="icon-box" style="color:var(--text-300);">
                    <i class="fa-solid fa-book"></i>
                </div>
                <div class="text-box">
                    <h3>Cuaderno de Novedades</h3>
                    <p>Registro digital del turno actual.</p>
                </div>
                <i class="fa-solid fa-lock chevron"></i>
            </a>
        </div>

    </div>

    <footer class="footer">
        <div class="footer-text">CONTROL DE ACCESO INTEGRAL</div>
        <div class="footer-text" style="color:var(--h-gold);">HOCHSCHILD MINING • 2026</div>
    </footer>

    <script>
        const modal = document.getElementById('modalLocation');
        // Si no está definida la ubicación, forzamos mostrar el modal
        const show = <?php echo $mostrar_modal; ?>;

        if(show) { 
            modal.style.display = 'flex'; 
        }

        function abrirModal() {
            modal.style.display = 'flex';
        }
        
        // Cierra el modal si se hace clic fuera del contenido (solo si ya hay ubicación definida)
        window.onclick = function(event) {
            if (event.target == modal && !show) { 
                modal.style.display = "none";
            }
        }
    </script>

</body>
</html>