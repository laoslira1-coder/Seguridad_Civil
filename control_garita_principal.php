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

require_once 'config.php';
$usuario_session = $_SESSION['usuario'];
$stmt_user = $conn->prepare("SELECT nombre, cargo_real FROM usuarios WHERE nombre_usuario = ? LIMIT 1");
$stmt_user->bind_param("s", $usuario_session);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

$nombre_completo = "OPERADOR";
if ($res_user && $res_user->num_rows > 0) {
    $fila_usuario = $res_user->fetch_assoc();
    $nombre_completo = $fila_usuario['nombre'];
}

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
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>
    <style>
        /* --- ESTILOS LOCALES --- */
        body { font-family: 'Poppins', sans-serif; }
        
        /* BARRA DE CONTEXTO */
        .context-card {
            background: var(--h-white);
            border-radius: var(--radius-2xl);
            padding: 24px;
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
            gap: 15px;
        }
        .context-icon {
            width: 45px; height: 45px;
            background: rgba(197, 160, 89, 0.1);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: var(--h-gold);
            font-size: 20px;
        }
        .context-text h4 { margin: 0; font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        .context-text h2 { margin: 2px 0 0; font-size: 16px; color: var(--text-primary); font-weight: 800; text-transform: uppercase; }

        .btn-change {
            background: var(--h-dark);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: 0.3s;
        }
        .btn-change:hover { background: var(--h-gold); transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,160,89,0.3); }

        .section-heading {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 800;
            letter-spacing: 1.5px;
            margin: 15px 0 15px 5px;
        }

        /* --- MENÚ --- */
        .menu-list { display: flex; flex-direction: column; gap: 12px; }
        
        .menu-item { 
            display: flex; align-items: center; 
            background: var(--h-white); padding: 20px; 
            border-radius: var(--radius-lg); 
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
        .text-box h3 { margin: 0; font-size: 15px; color: var(--text-primary); font-weight: 700; transition: 0.2s;}
        .menu-item:hover .text-box h3 { color: var(--h-gold); }
        .text-box p { margin: 4px 0 0; font-size: 12px; color: var(--text-secondary); font-weight: 400; }

        .chevron { color: #cbd5e1; font-size: 14px; }

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

        .modal-title { font-size: 16px; font-weight: 800; color: var(--text-primary); text-transform: uppercase; margin-bottom: 25px; font-family: 'Inter', sans-serif; }

        .location-option {
            background: var(--h-gray-bg);
            border: 1px solid #e2e8f0;
            padding: 18px;
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            cursor: pointer;
            display: flex; align-items: center; gap: 15px;
            transition: 0.2s;
            text-align: left;
            color: var(--text-primary);
            width: 100%;
        }
        
        .location-option:hover { background: #fff; border-color: var(--h-gold); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .location-option i { color: var(--text-muted); font-size: 20px; width: 30px; text-align: center; }
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
                
                <button type="submit" name="ubicacion_selected" value="GARITA PRINCIPAL" class="location-option">
                    <i class="fa-solid fa-tower-observation"></i>
                    <span>GARITA PRINCIPAL</span>
                </button>

                <button type="button" class="location-option" onclick="alert('Módulo en Desarrollo.\nPróximamente disponible.');">
                    <i class="fa-solid fa-mountain-sun"></i>
                    <span>BOCAMINA / INTERIOR</span>
                </button>
            </form>
            <div style="margin-top:20px; font-size:10px; color:#94a3b8; font-weight:700;">SEGURIDAD CIVIL • HOCHSCHILD</div>
        </div>
    </div>

<div class="app-layout">
    
    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="Assets Index/logo.png" alt="Hochschild Logo" class="sidebar-logo">
            <h2 class="sidebar-title">SITRAN</h2>
        </div>
        <div class="sidebar-menu">
            <div class="menu-label">Principal</div>
            <a href="panel.php" class="sidebar-link"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="monitoreo.php" class="sidebar-link"><i class="fa-solid fa-desktop"></i> Monitoreo</a>
            
            <div class="menu-label" style="margin-top: 20px;">Operación</div>
            <a href="control_garita_principal.php" class="sidebar-link active"><i class="fa-solid fa-id-card-clip"></i> Control Acceso</a>
        </div>
    </nav>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img src="Assets Index/seguridadcivil.png" alt="Seguridad Civil" style="height: 30px;">
                </div>
            </div>
            <div class="topbar-right">
                <div class="user-profile">
                    <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo mb_strtoupper((explode(' ', $nombre_completo))[0]); ?></span>
                        <span class="user-role">OPERADOR</span>
                    </div>
                </div>
                <a href="logout.php" style="color: var(--color-danger); font-size: 18px; margin-left: 10px;"><i class="fa-solid fa-power-off"></i></a>
            </div>
        </header>

        <!-- CONTENT BODY -->
        <div class="content-body" style="max-width: 600px;">

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
                <button class="btn-change" onclick="abrirModal()">Cambiar</button>
            </div>

            <div class="section-heading">Gestión Operativa</div>

            <div class="menu-list">
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

            <div class="section-heading" style="margin-top: 25px;">Control Auxiliar</div>

            <div class="menu-list">
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

                <a href="#" onclick="alert('Módulo en construcción')" class="menu-item">
                    <div class="icon-box" style="color:var(--text-muted); background: var(--h-gray-bg);">
                        <i class="fa-solid fa-book"></i>
                    </div>
                    <div class="text-box">
                        <h3 style="color:var(--text-muted);">Cuaderno de Novedades</h3>
                        <p>Registro digital del turno actual.</p>
                    </div>
                    <i class="fa-solid fa-lock chevron"></i>
                </a>
            </div>

        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }

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