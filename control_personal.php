<?php
// ==============================================================================
// CONTROL PEATONAL - FINAL STABLE (SIN ERROR 500 + FLECHA NEGRA)
// ==============================================================================
ob_start();
session_start();

date_default_timezone_set('America/Lima');

// 1. CONEXIÓN
require_once 'config.php';
// $conn disponible desde config.php (Hostinger)
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

$busqueda = "";
$persona = null;
$nuevo_dni = null;
$mensaje = null;
$tipo_mensaje = "";

// ---------------------------------------------------------
// 2. BÚSQUEDA
// ---------------------------------------------------------
if (isset($_POST['dni_buscar'])) {
    $busqueda = preg_replace('/[^0-9]/', '', $_POST['dni_buscar']);
    $stmt_bus = $conn->prepare("SELECT * FROM fuerza_laboral WHERE dni = ? LIMIT 1");
    $stmt_bus->bind_param("s", $busqueda);
    $stmt_bus->execute();
    $res = $stmt_bus->get_result();

    if ($res->num_rows > 0) {
        $persona = $res->fetch_assoc();
    } else {
        $nuevo_dni = $busqueda;
    }
}

// ---------------------------------------------------------
// 3. REGISTRO (CORREGIDO: ELIMINADO num_acompanantes)
// ---------------------------------------------------------
if (isset($_POST['btn_registrar'])) {
    $dni    = preg_replace('/[^0-9]/', '', $_POST['dni_final']);
    $nombre = strtoupper(trim($_POST['nombre_final']));
    $empresa = strtoupper(trim($_POST['empresa_final']));

    // Si es nuevo, insertamos en fuerza_laboral
    if (isset($_POST['es_nuevo']) && $_POST['es_nuevo'] == '1') {
        $tipo_p = trim($_POST['tipo_personal_new']);
        $stmt_chk = $conn->prepare("SELECT dni FROM fuerza_laboral WHERE dni = ?");
        $stmt_chk->bind_param("s", $dni);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows == 0) {
            $cargo_peat = 'PEATON';
            $stmt_new = $conn->prepare(
                "INSERT INTO fuerza_laboral (dni, nombres, apellidos, empresa, tipo_personal, area, cargo, estado_validacion)
                 VALUES (?, ?, '-', ?, ?, '-', ?, 'ACTIVO')"
            );
            $stmt_new->bind_param("sssss", $dni, $nombre, $empresa, $tipo_p, $cargo_peat);
            if (!$stmt_new->execute()) {
                die("Error al crear personal.");
            }
        }
    }

    $mov = trim($_POST['tipo_movimiento']);

    if ($mov === 'SALIDA') {
        $destino  = strtoupper(trim($_POST['destino_salida']));
        $autoriza = trim($_POST['autoriza_salida']);
    } else {
        $destino  = 'INTERIOR MINA';
        $autoriza = 'VERIFICADO EN GARITA';
    }

    $anfitrion = strtoupper(trim($_POST['anfitrion'] ?? '-'));
    $motivo    = strtoupper(trim($_POST['motivo'] ?? '-'));
    $op        = $_SESSION['usuario'];
    $ning      = 'NINGUNO';

    $stmt_reg = $conn->prepare(
        "INSERT INTO registros_garita
            (dni_conductor, nombre_conductor, empresa, tipo_movimiento,
             destino, autorizado_por, anfitrion, motivo, operador_garita,
             fecha_ingreso, acompanante_1, acompanante_2, acompanante_3, acompanante_4)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)"
    );
    $stmt_reg->bind_param(
        "sssssssssssss",
        $dni, $nombre, $empresa, $mov,
        $destino, $autoriza, $anfitrion, $motivo, $op,
        $ning, $ning, $ning, $ning
    );

    if ($stmt_reg->execute()) {
        header("Location: control_personal.php?status=ok");
        exit();
    } else {
        die("Error FATAL SQL: " . $stmt_reg->error);
    }
}

if(isset($_GET['status']) && $_GET['status'] == 'ok') {
    $mensaje = "REGISTRO EXITOSO";
    $tipo_mensaje = "success";
}

