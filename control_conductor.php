<?php
// ==============================================================================
// CONTROL DE CONDUCTORES - VERSIÓN FINAL CORREGIDA (FIX SQL)
// ==============================================================================
ob_start(); 
session_start();

// 1. CONFIGURACIÓN DE ERRORES (Silenciosos para no romper JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. CONEXIÓN BLINDADA
require_once 'config.php';
// $conn ya está disponible desde config.php con charset utf8
date_default_timezone_set('America/Lima');

// Verificar Sesión
if (!isset($_SESSION['usuario'])) { 
    if(isset($_POST['ajax_create_companion'])) {
        ob_clean();
        echo json_encode(['success' => false, 'msg' => 'Sesión caducada. Recargue la página.']);
        exit;
    }
    header("Location: index.php"); 
    exit(); 
}

$busqueda = "";
$persona = null;
$nuevo_dni = null;
$mensaje = null;
$tipo_mensaje = ""; 

// ---------------------------------------------------------
// 0. AJAX: CREACIÓN RÁPIDA DE ACOMPAÑANTE (MODAL FANTASMA)
// ---------------------------------------------------------
if (isset($_POST['ajax_create_companion'])) {
    ob_clean();
    header('Content-Type: application/json');

    $dni    = preg_replace('/[^0-9]/', '', $_POST['dni'] ?? '');
    $nombre = strtoupper(trim($_POST['nombre'] ?? ''));
    $empresa = strtoupper(trim($_POST['empresa'] ?? ''));
    $tipo   = trim($_POST['tipo'] ?? 'VISITA');

    $stmt_chk = $conn->prepare("SELECT dni FROM fuerza_laboral WHERE dni = ?");
    $stmt_chk->bind_param("s", $dni);
    $stmt_chk->execute();
    if ($stmt_chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'msg' => 'El DNI ya está registrado.']);
        exit;
    }

    $cargo_vis = 'VISITANTE';
    $stmt_ins = $conn->prepare(
        "INSERT INTO fuerza_laboral (dni, nombres, apellidos, empresa, tipo_personal, area, cargo, estado_validacion)
         VALUES (?, ?, '-', ?, ?, '-', ?, 'ACTIVO')"
    );
    $stmt_ins->bind_param("sssss", $dni, $nombre, $empresa, $tipo, $cargo_vis);
    if ($stmt_ins->execute()) {
        echo json_encode(['success' => true, 'nombre_completo' => $nombre]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al guardar.']);
    }
    exit;
}

// ---------------------------------------------------------
// 1. LIMPIEZA DE MENSAJES
// ---------------------------------------------------------
if (isset($_SESSION['temp_msg'])) {
    $mensaje = $_SESSION['temp_msg'];
    $tipo_mensaje = $_SESSION['temp_type'];
    unset($_SESSION['temp_msg']);
    unset($_SESSION['temp_type']);
}

// ---------------------------------------------------------
// 2. BÚSQUEDA CONDUCTOR
// ---------------------------------------------------------
if (isset($_POST['dni_buscar'])) {
    $busqueda = mysqli_real_escape_string($conn, $_POST['dni_buscar']);
    $sql = "SELECT * FROM fuerza_laboral WHERE dni = '$busqueda' LIMIT 1";
    $res = mysqli_query($conn, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        $persona = mysqli_fetch_assoc($res);
    } else {
        $nuevo_dni = $busqueda;
        $mensaje = "DNI NO REGISTRADO. COMPLETE LOS DATOS.";
        $tipo_mensaje = "info"; 
    }
}

