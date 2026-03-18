<?php
session_start();

// 1. SEGURIDAD DE SESIÓN
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// 2. CONEXIÓN A LA BASE DE DATOS
require_once 'config.php';
// $conn disponible desde config.php (Hostinger)

// 3. RECUPERAR DATOS EXACTOS
$usuario_session = $_SESSION['usuario'];
$stmt_user = $conn->prepare("SELECT nombre, cargo_real FROM usuarios WHERE nombre_usuario = ? LIMIT 1");
$stmt_user->bind_param("s", $usuario_session);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

$nombre_completo = $_SESSION['usuario'];
$cargo_real = "OPERADOR DE SEGURIDAD";

if ($res_user && $res_user->num_rows > 0) {
    $fila_usuario = $res_user->fetch_assoc();
    $nombre_completo = $fila_usuario['nombre'];
    $cargo_real = $fila_usuario['cargo_real'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel SITRAN | Hochschild</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>
    <style>
        /* Panel specific overrides */
        body { font-family: 'Poppins', sans-serif; }
        .container { max-width: 800px; margin: 0 auto; width: 100%; box-sizing: border-box; }

        /* TARJETA BIENVENIDA */
        .welcome-card {
            background: var(--h-dark);
            border-radius: var(--radius-2xl);
            padding: 40px 20px;
            color: var(--h-white);
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border: 1px solid rgba(197, 160, 89, 0.3);
            position: relative;
            overflow: hidden;
        }

        .welcome-card::after {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(197, 160, 89, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .welcome-card h2 { 
            font-size: 12px; 
            color: var(--h-white); 
            font-weight: 400; 
            letter-spacing: 4px; 
            text-transform: uppercase; 
            margin: 0 0 15px 0;
            opacity: 0.8;
        }

        .role-badge { 
            display: inline-block; 
            margin-bottom: 15px;
            font-size: 11px; 
            font-weight: 700; 
            padding: 8px 20px; 
            border-radius: 50px; 
            background: var(--h-gold); 
            color: var(--h-dark); 
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(197, 160, 89, 0.3);
        }

        .welcome-card h1 { 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            font-size: 26px; 
            font-weight: 800; 
            letter-spacing: -0.5px;
            text-transform: uppercase;
            color: var(--h-gold);
            line-height: 1.2;
        }

        /* --- MENÚ --- */
        .menu-list { display: flex; flex-direction: column; gap: 12px; }
        
        .menu-item { 
            display: flex; align-items: center; 
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            padding: 20px; 
            border-radius: var(--radius-lg); 
            text-decoration: none; border: 1px solid #edf2f7;
            transition: 0.3s ease;
            position: relative;
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
            color: var(--text-primary);
            font-size: 22px; 
        }
        
        .menu-item:hover .icon-box { background: var(--h-gold); color: white; }

        .text-box h3 { margin: 0; font-size: 16px; color: var(--text-primary); font-weight: 700; transition: 0.2s;}
        .menu-item:hover .text-box h3 { color: var(--h-gold); }
        .text-box p { margin: 4px 0 0; font-size: 12px; color: var(--text-secondary); }

        .badge-live {
            background: #ef4444; color: white; font-size: 9px; font-weight: 800;
            padding: 4px 10px; border-radius: 20px; position: absolute; top: 20px; right: 20px;
            animation: pulse-red 2s infinite;
        }
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 50% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }

        /* --- FOOTER --- */
        .footer { padding: 40px 20px; display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .logos-footer { display: flex; gap: 30px; align-items: center; }
        .logo-footer-bottom { height: 60px; cursor: pointer; transition: 0.3s; filter: grayscale(1); opacity: 0.5; }
        .logo-footer-bottom:hover { filter: grayscale(0); opacity: 1; transform: scale(1.05); }
        .footer-text { font-size: 10px; color: var(--text-muted); letter-spacing: 2px; font-weight: 700; }
    </style>
</head>
<body>

<div class="app-layout">
    
    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="Assets Index/logo.png" alt="Hochschild Logo" class="sidebar-logo">
            <h2 class="sidebar-title">SITRAN</h2>
        </div>
        <div class="sidebar-menu">
            <div class="menu-label">Principal</div>
            <a href="panel.php" class="sidebar-link active"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="monitoreo.php" class="sidebar-link"><i class="fa-solid fa-desktop"></i> Monitoreo</a>
            
            <div class="menu-label" style="margin-top: 20px;">Operación</div>
            <a href="control_garita_principal.php" class="sidebar-link"><i class="fa-solid fa-id-card-clip"></i> Control Acceso</a>
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
        <div class="content-body">
            <div class="container">
                <!-- TARJETA BIENVENIDA -->
                <div class="welcome-card">
                    <h2>BIENVENIDO</h2>
                    <div class="role-badge"><?php echo mb_strtoupper($cargo_real); ?></div>
                    <h1><?php echo mb_strtoupper($nombre_completo); ?></h1>
                </div>

                <div class="menu-list">
                    <!-- BOTÓN 0: CENTRO DE MONITOREO -->
                    <a href="monitoreo.php" class="menu-item" style="border-left: 5px solid var(--h-gold);">
                        <div class="icon-box" style="background: rgba(197, 160, 89, 0.1); color: var(--h-gold);">
                            <i class="fa-solid fa-desktop"></i>
                        </div>
                        <div class="text-box">
                            <h3>Centro de Monitoreo Real-Time</h3>
                            <p>KPIs operativos, aforo en vivo y radar de movimientos.</p>
                        </div>
                        <span class="badge-live">LIVE</span>
                    </a>

                    <!-- BOTÓN 1: CONTROL DE ACCESO INTEGRAL -->
                    <a href="control_garita_principal.php" class="menu-item">
                        <div class="icon-box">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div class="text-box">
                            <h3>Control de Acceso Integral</h3>
                            <p>Gestión centralizada de ingresos, personal y vehículos.</p>
                        </div>
                    </a>

                    <!-- BOTÓN 2: PLAN TORQUE -->
                    <a href="#" class="menu-item" onclick="alert('Módulo de Plan Torque y Procedimientos estará disponible próximamente.')">
                        <div class="icon-box">
                            <i class="fa-solid fa-file-contract"></i>
                        </div>
                        <div class="text-box">
                            <h3>Plan Torque y Procedimientos</h3>
                            <p>Consulta de lineamientos y documentación operativa.</p>
                        </div>
                    </a>
                </div>
                
                <footer class="footer">
                    <div class="logos-footer">
                        <img src="Assets Index/Hochscild_logo3.png" alt="Hochschild" class="logo-footer-bottom">
                        <img src="Assets Index/Torque SC.png" alt="Torque" class="logo-footer-bottom">
                    </div>
                    <div class="footer-text">HOCHSCHILD MINING • 2026</div>
                </footer>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
</script>

</body>
</html>