// 4. HISTORIAL
$sql_hist = "SELECT * FROM registros_garita ORDER BY fecha_ingreso DESC LIMIT 10";
$res_hist = mysqli_query($conn, $sql_hist);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Personal | SITRAN</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>

    <style>
        :root { --h-gold: #c5a059; --h-dark: #111827; --h-bg: #f3f4f6; }
        body { font-family: 'Inter', sans-serif; background-color: var(--h-bg); margin: 0; padding-bottom: 50px; }

        /* HEADER */
        .header-main { background: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid var(--h-gold); position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .logo-header { height: 45px; }

        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        
        /* ALERTAS */
        .alert-box { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: 700; animation: fadeIn 0.5s; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } 
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        /* ESCÁNER (TARJETA NEGRA) */
        .driver-search-box { background: var(--h-dark); color: white; border-radius: 24px; padding: 40px 20px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .big-scan-btn { background: var(--h-gold); color: white; border: none; width: 90px; height: 90px; border-radius: 50%; font-size: 34px; cursor: pointer; box-shadow: 0 0 25px rgba(197, 160, 89, 0.6); margin-bottom: 20px; animation: pulse 2s infinite; }
        @keyframes pulse { 0% {box-shadow: 0 0 0 0 rgba(197, 160, 89, 0.7);} 70% {box-shadow: 0 0 0 15px rgba(197, 160, 89, 0);} 100% {box-shadow: 0 0 0 0 rgba(197, 160, 89, 0);} }
        .input-driver { background: rgba(255,255,255,0.1); border: 2px solid #374151; color: white; padding: 15px; width: 80%; border-radius: 12px; text-align: center; font-family: 'Orbitron'; font-size: 18px; outline: none; }
        
        /* FICHA */
        .result-card { background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08); animation: slideUp 0.4s; }
        @keyframes slideUp { from {transform: translateY(20px); opacity:0;} to {transform: translateY(0); opacity:1;} }
        .card-header { background: var(--h-dark); padding: 30px 20px; text-align: center; border-bottom: 4px solid var(--h-gold); }
        .card-header h2 { margin: 0; color: white; font-family: 'Orbitron'; font-size: 20px; letter-spacing: 1px; }
        .card-header p { margin: 5px 0 0; color: var(--h-gold); font-size: 12px; font-weight: 600; }
        .form-section { padding: 25px; }

        /* INPUTS */
        .label-mini { font-size: 10px; font-weight: 800; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .input-comp { width: 100%; padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; box-sizing: border-box; background: #f9fafb; margin-bottom: 15px; }
        .input-comp:focus { border-color: var(--h-gold); background: white; outline: none; }
        
        /* BOTONES DE SELECCIÓN */
        .action-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
        .btn-sel { 
            flex: 1; padding: 18px; border-radius: 12px; font-weight: 800; cursor: pointer; text-align: center; 
            font-size: 13px; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;
            border: 2px solid #e2e8f0; color: #94a3b8; background: #f8fafc;
        }
        
        /* ESTADOS ACTIVOS FORZADOS */
        .active-gold { background: var(--h-gold) !important; color: white !important; border-color: var(--h-gold) !important; box-shadow: 0 8px 20px rgba(197, 160, 89, 0.4); transform: translateY(-2px); }
        .active-black { background: var(--h-dark) !important; color: white !important; border-color: var(--h-dark) !important; box-shadow: 0 8px 20px rgba(0,0,0,0.4); transform: translateY(-2px); }
        
        .btn-confirm { width: 100%; padding: 20px; border: none; border-radius: 14px; font-weight: 800; font-size: 16px; margin-top: 10px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition:0.3s; color: white; }
        .bg-gold { background: var(--h-gold); box-shadow: 0 4px 0 #a17f3a; }
        .bg-black { background: var(--h-dark); box-shadow: 0 4px 0 #000; }

        /* TABS TIPO */
        .type-tabs { display: flex; gap: 5px; margin-bottom: 15px; background: #f3f4f6; padding: 4px; border-radius: 8px; }
        .type-tab { flex: 1; text-align: center; padding: 8px; border-radius: 6px; font-weight: 800; font-size: 11px; cursor: pointer; color: #9ca3af; }
        
        /* HISTORIAL */
        .history-box { margin-top: 30px; }
        .history-item { 
            background: white; padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; 
            border-left: 5px solid transparent; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer; transition: 0.2s;
        }
        .history-item.item-in { border-left-color: var(--h-gold); }
        .history-item.item-out { border-left-color: var(--h-dark); }
        
        .h-name { font-weight: 700; font-size: 14px; color: #374151; display:flex; align-items:center; }
        .h-desc { font-size: 11px; color: #9ca3af; margin-top: 2px; }
        .h-time { font-family: 'Orbitron'; font-weight: 700; color: var(--h-dark); font-size: 12px; }
        .btn-details-icon { background: #f1f5f9; color: var(--h-gold); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }

        .strict-box { background: #fef2f2; border: 1px solid #fee2e2; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: none; border-left: 4px solid var(--h-dark); }
        .strict-label { color: var(--h-dark); font-size: 10px; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; display: block; }

        /* MODAL DETALLES */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal-card { background: white; padding: 25px; border-radius: 20px; width: 85%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); animation: popIn 0.3s; }
        @keyframes popIn { from{transform:scale(0.9);} to{transform:scale(1);} }
        
        .det-row { display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding: 12px 0; font-size: 13px; }
        .det-label { font-weight: 800; color: #94a3b8; text-transform: uppercase; font-size: 10px; }
        .det-val { font-weight: 600; color: var(--h-dark); text-align: right; }
    </style>
</head>
<body>

<div id="camera-modal" class="modal-overlay">
    <div style="text-align:center; width:100%; max-width:400px;">
        <div style="color:white; margin-bottom:20px; font-family:'Orbitron';">ESCANEAR DNI</div>
        <div style="width:100%; border:2px solid var(--h-gold); border-radius:20px; overflow:hidden;"><div id="reader"></div></div>
        <button onclick="document.getElementById('camera-modal').style.display='none'" style="margin-top:20px; padding:10px 30px; border-radius:20px; border:none; font-weight:800;">CERRAR</button>
    </div>
</div>

<div id="details-modal" class="modal-overlay" onclick="closeDetails()">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div style="text-align:center; margin-bottom:20px;">
            <div style="width:60px; height:60px; background:#f8fafc; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:24px; color:var(--h-gold);">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <h3 style="margin:0; color:var(--h-dark); font-family:'Orbitron';">DETALLE MOVIMIENTO</h3>
        </div>
        <div id="modal-body"></div>
        <button onclick="closeDetails()" style="width:100%; padding:15px; background:var(--h-dark); color:white; border:none; border-radius: 12px; margin-top:20px; font-weight:800; cursor:pointer;">CERRAR</button>
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
                    <a href="control_garita_principal.php" style="color:var(--text-primary);"><i class="fa-solid fa-chevron-left"></i></a>
                    <span style="font-weight:700; font-size:14px; color:var(--text-primary);">CONTROL PERSONAL</span>
                </div>
            </div>
            <div class="topbar-right">
                <a href="reporte_excel.php" target="_blank" style="background:var(--color-success); color:white; padding:8px 15px; border-radius:8px; text-decoration:none; font-weight:700; font-size:11px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-file-excel"></i> DATA
                </a>
            </div>
        </header>

        <div class="content-body">
            <div class="container" style="max-width: 600px; margin: 0 auto; width:100%;">
    
    <?php if ($mensaje): ?> <div class="alert-box <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div> <?php endif; ?>

    <?php if (!$persona && !$nuevo_dni): ?>
        <div class="driver-search-box">
            <button onclick="openScanner()" class="big-scan-btn"><i class="fa-solid fa-qrcode"></i></button>
            <h2 style="font-family:'Orbitron'; margin:15px 0 25px;">CONTROL PEATONAL</h2>
            <form method="POST" id="formScan">
                <input type="text" name="dni_buscar" id="inputDni" class="input-driver" placeholder="DNI..." autocomplete="off">
            </form>
            <p style="font-size:12px; color:#9ca3af; margin-top:20px; font-weight:600;">Use el botón para activar cámara</p>
        </div>
    <?php endif; ?>

    <?php if ($persona || $nuevo_dni): ?>
        <div class="result-card">
            <div class="card-header">
                <?php if ($persona): ?>
                    <h2><?php echo explode(" ", $persona['nombres'])[0] . " " . explode(" ", $persona['apellidos'])[0]; ?></h2>
                    <p><?php echo $persona['empresa']; ?> | DNI: <?php echo $persona['dni']; ?></p>
                    
                    <?php if (isset($persona['tipo_personal']) && $persona['tipo_personal'] == 'VISITA'): ?>
                        <div style="margin-top:10px; display:inline-block; background:var(--visita); color:white; font-size:10px; font-weight:800; padding:4px 10px; border-radius:4px;">VISITA</div>
                    <?php else: ?>
                        <div style="margin-top:10px; display:inline-block; background:rgba(255,255,255,0.2); color:white; font-size:10px; font-weight:800; padding:4px 10px; border-radius:4px;">PERMANENTE</div>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>NUEVO PERSONAL</h2>
                    <p>DNI: <?php echo $nuevo_dni; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="form-section">
                <form method="POST">
                    <input type="hidden" name="dni_final" value="<?php echo $persona ? $persona['dni'] : $nuevo_dni; ?>">
                    
                    <?php if (!$persona): ?>
                        <input type="hidden" name="es_nuevo" value="1">
                        
                        <label class="label-mini">TIPO DE PERSONAL</label>
                        <div class="action-buttons">
                            <div class="btn-sel active-gold" id="tab-perm" onclick="setNewType('PERMANENTE')">PERMANENTE</div>
                            <div class="btn-sel" id="tab-vis" onclick="setNewType('VISITA')">VISITA</div>
                        </div>
                        <input type="hidden" name="tipo_personal_new" id="new_type" value="PERMANENTE">
                        
                        <label class="label-mini">NOMBRE</label>
                        <input type="text" name="nombre_final" class="input-comp" required>
                        <label class="label-mini">EMPRESA</label>
                        <input type="text" name="empresa_final" class="input-comp" required>
                    <?php else: ?>
                        <input type="hidden" name="nombre_final" value="<?php echo $persona['nombres'].' '.$persona['apellidos']; ?>">
                        <input type="hidden" name="empresa_final" value="<?php echo $persona['empresa']; ?>">
                    <?php endif; ?>

                    <div id="visita-box" style="display:<?php echo (isset($persona) && $persona['tipo_personal']=='VISITA')?'block':'none'; ?>; background:#fff7ed; padding:10px; border-radius:10px; margin-bottom:15px; border:1px solid #fdba74;">
                        <label class="label-mini" style="color:#c2410c;">ANFITRIÓN</label>
                        <input type="text" name="anfitrion" class="input-comp" placeholder="A quien visita...">
                        <label class="label-mini" style="color:#c2410c;">MOTIVO</label>
                        <input type="text" name="motivo" class="input-comp">
                    </div>

                    <label class="label-mini">SELECCIONE MOVIMIENTO</label>
                    <div class="action-buttons">
                        <div class="btn-sel active-gold" id="btn-in" onclick="setMov('INGRESO')">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> INGRESO
                        </div>
                        <div class="btn-sel" id="btn-out" onclick="setMov('SALIDA')">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> SALIDA
                        </div>
                    </div>
                    <input type="hidden" name="tipo_movimiento" id="tipo_movimiento" value="INGRESO">

                    <div id="salida-box" class="strict-box">
                        <label class="strict-label">DESTINO</label>
                        <input type="text" name="destino_salida" id="dest" class="input-comp" placeholder="Destino...">
                        <label class="strict-label">AUTORIZADO POR</label>
                        <select name="autoriza_salida" id="auth" class="input-comp">
                            <option value="">-- SELECCIONE --</option>
                            <option value="Jorge Taco">Jorge Taco</option>
                            <option value="Fredy Achircana">Fredy Achircana</option>
                            <option value="Daniel Contreras">Daniel Contreras</option>
                            <option value="Centro de Control">Centro de Control</option>
                        </select>
                    </div>

                    <button type="submit" name="btn_registrar" id="btn-submit" class="btn-confirm bg-gold">
                        REGISTRAR INGRESO
                    </button>
                    
                    <a href="control_personal.php" style="display:block; text-align:center; margin-top:15px; color:#ef4444; font-weight:800; text-decoration:none;">CANCELAR</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="history-box">
        <h3 style="font-size:11px; font-weight:800; color:#64748b; margin-bottom:10px;">ÚLTIMOS MOVIMIENTOS</h3>
        <?php while($row = mysqli_fetch_assoc($res_hist)): 
            $is_out = ($row['tipo_movimiento'] == 'SALIDA');
            $data_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
        ?>
            <div class="history-item <?php echo $is_out?'item-out':'item-in'; ?>" onclick="showHistoryDetails('<?php echo $data_json; ?>')">
                <div style="flex:1;">
                    <div class="h-name">
                        <?php if($is_out): ?>
                            <i class="fa-solid fa-arrow-left" style="color:var(--h-dark); margin-right:8px;"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-arrow-right" style="color:var(--h-gold); margin-right:8px;"></i>
                        <?php endif; ?>
                        <?php echo explode(' ', $row['nombre_conductor'])[0]; ?>
                    </div>
                    <div class="h-desc">
                        <?php echo $row['empresa']; ?>
                        <?php if($is_out): ?> <span style="opacity:0.6;"> → <?php echo $row['destino']; ?></span><?php endif; ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div class="h-time"><?php echo date('H:i', strtotime($row['fecha_ingreso'])); ?></div>
                    <div style="margin-top:5px; display:flex; justify-content:flex-end;">
                        <div class="btn-details-icon"><i class="fa-solid fa-eye"></i></div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<audio id="beep" src="https://www.soundjay.com/buttons/beep-01a.mp3"></audio>

<script>
    function setMov(tipo) {
        const input = document.getElementById('tipo_movimiento');
        const btnIn = document.getElementById('btn-in');
        const btnOut = document.getElementById('btn-out');
        const submit = document.getElementById('btn-submit');
        const boxSalida = document.getElementById('salida-box');
        const dest = document.getElementById('dest');
        const auth = document.getElementById('auth');

        input.value = tipo;

        // Limpiar clases
        btnIn.classList.remove('active-gold', 'active-black');
        btnOut.classList.remove('active-gold', 'active-black');
        submit.classList.remove('bg-gold', 'bg-black');

        if (tipo === 'INGRESO') {
            btnIn.classList.add('active-gold');
            submit.innerHTML = 'REGISTRAR INGRESO';
            submit.classList.add('bg-gold');
            boxSalida.style.display = 'none';
            dest.required = false; auth.required = false;
        } else {
            btnOut.classList.add('active-black');
            submit.innerHTML = 'REGISTRAR SALIDA';
            submit.classList.add('bg-black');
            boxSalida.style.display = 'block';
            dest.required = true; auth.required = true;
        }
    }

    function setNewType(type) {
        document.getElementById('new_type').value = type;
        const box = document.getElementById('visita-box');
        const btnPerm = document.getElementById('tab-perm');
        const btnVis = document.getElementById('tab-vis');

        // Limpiar clases
        btnPerm.classList.remove('active-gold', 'active-black');
        btnVis.classList.remove('active-gold', 'active-black');

        if(type === 'VISITA') {
            btnVis.classList.add('active-black'); 
            if(box) box.style.display = 'block';
        } else {
            btnPerm.classList.add('active-gold'); 
            if(box) box.style.display = 'none';
        }
    }

    // MODAL DE DETALLES
    function showHistoryDetails(jsonStr) {
        const data = JSON.parse(jsonStr);
        const color = (data.tipo_movimiento === 'INGRESO') ? '#c5a059' : '#1a1c1e';
        
        let html = `
            <div class="det-row"><span class="det-label">MOVIMIENTO</span> <span class="det-val" style="color:${color}; font-weight:800;">${data.tipo_movimiento}</span></div>
            <div class="det-row"><span class="det-label">NOMBRE</span> <span class="det-val">${data.nombre_conductor}</span></div>
            <div class="det-row"><span class="det-label">EMPRESA</span> <span class="det-val">${data.empresa}</span></div>
            <div class="det-row"><span class="det-label">FECHA/HORA</span> <span class="det-val">${data.fecha_ingreso}</span></div>
            <div class="det-row"><span class="det-label">OPERADOR</span> <span class="det-val">${data.operador_garita}</span></div>
        `;

        if(data.tipo_movimiento === 'SALIDA') {
            html += `
                <div class="det-row"><span class="det-label">DESTINO</span> <span class="det-val">${data.destino}</span></div>
                <div class="det-row"><span class="det-label">AUTORIZADO POR</span> <span class="det-val">${data.autorizado_por}</span></div>
            `;
        }

        if(data.anfitrion && data.anfitrion !== '-') {
            html += `
                <div class="det-row"><span class="det-label">ANFITRIÓN</span> <span class="det-val">${data.anfitrion}</span></div>
                <div class="det-row"><span class="det-label">MOTIVO</span> <span class="det-val">${data.motivo}</span></div>
            `;
        }

        document.getElementById('modal-body').innerHTML = html;
        document.getElementById('details-modal').style.display = 'flex';
    }

    function closeDetails() {
        document.getElementById('details-modal').style.display = 'none';
    }

    // CÁMARA
    let scanner;
    function openScanner() {
        document.getElementById('camera-modal').style.display = 'flex';
        scanner = new Html5Qrcode("reader");
        scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScan);
    }
    function onScan(txt) {
        let val = txt.trim();
        if(!/^\d{8}$/.test(val)) return;
        document.getElementById('beep').play();
        scanner.stop().then(() => {
            document.getElementById('camera-modal').style.display='none';
            document.getElementById('inputDni').value = val;
            document.getElementById('formScan').submit();
        });
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }
</script>

            </div> <!-- End container -->
        </div> <!-- End content-body -->
    </div> <!-- End main-content -->
</div> <!-- End app-layout -->

</body>
</html>