// ---------------------------------------------------------
// 3. REGISTRO (Conductor Nuevo o Existente)
// ---------------------------------------------------------
if (isset($_POST['btn_registrar'])) {
    $dni_c = $_POST['dni_c']; 
    $nom_c = strtoupper($_POST['nom_c']); 
    $emp_c = strtoupper($_POST['emp_c']);
    
    // Si es nuevo conductor, lo creamos primero
    if (isset($_POST['es_nuevo']) && $_POST['es_nuevo'] == '1') {
        $tipo_p = $_POST['tipo_personal_new']; 
        $check = mysqli_query($conn, "SELECT dni FROM fuerza_laboral WHERE dni = '$dni_c'");
        if (mysqli_num_rows($check) == 0) {
            $sql_new = "INSERT INTO fuerza_laboral (dni, nombres, apellidos, empresa, tipo_personal, area, cargo, estado_validacion) 
                        VALUES ('$dni_c', '$nom_c', '-', '$emp_c', '$tipo_p', '-', 'CONDUCTOR', 'ACTIVO')"; 
            mysqli_query($conn, $sql_new);
        }
    }

    $ac1 = $_POST['ac1'] ?: 'NINGUNO'; $ac2 = $_POST['ac2'] ?: 'NINGUNO';
    $ac3 = $_POST['ac3'] ?: 'NINGUNO'; $ac4 = $_POST['ac4'] ?: 'NINGUNO';
    $obs = $_POST['obs'] ?: '';
    
    $anfitrion = isset($_POST['anfitrion']) ? strtoupper($_POST['anfitrion']) : '-';
    $motivo    = isset($_POST['motivo']) ? strtoupper($_POST['motivo']) : '-';
    
    $tipo_mov = $_POST['tipo_movimiento']; 
    $destino  = $_POST['destino'] ?: 'INTERIOR MINA'; 
    $op  = $_SESSION['usuario'];

    $sql_reg = "INSERT INTO registros_garita (dni_conductor, nombre_conductor, empresa, tipo_movimiento, destino, acompanante_1, acompanante_2, acompanante_3, acompanante_4, observaciones, anfitrion, motivo, operador_garita, fecha_ingreso) 
                VALUES ('$dni_c', '$nom_c', '$emp_c', '$tipo_mov', '$destino', '$ac1', '$ac2', '$ac3', '$ac4', '$obs', '$anfitrion', '$motivo', '$op', NOW())";
    
    if (mysqli_query($conn, $sql_reg)) {
        $_SESSION['temp_msg'] = "REGISTRO EXITOSO: $nom_c ($tipo_mov)";
        $_SESSION['temp_type'] = "success";
        header("Location: control_conductor.php"); 
        exit();
    } else {
        $mensaje = "Error al registrar movimiento: " . mysqli_error($conn);
        $tipo_mensaje = "error";
    }
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
    <title>SITRAN | Control de Acceso</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root { --h-gold: #c5a059; --h-dark: #111827; --h-gray-bg: #f3f4f6; --h-white: #ffffff; --visita: #f59e0b; }
        body { font-family: 'Inter', sans-serif; background-color: var(--h-gray-bg); margin: 0; padding-bottom: 50px; }

        /* HEADER */
        .header-main { background: var(--h-white); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid var(--h-gold); position: sticky; top: 0; z-index: 1000; }
        .logo-header { height: 45px; }

        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .alert-box { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: 700; animation: fadeIn 0.5s; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } 
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        /* CÁMARA */
        #camera-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; flex-direction: column; align-items: center; justify-content: center; }
        .cam-frame { width: 98%; max-width: 450px; border: 2px solid var(--h-gold); border-radius: 20px; overflow: hidden; background: #000; position: relative; }
        .scan-line { position: absolute; width: 100%; height: 2px; background: red; top: 50%; box-shadow: 0 0 15px red; z-index: 10; opacity: 0.8; animation: scanMove 2s infinite linear; }
        @keyframes scanMove { 0% {top: 10%; opacity: 0;} 50% {opacity: 1;} 100% {top: 90%; opacity: 0;} }
        .close-cam { margin-top: 20px; padding: 15px 40px; border-radius: 50px; background: #dc2626; color: white; border: none; font-weight: 800; font-size: 16px; cursor: pointer; }

        /* BUSCADOR */
        .driver-search-box { background: var(--h-dark); color: white; border-radius: 24px; padding: 40px 20px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.2); position: relative; overflow: hidden; }
        .driver-search-box::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: var(--h-gold); }
        .big-scan-btn { background: var(--h-gold); color: white; border: none; width: 90px; height: 90px; border-radius: 50%; font-size: 34px; cursor: pointer; box-shadow: 0 0 25px rgba(197, 160, 89, 0.6); margin-bottom: 20px; animation: pulse-gold 2s infinite; }
        @keyframes pulse-gold { 0% {box-shadow: 0 0 0 0 rgba(197, 160, 89, 0.8);} 70% {box-shadow: 0 0 0 20px rgba(197, 160, 89, 0);} 100% {box-shadow: 0 0 0 0 rgba(197, 160, 89, 0.8);} }
        .input-driver { background: rgba(255,255,255,0.15); border: 2px solid #4b5563; color: white; padding: 18px; width: 75%; border-radius: 14px; text-align: center; font-family: 'Orbitron', sans-serif; font-size: 20px; outline: none; }
        .input-driver:focus { border-color: var(--h-gold); }

        /* REGISTRO */
        .result-card { background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); animation: slideUp 0.4s; }
        @keyframes slideUp { from {transform: translateY(20px); opacity:0;} to {transform: translateY(0); opacity:1;} }
        .card-header { background: var(--h-dark); padding: 35px 25px; text-align: center; border-bottom: 4px solid var(--h-gold); }
        .card-header h2 { margin: 0; color: white; font-family: 'Orbitron', sans-serif; text-transform: uppercase; font-size: 24px; letter-spacing: 1px; }
        .card-header p { margin: 8px 0 0; color: var(--h-gold); font-size: 13px; letter-spacing: 1px; font-weight: 600; text-transform: uppercase; }
        .form-section { padding: 25px; }

        /* SWITCH MOVIMIENTO */
        .switch-box { background: #f3f4f6; padding: 10px; border-radius: 15px; display: flex; gap: 10px; margin-bottom: 20px; border: 1px solid #e5e7eb; }
        .switch-option { flex: 1; padding: 15px; text-align: center; font-weight: 800; cursor: pointer; border-radius: 12px; transition: 0.3s; font-size: 14px; text-transform: uppercase; color: #9ca3af; }
        .switch-option.active-in { background: #166534; color: white; box-shadow: 0 5px 15px rgba(22, 101, 52, 0.3); }
        .switch-option.active-out { background: #991b1b; color: white; box-shadow: 0 5px 15px rgba(153, 27, 27, 0.3); }

        /* TABS TIPO (NUEVO) */
        .type-tabs { display: flex; gap: 5px; margin-bottom: 20px; background: #f3f4f6; padding: 5px; border-radius: 10px; }
        .type-tab { flex: 1; text-align: center; padding: 10px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; color: #6b7280; }
        .type-tab.active-perm { background: var(--h-dark); color: white; }
        .type-tab.active-visita { background: var(--visita); color: white; }

        /* ACOMPAÑANTES */
        .companion-row { display: flex; gap: 8px; margin-bottom: 15px; align-items: flex-end; }
        .input-group { flex: 1; }
        .label-mini { font-size: 10px; font-weight: 800; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .input-comp { width: 100%; padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; box-sizing: border-box; background: #f9fafb; transition: 0.3s; }
        .input-comp:focus { border-color: var(--h-gold); background: white; }
        .btn-mini { height: 48px; width: 48px; border: none; border-radius: 10px; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; }
        .btn-scan { background: var(--h-dark); color: var(--h-gold); }
        .btn-search { background: #e5e7eb; color: #374151; }

        .btn-confirm { width: 100%; background: var(--h-gold); color: white; padding: 20px; border: none; border-radius: 14px; font-weight: 800; font-size: 18px; margin-top: 15px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition:0.3s; }
        .btn-confirm.btn-out { background: #991b1b; }

        /* HISTORIAL */
        .history-box { margin-top: 40px; }
        .history-item { 
            background: white; padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; 
            border-left: 5px solid #166534; 
            cursor: pointer; transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .history-item.item-out { border-left-color: #991b1b; }
        
        .h-name { font-weight: 700; font-size: 14px; color: #374151; display:flex; align-items:center; gap:8px; }
        .h-desc { font-size: 11px; color: #9ca3af; margin-top: 2px; }
        .h-time { font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--h-dark); font-size: 12px; }
        
        .badge-ac { background: #f3f4f6; color: var(--h-dark); padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 800; display:flex; align-items:center; gap:4px; }
        .item-out .badge-ac { background: #fef2f2; color: #991b1b; }

        /* MODAL NUEVO ACOMPAÑANTE */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal-card { background: white; padding: 25px; border-radius: 20px; width: 85%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); animation: popIn 0.3s; }
        @keyframes popIn { from{transform:scale(0.9);} to{transform:scale(1);} }

        /* TABS DEL MODAL FANTASMA */
        .type-tab.active-p { background: var(--h-dark); color: white; }
        .type-tab.active-v { background: var(--visita); color: white; }

        /* MODAL DETALLES */
        #details-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9990; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .details-content { background: white; padding: 30px; border-radius: 24px; width: 85%; max-width: 400px; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: popIn 0.3s; }
        .details-row { border-bottom: 1px solid #eee; padding: 12px 0; font-size: 14px; color: #374151; }
        .details-label { font-weight: 800; color: var(--h-gold); font-size: 10px; text-transform: uppercase; display: block; margin-bottom: 3px; letter-spacing: 1px; }
    </style>
</head>
<body>

<div id="camera-modal" class="modal-overlay">
    <div style="text-align:center; width:100%; max-width:400px;">
        <div class="cam-frame"><div class="scan-line"></div><div id="reader"></div></div>
        <button class="close-cam" onclick="closeCamera()"><i class="fa-solid fa-xmark"></i> CERRAR CÁMARA</button>
    </div>
</div>

<div id="modal-new-ac" class="modal-overlay">
    <div class="modal-card">
        <h3 style="margin:0 0 15px; color:var(--h-dark); text-align:center; font-family:'Orbitron';">NUEVO ACOMPAÑANTE</h3>
        
        <input type="hidden" id="new_ac_target"> <label class="label-mini">DNI</label>
        <input type="text" id="new_ac_dni" class="input-comp" readonly style="background:#e5e7eb;">
        
        <label class="label-mini">TIPO DE ACOMPAÑANTE</label>
        <div class="type-tabs">
            <div class="type-tab active-p" id="tab-ac-p" onclick="setAcType('PERMANENTE')">PERMANENTE</div>
            <div class="type-tab" id="tab-ac-v" onclick="setAcType('VISITA')">VISITA</div>
        </div>
        <input type="hidden" id="new_ac_type" value="PERMANENTE">

        <label class="label-mini">NOMBRE COMPLETO</label>
        <input type="text" id="new_ac_nombre" class="input-comp" placeholder="Ej: Juan Perez">
        
        <label class="label-mini">EMPRESA</label>
        <input type="text" id="new_ac_empresa" class="input-comp" placeholder="Ej: Contratistas SAC">

        <button type="button" onclick="saveNewCompanion()" style="width:100%; padding:15px; background:var(--h-gold); color:white; border:none; border-radius:10px; font-weight:800; cursor:pointer;">GUARDAR Y AGREGAR</button>
        <button type="button" onclick="closeNewAc()" style="width:100%; margin-top:10px; background:transparent; color:#ef4444; border:none; font-weight:700; cursor:pointer;">CANCELAR</button>
    </div>
</div>

<div id="details-modal" class="modal-overlay" onclick="closeDetails()">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div style="text-align:center; margin-bottom:20px;"><i class="fa-solid fa-clipboard-list" style="font-size:30px; color:var(--h-gold);"></i><h3 style="margin:10px 0 0; color:var(--h-dark); font-family:'Orbitron';">DETALLE</h3></div>
        <div id="modal-body"></div>
        <button onclick="closeDetails()" style="width:100%; padding:15px; background:var(--h-dark); color:white; border:none; border-radius: 14px; margin-top:25px; font-weight:800;">CERRAR VENTANA</button>
    </div>
</div>

<nav class="header-main">
    <a href="control_garita_principal.php" style="color:#333; font-size:22px;"><i class="fa-solid fa-chevron-left"></i></a>
    <div style="display:flex; gap:15px;">
        <img src="Assets Index/logo.png" class="logo-header">
        <img src="Assets Index/seguridadcivil.png" class="logo-header">
    </div>
    <div style="width:22px;"></div>
</nav>

<div class="container">
    
    <?php if ($mensaje): ?> <div class="alert-box <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div> <?php endif; ?>

    <?php if (!$persona && !$nuevo_dni): ?>
        <div class="driver-search-box">
            <button onclick="openScanner('driver')" class="big-scan-btn"><i class="fa-solid fa-qrcode"></i></button>
            <h2 style="font-family:'Orbitron'; margin:15px 0 25px; letter-spacing:1px;">ESCANEAR CONDUCTOR</h2>
            <form method="POST" id="formDriver">
                <input type="text" name="dni_buscar" id="inputDriver" class="input-driver" placeholder="Escribe DNI o usa el botón..." autocomplete="off">
            </form>
            <p style="font-size:12px; color:#9ca3af; margin-top:20px; font-weight:600;">Presiona el botón dorado para activar la cámara</p>
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
                    <h2>NUEVO CONDUCTOR</h2>
                    <p>DNI: <?php echo $nuevo_dni; ?> | REGISTRO RÁPIDO</p>
                <?php endif; ?>
            </div>
            
            <div class="form-section">
                <form method="POST">
                    <input type="hidden" name="dni_c" value="<?php echo $persona ? $persona['dni'] : $nuevo_dni; ?>">
                    
                    <?php if (!$persona): ?>
                        <input type="hidden" name="es_nuevo" value="1">
                        
                        <label class="label-mini">TIPO DE PERSONAL</label>
                        <div class="type-tabs">
                            <div class="type-tab active-perm" id="m-perm" onclick="setMainType('PERMANENTE')">PERMANENTE</div>
                            <div class="type-tab" id="m-vis" onclick="setMainType('VISITA')">VISITA</div>
                        </div>
                        <input type="hidden" name="tipo_personal_new" id="main_type" value="PERMANENTE">

                        <label class="label-mini">NOMBRE COMPLETO</label>
                        <input type="text" name="nom_c" class="input-comp" required placeholder="Nombres y Apellidos...">
                        
                        <label class="label-mini">EMPRESA</label>
                        <input type="text" name="emp_c" class="input-comp" required placeholder="Empresa...">
                    <?php else: ?>
                        <input type="hidden" name="nom_c" value="<?php echo $persona['nombres'] . ' ' . $persona['apellidos']; ?>">
                        <input type="hidden" name="emp_c" value="<?php echo $persona['empresa']; ?>">
                    <?php endif; ?>

                    <div id="visita-fields" style="display: <?php echo (isset($persona) && $persona['tipo_personal']=='VISITA') ? 'block' : 'none'; ?>; background:#fff7ed; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #fed7aa;">
                        <label class="label-mini" style="color:#ea580c;">ANFITRIÓN</label>
                        <input type="text" name="anfitrion" class="input-comp" placeholder="Ing. Residente..." style="border-color:#fdba74;">
                        <label class="label-mini" style="color:#ea580c;">MOTIVO</label>
                        <input type="text" name="motivo" class="input-comp" placeholder="Reunión..." style="border-color:#fdba74;">
                    </div>

                    <label class="label-mini">TIPO DE MOVIMIENTO</label>
                    <div class="switch-box">
                        <div class="switch-option active-in" id="opt-in" onclick="setMovimiento('INGRESO')"><i class="fa-solid fa-arrow-right-to-bracket"></i> INGRESO</div>
                        <div class="switch-option" id="opt-out" onclick="setMovimiento('SALIDA')"><i class="fa-solid fa-arrow-right-from-bracket"></i> SALIDA</div>
                    </div>
                    <input type="hidden" name="tipo_movimiento" id="tipo_movimiento" value="INGRESO">

                    <div id="destino-box" style="display:none; margin-bottom:20px;">
                        <label class="label-mini" style="color:#6b7280;">DESTINO DE SALIDA</label>
                        <input type="text" name="destino" id="input-destino" class="input-comp" placeholder="Ej: Lima...">
                    </div>

                    <div style="margin-bottom:25px;">
                        <span style="font-size:11px; font-weight:800; color:var(--h-gold); letter-spacing:1px; text-transform:uppercase;">REGISTRO DE ACOMPAÑANTES</span>
                        <div style="height:2px; background:#f3f4f6; margin:8px 0 20px;"></div>
                        <?php for($i=1; $i<=4; $i++): ?>
                        <div class="companion-row">
                            <div class="input-group">
                                <label class="label-mini">Acompañante 0<?php echo $i; ?></label>
                                <input type="text" name="ac<?php echo $i; ?>" id="ac<?php echo $i; ?>" class="input-comp" placeholder="DNI o Nombre...">
                            </div>
                            <button type="button" class="btn-mini btn-search" onclick="buscarManual('ac<?php echo $i; ?>')"><i class="fa-solid fa-magnifying-glass"></i></button>
                            <button type="button" class="btn-mini btn-scan" onclick="openScanner('ac<?php echo $i; ?>')"><i class="fa-solid fa-barcode"></i></button>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div style="margin-bottom:25px;">
                        <label class="label-mini">Observaciones</label>
                        <textarea name="obs" class="input-comp" rows="3" placeholder="Comentarios..."></textarea>
                    </div>

                    <button type="submit" name="btn_registrar" id="btn-submit" class="btn-confirm">
                        <i class="fa-solid fa-shield-check"></i> <?php echo ($persona) ? 'REGISTRAR INGRESO' : 'GUARDAR Y REGISTRAR'; ?>
                    </button>
                    
                    <button type="button" onclick="location.href='control_conductor.php'" style="width:100%; background:transparent; border:none; color:#ef4444; font-weight:700; margin-top:15px; cursor:pointer;">CANCELAR</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="history-box">
        <h3 style="font-size:12px; color:#6b7280; text-transform:uppercase; margin-bottom:15px; font-weight:800; letter-spacing:1px;">Últimos Registros</h3>
        <?php while($row = mysqli_fetch_assoc($res_hist)): 
            $data_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            $is_out = ($row['tipo_movimiento'] == 'SALIDA');
            $num_ac = 0;
            if($row['acompanante_1']!='NINGUNO') $num_ac++;
            if($row['acompanante_2']!='NINGUNO') $num_ac++;
            if($row['acompanante_3']!='NINGUNO') $num_ac++;
            if($row['acompanante_4']!='NINGUNO') $num_ac++;
        ?>
            <div class="history-item <?php echo $is_out ? 'item-out' : ''; ?>" onclick="showHistoryDetails('<?php echo $data_json; ?>')">
                <div>
                    <div class="h-name">
                        <?php echo explode(' ', $row['nombre_conductor'])[0]; ?>
                        <?php if($num_ac > 0): ?>
                            <span class="badge-ac"><i class="fa-solid fa-users"></i> +<?php echo $num_ac; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="h-desc" style="font-size:11px; color:#9ca3af;">
                        <?php echo $row['empresa']; ?> | <?php echo $row['tipo_movimiento']; ?>
                    </div>
                </div>
                <div class="h-time"><?php echo date('H:i', strtotime($row['fecha_ingreso'])); ?></div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<audio id="beep" src="https://www.soundjay.com/buttons/beep-01a.mp3"></audio>

<script>
    // --- LÓGICA DE ACOMPAÑANTE FANTASMA ---
    function openNewAcModal(dni, inputId) {
        $("#new_ac_target").val(inputId);
        $("#new_ac_dni").val(dni);
        $("#new_ac_nombre").val('');
        $("#new_ac_empresa").val('');
        setAcType('PERMANENTE');
        $("#modal-new-ac").fadeIn().css("display", "flex");
    }

    function closeNewAc() { $("#modal-new-ac").fadeOut(); }

    function setAcType(type) {
        $("#new_ac_type").val(type);
        if(type === 'PERMANENTE'){
            $("#tab-ac-p").addClass("active-p").removeClass("active-v"); 
            $("#tab-ac-v").removeClass("active-v").removeClass("active-p");
        } else {
            $("#tab-ac-v").addClass("active-v").removeClass("active-p");
            $("#tab-ac-p").removeClass("active-p").removeClass("active-v");
        }
    }

    function saveNewCompanion() {
        const data = {
            ajax_create_companion: true,
            dni: $("#new_ac_dni").val(),
            nombre: $("#new_ac_nombre").val(),
            empresa: $("#new_ac_empresa").val(),
            tipo: $("#new_ac_type").val()
        };

        if(!data.nombre || !data.empresa) { alert("Complete los datos"); return; }

        $.post("", data, function(res) {
            // Manejo directo de la respuesta JSON
            if(res.success) {
                const target = $("#new_ac_target").val();
                $("#" + target).val(res.nombre_completo + " (" + data.dni + ")");
                $("#" + target).css("border-color", "#16a34a").css("background", "#f0fdf4");
                closeNewAc();
            } else {
                alert("Error BD: " + res.msg);
            }
        }, "json")
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.log("Respuesta cruda del error:", jqXHR.responseText);
            alert("Error de Servidor (Revise consola): " + textStatus);
        });
    }

    // --- LÓGICA PRINCIPAL ---
    function setMainType(type) {
        $("#main_type").val(type);
        if(type==='PERMANENTE') {
            $("#m-perm").addClass('active-p'); $("#m-vis").removeClass('active-v');
            $("#visita-fields").hide();
        } else {
            $("#m-vis").addClass('active-v'); $("#m-perm").removeClass('active-p');
            $("#visita-fields").show();
        }
    }

    function setMovimiento(tipo) {
        const input = document.getElementById('tipo_movimiento');
        const btn = document.getElementById('btn-submit');
        const destBox = document.getElementById('destino-box');
        const destInput = document.getElementById('input-destino');
        input.value = tipo;

        if (tipo === 'INGRESO') {
            document.getElementById('opt-in').classList.add('active-in');
            document.getElementById('opt-out').classList.remove('active-out');
            btn.innerHTML = '<i class="fa-solid fa-shield-check"></i> <?php echo ($persona) ? "REGISTRAR INGRESO" : "GUARDAR Y REGISTRAR"; ?>';
            btn.classList.remove('btn-out');
            destBox.style.display = 'none'; destInput.required = false;
        } else {
            document.getElementById('opt-in').classList.remove('active-in');
            document.getElementById('opt-out').classList.add('active-out');
            btn.innerHTML = '<i class="fa-solid fa-person-walking-arrow-right"></i> REGISTRAR SALIDA';
            btn.classList.add('btn-out');
            destBox.style.display = 'block'; destInput.required = true;
            destInput.focus();
        }
    }

    let html5QrcodeScanner;
    let targetInputId = null;

    function openScanner(targetId) {
        targetInputId = targetId;
        document.getElementById('camera-modal').style.display = 'flex';
        html5QrcodeScanner = new Html5Qrcode("reader");
        const config = { fps: 15, qrbox: { width: 300, height: 180 }, aspectRatio: 1.0 };
        html5QrcodeScanner.start({ facingMode: "environment" }, config, onScanSuccess)
        .catch(err => { alert("Error cámara: " + err); closeCamera(); });
    }

    function closeCamera() {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                document.getElementById('camera-modal').style.display = 'none';
                document.getElementById('reader').innerHTML = "";
            });
        } else { document.getElementById('camera-modal').style.display = 'none'; }
    }

    function onScanSuccess(decodedText) {
        let limpio = decodedText.trim();
        if (!/^\d{8}$/.test(limpio)) { return; }

        document.getElementById('beep').play();
        closeCamera();
        if (targetInputId === 'driver') {
            document.getElementById('inputDriver').value = limpio;
            document.getElementById('formDriver').submit();
        } else { fetchName(limpio, targetInputId); }
    }

    function buscarManual(inputId) {
        let dni = document.getElementById(inputId).value;
        if(dni.length >= 5) { fetchName(dni, inputId); } else { alert("DNI inválido"); }
    }

    function fetchName(dni, inputId) {
        const inputField = document.getElementById(inputId);
        inputField.style.opacity = '0.5';
        
        fetch('buscar_persona.php?dni=' + dni)
        .then(response => response.json())
        .then(data => {
            inputField.style.opacity = '1';
            if (data.success) {
                inputField.value = data.nombre + " (" + dni + ")";
                inputField.style.borderColor = "#16a34a";
            } else {
                if(confirm("DNI " + dni + " no encontrado. ¿Registrar Nuevo Acompañante?")) {
                    openNewAcModal(dni, inputId);
                } else {
                    inputField.value = dni; 
                }
            }
        });
    }

    function showHistoryDetails(jsonStr) {
        const data = JSON.parse(jsonStr);
        const formatAc = (ac) => ac !== 'NINGUNO' ? `<li>${ac}</li>` : '';
        let acList = formatAc(data.acompanante_1) + formatAc(data.acompanante_2) + formatAc(data.acompanante_3) + formatAc(data.acompanante_4);
        let destInfo = data.tipo_movimiento === 'SALIDA' ? `<div class="details-row"><span class="details-label" style="color:var(--h-gold);">Destino</span> ${data.destino}</div>` : '';

        let html = `
            <div class="details-row"><span class="details-label">Movimiento</span> <strong style="${data.tipo_movimiento === 'SALIDA' ? 'color:#991b1b' : 'color:#166534'}">${data.tipo_movimiento}</strong></div>
            <div class="details-row"><span class="details-label">Conductor</span> ${data.nombre_conductor}</div>
            <div class="details-row"><span class="details-label">Empresa</span> ${data.empresa}</div>
            ${destInfo}
            <div class="details-row"><span class="details-label">Hora</span> ${data.fecha_ingreso}</div>
            <div class="details-row"><span class="details-label">Acompañantes</span><ul style="margin:5px 0 0 15px; padding:0; list-style:circle;">${acList || '<i>Ninguno</i>'}</ul></div>
            <div class="details-row"><span class="details-label">Obs</span> ${data.observaciones}</div>
        `;
        document.getElementById('modal-body').innerHTML = html;
        document.getElementById('details-modal').style.display = 'flex';
    }

    function closeDetails() { document.getElementById('details-modal').style.display = 'none'; }
</script>

</body>
</html>