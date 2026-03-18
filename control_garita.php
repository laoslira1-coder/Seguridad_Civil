<?php
ob_start();
session_start();

date_default_timezone_set('America/Lima');

// Desactivar excepciones mysqli para compatibilidad con el manejo manual de errores
$driver = new mysqli_driver();
$driver->report_mode = MYSQLI_REPORT_OFF;

// 2. CONEXIÓN
require_once 'config.php';
if (!$conn) { ob_clean(); http_response_code(503); die('Error de conexión a base de datos.'); }
// Validar Sesión
if (!isset($_SESSION['usuario'])) { 
    if(isset($_POST['ajax_create_companion'])) { ob_clean(); echo json_encode(['success' => false, 'msg' => 'Sesión caducada.']); exit; }
    header("Location: index.php"); exit(); 
}

$mensaje = null;
$tipo_mensaje = "";

// ==============================================================================
// AUTO-PARCHE DE BASE DE DATOS (ELIMINA ERRORES FANTASMAS)
// Agrega automáticamente las columnas que faltan para que no rebote el guardado
// ==============================================================================
$revisar_columnas = [
    'anio' => "VARCHAR(10) DEFAULT '-'",
    'placa_remolque' => "VARCHAR(20) DEFAULT '-'",
    'soat_vcto' => "DATE DEFAULT '1998-01-01'",
    'empresa_transporte' => "VARCHAR(100) DEFAULT '-'"
];
foreach($revisar_columnas as $columna => $tipo) {
    $chk = $conn->prepare("SHOW COLUMNS FROM vehiculos LIKE ?");
    if ($chk) {
        $chk->bind_param("s", $columna);
        $chk->execute();
        if($chk->get_result()->num_rows == 0) {
            mysqli_query($conn, "ALTER TABLE vehiculos ADD `$columna` $tipo");
        }
    }
}

// ==============================================================================
// 0. AUTO-CREACIÓN DE TABLA JEFES Y AUTORIZADORES (Mejora Arquitectura)
// ==============================================================================
$chk_jefes = mysqli_query($conn, "SHOW TABLES LIKE 'jefes_turno'");
if ($chk_jefes && mysqli_num_rows($chk_jefes) == 0) {
    mysqli_query($conn, "CREATE TABLE jefes_turno (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        rol VARCHAR(50) NOT NULL,
        cargo_desc VARCHAR(100) NOT NULL,
        estado VARCHAR(20) DEFAULT 'ACTIVO'
    )");
    
    // Insertar datos por defecto al crear la base de datos
    mysqli_query($conn, "INSERT INTO jefes_turno (nombre, rol, cargo_desc) VALUES 
        ('MANUEL ADRIAN PERALTA ESPINOZA', 'AUTORIZADOR', 'Autorizador de Ingreso'),
        ('BENJAMIN MAMANI FLORES', 'AUTORIZADOR', 'Autorizador de Ingreso'),
        ('JORGE TACO LLOSA', 'JEFE_TURNO', 'Jefe de Guardia'),
        ('FREDY ACHIRCANA TORRES', 'JEFE_TURNO', 'Jefe de Guardia')");
}
// ==============================================================================

// --- TRADUCTOR DE FECHAS (Evita que la Base de Datos rechace la actualización) ---
function formatDateForDB($dateStr) {
    $dateStr = trim($dateStr);
    if (empty($dateStr)) return '1998-01-01'; 
    if (strtoupper($dateStr) === 'VIGENTE') return '2099-01-01'; // Fecha mágica si digitan "VIGENTE"
    
    // Si viene como DD/MM/YYYY, lo voltea a YYYY-MM-DD
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return $dateStr;
}

// ---------------------------------------------------------
// 0. AJAX: CREACIÓN RÁPIDA DE ACOMPAÑANTE (CON CARGO)
// ---------------------------------------------------------
if (isset($_POST['ajax_create_companion'])) {
    ob_clean();
    header('Content-Type: application/json');

    $dni    = preg_replace('/[^0-9]/', '', $_POST['dni'] ?? '');
    $nombre = strtoupper(trim($_POST['nombre'] ?? ''));
    $empresa = strtoupper(trim($_POST['empresa'] ?? ''));
    $cargo  = strtoupper(trim($_POST['cargo'] ?? 'VISITA'));
    $tipo   = trim($_POST['tipo'] ?? 'VISITA');

    $stmt_chk = $conn->prepare("SELECT dni FROM fuerza_laboral WHERE dni = ?");
    $stmt_chk->bind_param("s", $dni);
    $stmt_chk->execute();
    if ($stmt_chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'msg' => 'El DNI ya está registrado.']); exit;
    }

    $stmt_ins = $conn->prepare(
        "INSERT INTO fuerza_laboral (dni, nombres, apellidos, empresa, tipo_personal, area, cargo, estado_validacion)
         VALUES (?, ?, '-', ?, ?, '-', ?, 'ACTIVO')"
    );
    $stmt_ins->bind_param("sssss", $dni, $nombre, $empresa, $tipo, $cargo);
    if ($stmt_ins->execute()) {
        echo json_encode(['success' => true, 'nombre_completo' => $nombre, 'cargo' => $cargo, 'empresa' => $empresa]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al guardar.']);
    }
    exit;
}

// RECUPERAR MENSAJES DE SESIÓN
if (isset($_SESSION['temp_msg'])) {
    $mensaje = $_SESSION['temp_msg'];
    $tipo_mensaje = $_SESSION['temp_type'];
    unset($_SESSION['temp_msg']); unset($_SESSION['temp_type']);
}

// PARAMETROS URL
$fase_actual = isset($_GET['fase']) ? $_GET['fase'] : 'vehiculo';
$auto_placa  = isset($_GET['placa']) ? $_GET['placa'] : '';
$auto_mov    = isset($_GET['mov']) ? $_GET['mov'] : '';
$auto_emp    = isset($_GET['empresa']) ? $_GET['empresa'] : '';
$auto_auth   = isset($_GET['auth']) ? $_GET['auth'] : ''; 

// ---------------------------------------------------------
// LOGICA 1: CREAR VEHÍCULO NUEVO
// ---------------------------------------------------------
if (isset($_POST['btn_crear_vehiculo'])) {
    $placa     = strtoupper(trim($_POST['new_placa']));
    $remolque  = strtoupper(trim($_POST['new_remolque'] ?: '-'));
    $tipo_veh  = strtoupper(trim($_POST['new_tipo']));
    $marca     = strtoupper(trim($_POST['new_marca']));
    $modelo    = strtoupper(trim($_POST['new_modelo']));
    $color     = strtoupper(trim($_POST['new_color']));
    $anio      = !empty($_POST['new_anio']) ? strtoupper(trim($_POST['new_anio'])) : '-'; 
    $empresa   = strtoupper(trim($_POST['new_empresa']));
    
    // Traducimos el SOAT antes de guardar
    $soat      = formatDateForDB($_POST['new_soat'] ?? '');
    
    $estado_auth = trim($_POST['passed_estado']); 
    // COMBINAR JEFE Y AUTORIZADO POR
    $jefe_turno  = trim($_POST['passed_jefe']);
    $solicita    = trim($_POST['selectAutoriza']);
    
    // Formato combinado: JEFE DE TURNO (Solic: QUIEN_SOLICITA)
    $autorizado_final = $jefe_turno;
    if (!empty($solicita)) {
        $autorizado_final .= " (Solic: " . $solicita . ")";
    } else {
        $autorizado_final = !empty($jefe_turno) ? $jefe_turno : 'NO ESPECIFICADO';
    }

    $movimiento  = trim($_POST['new_movimiento']); 
    $operador    = $_SESSION['usuario'];
    $obs_rechazo = isset($_POST['new_obs_rechazo']) ? strtoupper(trim($_POST['new_obs_rechazo'])) : '';

    $stmt_chk = $conn->prepare("SELECT id FROM vehiculos WHERE placa = ?");
    $stmt_chk->bind_param("s", $placa);
    $stmt_chk->execute();
    if ($stmt_chk->get_result()->num_rows > 0) {
        $mensaje = "La placa ya existe."; $tipo_mensaje = "error";
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO vehiculos (placa, placa_remolque, tipo_vehiculo, marca, modelo, anio, color, empresa_transporte, soat_vcto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("sssssssss", $placa, $remolque, $tipo_veh, $marca, $modelo, $anio, $color, $empresa, $soat);
        
        if (!$stmt_ins->execute()) {
            $_SESSION['temp_msg'] = "Error al crear Vehículo en Base de Datos.";
            $_SESSION['temp_type'] = "error";
            header("Location: control_garita.php"); exit();
        }
        
        if ($estado_auth === 'AUTORIZADO') {
            $destino_transito = 'EN TRANSITO';
            $stmt_mov = $conn->prepare("INSERT INTO registros_vehiculos (placa_unidad, placa_remolque, tipo_vehiculo, empresa, tipo_movimiento, destino, autorizado_por, operador_garita) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_mov->bind_param("ssssssss", $placa, $remolque, $tipo_veh, $empresa, $movimiento, $destino_transito, $autorizado_final, $operador);
            $stmt_mov->execute();
            
            $_SESSION['temp_msg'] = "VEHÍCULO REGISTRADO.";
            $_SESSION['temp_type'] = "success"; 
            header("Location: control_garita.php?fase=tripulacion&placa=" . urlencode($placa) . "&mov=" . urlencode($movimiento) . "&empresa=" . urlencode($empresa) . "&auth=" . urlencode($autorizado_final));
            exit();
        } else {
            $denied_mov = 'DENEGADO';
            $denied_dest = 'INGRESO RECHAZADO';
            $stmt_mov = $conn->prepare("INSERT INTO registros_vehiculos (placa_unidad, placa_remolque, tipo_vehiculo, empresa, tipo_movimiento, destino, autorizado_por, operador_garita, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_mov->bind_param("sssssssss", $placa, $remolque, $tipo_veh, $empresa, $denied_mov, $denied_dest, $denied_mov, $operador, $obs_rechazo);
            $stmt_mov->execute();

            $_SESSION['temp_msg'] = "VEHÍCULO CREADO PERO DENEGADO.";
            $_SESSION['temp_type'] = "warning"; 
            header("Location: control_garita.php"); exit();
        }
    }
}

// ---------------------------------------------------------
// LOGICA 2: REGISTRO/EDICION VEHÍCULO EXISTENTE
// ---------------------------------------------------------
if (isset($_POST['btn_registrar_vehiculo'])) {
    $placa     = strtoupper(trim($_POST['placa_final']));
    $remolque  = trim($_POST['remolque_final'] ?: '-');
    $tipo_veh  = trim($_POST['tipo_final']);
    $empresa   = trim($_POST['empresa_final']);
    $marca_ed  = trim($_POST['marca_final']);
    $modelo_ed = trim($_POST['modelo_final']);
    $color_ed  = trim($_POST['color_final']);
    $anio_ed   = !empty($_POST['anio_final']) ? trim($_POST['anio_final']) : '-'; 
    
    // Traducimos el SOAT editado
    $soat_ed   = formatDateForDB($_POST['soat_final']);
    
    $autoriza   = trim($_POST['jefe_autoriza_final']); 
    $movimiento = trim($_POST['tipo_movimiento']);
    $operador   = $_SESSION['usuario'];
    $es_rechazo = ($_POST['estado_validacion_final'] === 'NO AUTORIZADO');
    $obs_rech   = isset($_POST['obs_rechazo_final']) ? strtoupper(trim($_POST['obs_rechazo_final'])) : '';
    
    // Limpiamos la placa para asegurar que coincida con la Base de Datos
    $placa_limpia = preg_replace('/[^A-Z0-9]/', '', $placa);
    
    // UPDATE BLINDADO con Prepared Statements
    $stmt_upd = $conn->prepare("UPDATE vehiculos SET tipo_vehiculo=?, marca=?, modelo=?, color=?, anio=?, empresa_transporte=?, placa_remolque=?, soat_vcto=? WHERE REPLACE(REPLACE(placa, '-', ''), ' ', '')=?");
    $stmt_upd->bind_param("sssssssss", $tipo_veh, $marca_ed, $modelo_ed, $color_ed, $anio_ed, $empresa, $remolque, $soat_ed, $placa_limpia);
    
    if (!$stmt_upd->execute()) {
        $_SESSION['temp_msg'] = "Error al guardar cambios del Vehículo.";
        $_SESSION['temp_type'] = "error";
        header("Location: control_garita.php"); exit();
    }

    if ($es_rechazo) {
        $denied_mov = 'DENEGADO';
        $denied_dest = 'INGRESO RECHAZADO';
        $stmt_rch = $conn->prepare("INSERT INTO registros_vehiculos (placa_unidad, placa_remolque, tipo_vehiculo, empresa, tipo_movimiento, destino, autorizado_por, operador_garita, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_rch->bind_param("sssssssss", $placa, $remolque, $tipo_veh, $empresa, $denied_mov, $denied_dest, $denied_mov, $operador, $obs_rech);
        $stmt_rch->execute();
        $_SESSION['temp_msg'] = "ACCESO DENEGADO REGISTRADO.";
        $_SESSION['temp_type'] = "warning";
        header("Location: control_garita.php"); exit();
    } else {
        $destino_transito = 'EN TRANSITO';
        $stmt_ok = $conn->prepare("INSERT INTO registros_vehiculos (placa_unidad, placa_remolque, tipo_vehiculo, empresa, tipo_movimiento, destino, autorizado_por, operador_garita) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ok->bind_param("ssssssss", $placa, $remolque, $tipo_veh, $empresa, $movimiento, $destino_transito, $autoriza, $operador);
        $stmt_ok->execute();
        
        $_SESSION['temp_msg'] = "VEHÍCULO OK. PASE A PERSONAL.";
        $_SESSION['temp_type'] = "success";
        header("Location: control_garita.php?fase=tripulacion&placa=" . urlencode($placa) . "&mov=" . urlencode($movimiento) . "&empresa=" . urlencode($empresa) . "&auth=" . urlencode($autoriza));
        exit();
    }
}

// ---------------------------------------------------------
// LOGICA 3: REGISTRO FINAL (PERSONAL)
// ---------------------------------------------------------
if (isset($_POST['btn_registrar_conductor'])) {
    $dni_c = preg_replace('/[^0-9]/', '', $_POST['dni_c']); 
    $nom_c = strtoupper(trim($_POST['nom_c'])); 
    
    // Capturamos los datos editables (Empresa, Área y Cargo)
    $emp_c = strtoupper(trim($_POST['emp_c']));
    $area_c = isset($_POST['area_c']) ? strtoupper(trim($_POST['area_c'])) : '-';
    $cargo_c = isset($_POST['cargo_c']) ? strtoupper(trim($_POST['cargo_c'])) : 'VISITA';
    
    if (isset($_POST['es_nuevo']) && $_POST['es_nuevo'] == '1') {
        $tipo_p = trim($_POST['tipo_personal_new']); 
        $stmt_chk = $conn->prepare("SELECT dni FROM fuerza_laboral WHERE dni = ?");
        $stmt_chk->bind_param("s", $dni_c);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows == 0) {
            $apellido_def = '-';
            $estado_def = 'ACTIVO';
            $stmt_new = $conn->prepare("INSERT INTO fuerza_laboral (dni, nombres, apellidos, empresa, tipo_personal, area, cargo, estado_validacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_new->bind_param("ssssssss", $dni_c, $nom_c, $apellido_def, $emp_c, $tipo_p, $area_c, $cargo_c, $estado_def);
            $stmt_new->execute();
        }
    } else {
        // ACTUALIZACIÓN DE DATOS LABORALES SI EL USUARIO LOS MODIFICÓ EN PANTALLA
        $stmt_upd_p = $conn->prepare("UPDATE fuerza_laboral SET empresa=?, area=?, cargo=? WHERE dni=?");
        $stmt_upd_p->bind_param("ssss", $emp_c, $area_c, $cargo_c, $dni_c);
        $stmt_upd_p->execute();
    }

    $lic_nro = strtoupper(trim($_POST['lic_nro']));
    $lic_cat = strtoupper(trim($_POST['lic_cat_mtc']));
    
    // Traducimos las Fechas de la Licencia
    $lic_f_exp = formatDateForDB($_POST['f_expedicion']);
    $lic_f_rev = formatDateForDB($_POST['f_revalidacion']);
    
    $lic_res = strtoupper(trim($_POST['lic_restricciones']));
    $lic_gs = strtoupper(trim($_POST['lic_gs']));
    $lic_cat_mina = strtoupper(trim($_POST['lic_cat_mina']));

    $stmt_lic = $conn->prepare("INSERT INTO detalles_conductor (dni, nro_licencia, categoria_mtc, f_expedicion, f_revalidacion, restricciones, grupo_sanguineo, categoria_mina) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nro_licencia=VALUES(nro_licencia), categoria_mtc=VALUES(categoria_mtc), f_expedicion=VALUES(f_expedicion), f_revalidacion=VALUES(f_revalidacion), restricciones=VALUES(restricciones), grupo_sanguineo=VALUES(grupo_sanguineo), categoria_mina=VALUES(categoria_mina)");
    $stmt_lic->bind_param("ssssssss", $dni_c, $lic_nro, $lic_cat, $lic_f_exp, $lic_f_rev, $lic_res, $lic_gs, $lic_cat_mina);
    $stmt_lic->execute();

    // Acompañantes
    $ac1 = $_POST['ac1'] ?: 'NINGUNO'; 
    $ac2 = $_POST['ac2'] ?: 'NINGUNO';
    $ac3 = $_POST['ac3'] ?: 'NINGUNO'; 
    $ac4 = $_POST['ac4'] ?: 'NINGUNO';
    
    // DATOS DE ORIGEN Y DESTINO
    $origen_ui = isset($_POST['origen_ui']) ? strtoupper(trim($_POST['origen_ui'])) : 'NO ESPECIFICADO';
    $destino_ui = isset($_POST['destino_ui']) ? strtoupper(trim($_POST['destino_ui'])) : 'NO ESPECIFICADO';
    
    $obs_raw = $_POST['obs'] ?: '';
    $obs_final = "ORIGEN: " . $origen_ui . ". " . $obs_raw;

    $anfitrion = isset($_POST['anfitrion']) ? strtoupper(trim($_POST['anfitrion'])) : '-';
    $motivo    = isset($_POST['motivo']) ? strtoupper(trim($_POST['motivo'])) : '-';
    $tipo_mov  = trim($_POST['mov_vehiculo']);
    
    $placa_veh = trim($_POST['placa_vehiculo']);
    $autorizado_por = trim($_POST['auth_vehiculo']); 
    $op        = $_SESSION['usuario'];

    $stmt_reg = $conn->prepare("INSERT INTO registros_garita (placa_unidad, dni_conductor, nombre_conductor, empresa, tipo_movimiento, destino, acompanante_1, acompanante_2, acompanante_3, acompanante_4, observaciones, anfitrion, motivo, operador_garita, fecha_ingreso, autorizado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt_reg->bind_param("sssssssssssssss", $placa_veh, $dni_c, $nom_c, $emp_c, $tipo_mov, $destino_ui, $ac1, $ac2, $ac3, $ac4, $obs_final, $anfitrion, $motivo, $op, $autorizado_por);
    
    if ($stmt_reg->execute()) {
        $_SESSION['temp_msg'] = "REGISTRO COMPLETADO.";
        $_SESSION['temp_type'] = "success";
        header("Location: control_garita.php"); 
        exit();
    } else {
        $mensaje = "Error al guardar. Intente nuevamente."; $tipo_mensaje = "error";
    }
}

// HISTORIAL
$sql_hist = "SELECT * FROM registros_garita ORDER BY fecha_ingreso DESC LIMIT 10";
$res_hist = mysqli_query($conn, $sql_hist);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SITRAN | Control Integral</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>
    
    <style>
        :root { 
            --h-gold: #c5a059; 
            --h-dark: #1a1c1e; 
            --bg: #f3f4f6; 
            --glass: rgba(255, 255, 255, 0.95); 
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg); 
            background-image: radial-gradient(#c5a059 0.5px, transparent 0.5px); 
            background-size: 10px 10px; 
            margin: 0; padding-bottom: 80px; 
        }
        .header-main { 
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); 
            padding: 10px 20px; display: flex; justify-content: center; align-items: center; 
            border-bottom: 1px solid rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.03); height: 70px; 
        }
        .nav-left { position: absolute; left: 20px; display: flex; align-items: center; gap: 15px; }
        .logo-container { display: flex; gap: 15px; align-items: center; }
        .logo-header { height: 50px; transition: transform 0.3s; }
        .btn-back { color: var(--h-dark); font-size: 20px; text-decoration: none; }
        
        .container { max-width: 1000px; margin: 20px auto; padding: 0 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { 
            .container { 
                grid-template-columns: 1fr; 
                gap: 50px; 
                margin-top: 15px;
            } 
            .card {
                margin-bottom: 20px;
            }
        }
        
        .card { background: var(--glass); padding: 20px; border-radius: 20px; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.5); }
        .card-header { border-bottom: 3px solid var(--h-gold); padding-bottom: 10px; margin-bottom: 20px; }
        h2 { margin: 0; color: var(--h-dark); font-size: 16px; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; }
        label { display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        input, select, textarea { width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 16px; box-sizing: border-box; margin-bottom: 15px; font-weight: 600; color: #333; background: #f8fafc; transition: all 0.3s ease; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--h-gold); background: #fff; box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.15); }
        
        /* --- CLASES DE ALERTA --- */
        .input-alert { 
            background-color: #fee2e2 !important; 
            color: #991b1b !important; 
            border: 3px solid #ef4444 !important; 
            font-weight: 800 !important;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.3) !important; 
            animation: pulse-red 2s infinite; 
        }
        
        .input-alert::placeholder {
            color: #991b1b !important;
            opacity: 0.7;
        }
        
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        
        #inputPlaca { font-size: 24px; text-align: center; letter-spacing: 3px; text-transform: uppercase; font-family: 'Orbitron', sans-serif; border-color: var(--h-dark); }
        .btn-search { background: var(--h-dark); color: var(--h-gold); width: 100%; padding: 16px; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; font-size: 14px; letter-spacing: 1px; }
        .btn-register { background: linear-gradient(135deg, #166534 0%, #14532d 100%); color: white; width: 100%; padding: 18px; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; font-size: 16px; margin-top: 10px; }
        .btn-register.btn-out { background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); }
        .grid-datos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .dato-input { background: white !important; border: 1px solid #cbd5e1 !important; color: var(--h-dark); font-weight: 700; padding: 12px; font-size: 14px; }
        .full-width { grid-column: span 2; }
        .history-container { padding-left: 20px; border-left: 2px solid #e2e8f0; grid-column: span 2; margin-top: 20px; }
        @media (max-width: 768px) { .history-container { padding-left: 0; border-left: none; } }
        .history-item { background: white; border: 1px solid #f1f5f9; box-shadow: 0 2px 5px rgba(0,0,0,0.03); margin-bottom: 15px; border-radius: 12px; padding: 15px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .switch-box { background: #f3f4f6; padding: 5px; border-radius: 12px; display: flex; gap: 5px; margin-bottom: 20px; border: 1px solid #e5e7eb; }
        .switch-option { flex: 1; padding: 12px; text-align: center; font-weight: 800; cursor: pointer; border-radius: 8px; font-size: 12px; text-transform: uppercase; color: #9ca3af; }
        .switch-option.active-in { background: #166534; color: white; }
        .switch-option.active-out { background: #991b1b; color: white; }
        .hidden { display: none; }
        .swal2-popup { font-family: 'Inter', sans-serif !important; border-radius: 20px !important; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal-card { background: white; padding: 25px; border-radius: 20px; width: 85%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); animation: popIn 0.3s; }
        @keyframes popIn { from{transform:scale(0.9);} to{transform:scale(1);} }
        .type-tabs { display: flex; gap: 5px; margin-bottom: 15px; background: #e2e8f0; padding: 5px; border-radius: 10px; }
        .type-tab { flex: 1; text-align: center; padding: 10px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; color: #6b7280; }
        .type-tab.active-perm { background: var(--h-dark); color: white; }
        .type-tab.active-visita { background: #f59e0b; color: white; }
        .companion-row { display: flex; gap: 8px; margin-bottom: 10px; align-items: center; }
        .btn-mini { height: 48px; width: 48px; border: none; border-radius: 10px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .btn-scan { background: var(--h-dark); color: var(--h-gold); }
        .btn-buscar-mini { background: #e5e7eb; color: #374151; }
        
        .jefe-option {
            border: 2px solid #e5e7eb; border-radius: 12px; padding: 15px; margin-bottom: 10px;
            cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 15px; background: white;
        }
        .jefe-option.selected { border-color: var(--h-gold); background-color: #fffbeb; box-shadow: 0 4px 10px rgba(197, 160, 89, 0.2); }
        .jefe-icon { width: 40px; height: 40px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--h-dark); font-size: 18px; }
        .jefe-option.selected .jefe-icon { background: var(--h-gold); color: white; }
        .jefe-info h4 { margin: 0; font-size: 13px; font-weight: 800; color: var(--h-dark); text-transform: uppercase; }
        .jefe-info p { margin: 2px 0 0; font-size: 10px; color: #6b7280; font-weight: 600; }
    </style>
</head>
<body>

<!-- MODALES -->
<div id="camera-modal" class="modal-overlay" style="flex-direction: column;">
    <div style="text-align:center; width:100%; max-width:400px; background:#fff; padding:15px; border-radius:20px;">
        <h3 style="margin:0 0 15px; font-family:'Orbitron';">ESCANEAR</h3>
        <div id="reader" style="width:100%; border:2px solid var(--h-gold); border-radius:10px; overflow:hidden;"></div>
        <button onclick="closeCamera()" style="margin-top:20px; padding:15px; width:100%; border-radius:10px; background:#dc2626; color:white; border:none; font-weight:800;">CERRAR</button>
    </div>
</div>

<div id="modal-new-ac" class="modal-overlay">
    <div class="modal-card">
        <h3 style="margin:0 0 15px; color:var(--h-dark); text-align:center; font-family:'Orbitron';">NUEVO ACOMPAÑANTE</h3>
        <input type="hidden" id="new_ac_target"> 
        <label>DNI</label>
        <input type="text" id="new_ac_dni" readonly style="background:#e5e7eb;">
        <label>TIPO</label>
        <div class="type-tabs">
            <div class="type-tab active-perm" id="tab-ac-p" onclick="setAcType('PERMANENTE')">PERMANENTE</div>
            <div class="type-tab" id="tab-ac-v" onclick="setAcType('VISITA')">VISITA</div>
        </div>
        <input type="hidden" id="new_ac_type" value="PERMANENTE">
        
        <label>NOMBRE COMPLETO</label>
        <input type="text" id="new_ac_nombre" placeholder="Ej: Juan Perez">
        
        <label>CARGO</label>
        <input type="text" id="new_ac_cargo" placeholder="Ej: Conductor, Ayudante...">
        
        <label>EMPRESA</label>
        <input type="text" id="new_ac_empresa" placeholder="Ej: Contratistas SAC">
        
        <button type="button" onclick="saveNewCompanion()" style="width:100%; padding:15px; background:var(--h-gold); color:white; border:none; border-radius:10px; font-weight:800; cursor:pointer;">GUARDAR Y AGREGAR</button>
        <button type="button" onclick="closeNewAc()" style="width:100%; margin-top:10px; background:transparent; color:#ef4444; border:none; font-weight:700; cursor:pointer;">CANCELAR</button>
    </div>
</div>

<div id="details-modal" class="modal-overlay" onclick="closeDetails()">
    <div class="modal-card" style="padding:0; overflow:hidden;" onclick="event.stopPropagation()">
        <div style="background:var(--h-dark); padding:20px; text-align:center; border-bottom:3px solid var(--h-gold);">
            <i class="fa-solid fa-clipboard-list" style="font-size:30px; color:var(--h-gold);"></i>
            <h3 style="margin:10px 0 0; color:white; font-family:'Orbitron';">DETALLE GENERAL</h3>
        </div>
        <div id="modal-body" style="padding:20px; max-height:60vh; overflow-y:auto;"></div>
        <div style="padding:15px;">
            <button onclick="closeDetails()" style="width:100%; padding:15px; background:#e2e8f0; color:#333; border:none; border-radius: 10px; font-weight:800; cursor:pointer;">CERRAR VENTANA</button>
        </div>
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
                    <span style="font-weight:700; font-size:14px; color:var(--text-primary);">GARITA</span>
                </div>
            </div>
            <div class="topbar-right">
                <a href="reporte_excel.php" target="_blank" style="background:var(--color-success); color:white; padding:8px 15px; border-radius:8px; text-decoration:none; font-weight:700; font-size:11px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-file-excel"></i> DATA
                </a>
            </div>
        </header>

        <div class="content-body">
            <div class="container" style="max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    
    <?php if ($fase_actual !== 'tripulacion'): ?>
    
    <!-- FASE 1: VALIDACIÓN -->
    <div class="glass-card" id="cardValidacion" style="margin-bottom: 20px; align-self: start;">
        <div class="card-header"><h2><i class="fa-solid fa-shield-halved"></i> 1. Validación Previa</h2></div>
        <form method="POST" id="formMain">
            <div class="floating-group">
                <input type="text" id="inputPlaca" class="floating-input" placeholder=" " maxlength="10" autocomplete="off" style="font-size: 24px; text-align: center; letter-spacing: 3px; font-family: 'Orbitron', sans-serif; text-transform: uppercase;">
                <label class="floating-label">PLACA UNIDAD</label>
            </div>

            <label>Estado de Autorización</label>
            <select id="selectEstado" class="input-comp" onchange="toggleJefe()" style="margin-bottom: 20px;">
                <option value="">-- SELECCIONE --</option>
                <option value="AUTORIZADO">AUTORIZADO (Ingreso/Salida)</option>
                <option value="NO AUTORIZADO">NO AUTORIZADO (Registrar Rechazo)</option>
            </select>

            <div id="divJefe" class="hidden" style="margin-top:15px;">
                <label style="color:#c5a059;">Autorizado Por:</label>
                <select id="selectAutoriza" name="selectAutoriza" style="margin-bottom: 20px;">
                    <option value="">-- SELECCIONE AUTORIZADOR --</option>
                    <?php
                    $res_aut = mysqli_query($conn, "SELECT nombre FROM jefes_turno WHERE rol='AUTORIZADOR' AND estado='ACTIVO'");
                    while($row_a = mysqli_fetch_assoc($res_aut)):
                    ?>
                        <option value="<?= htmlspecialchars($row_a['nombre']) ?>"><?= htmlspecialchars($row_a['nombre']) ?></option>
                    <?php endwhile; ?>
                </select>

                <label style="color:#c5a059; margin-bottom:10px;">Jefe de Seguridad en Turno:</label>
                
                <?php
                $res_jefes = mysqli_query($conn, "SELECT nombre, cargo_desc FROM jefes_turno WHERE rol='JEFE_TURNO' AND estado='ACTIVO'");
                while($row_j = mysqli_fetch_assoc($res_jefes)):
                ?>
                <div class="jefe-option" onclick="selectJefe(this, '<?= htmlspecialchars($row_j['nombre']) ?>')">
                    <div class="jefe-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <div class="jefe-info">
                        <h4><?= htmlspecialchars($row_j['nombre']) ?></h4>
                        <p><?= htmlspecialchars($row_j['cargo_desc']) ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
                
                <input type="hidden" name="passed_jefe" id="inputJefeHidden">
            </div>

            <button type="button" onclick="validarYBuscar()" class="btn-search" style="margin-top:20px;">
                <i class="fa-solid fa-magnifying-glass"></i> VALIDAR Y BUSCAR
            </button>
        </form>
    </div>

    <!-- FASE 2: FICHA TÉCNICA -->
    <div class="glass-card" id="cardResultado" style="display:none; margin-bottom: 20px; align-self: start;">
        <div class="card-header" id="headerResultado"><h2><i class="fa-solid fa-truck-front"></i> 2. Ficha Técnica</h2></div>

        <div id="estadoEspera" style="text-align:center; padding:40px; color:#cbd5e1;">
            <i class="fa-solid fa-id-card" style="font-size:50px; margin-bottom:10px;"></i>
            <p style="font-size:12px;">Complete validación para ver datos</p>
        </div>

        <div id="fichaVehiculo" class="hidden">
            <div id="bloqueRechazo" class="hidden" style="background:#fef2f2; color:#991b1b; padding:15px; border-radius:10px; margin-bottom:15px; border:2px dashed #991b1b;">
                <div style="text-align:center; margin-bottom:10px;"><i class="fa-solid fa-hand" style="font-size:30px;"></i><br><strong style="font-size:14px;">ACCESO DENEGADO</strong></div>
                <form method="POST">
                    <input type="hidden" name="placa_final" id="placa_rechazo">
                    <label style="color:#991b1b;">OBSERVACIÓN OBLIGATORIA DEL RECHAZO:</label>
                    <textarea name="obs_rechazo" required rows="3" style="border-color:#fca5a5; background:white;" placeholder="Explique el motivo del rechazo..."></textarea>
                    <button type="submit" name="btn_rechazar_vehiculo" class="btn-register btn-out" style="margin-top:5px;">REGISTRAR RECHAZO</button>
                </form>
            </div>

            <div id="areaRegistro" class="hidden">
                <form method="POST">
                    <input type="hidden" name="placa_final" id="placa_final">
                    <input type="hidden" name="jefe_autoriza_final" id="jefe_autoriza_final">
                    <input type="hidden" name="estado_validacion_final" id="estado_validacion_final">

                    <div class="grid-datos" style="margin-bottom:15px;">
                        <div class="full-width">
                            <label>Placa Unidad</label>
                            <input type="text" value="-" id="resPlaca" readonly style="background:#e2e8f0; font-family:'Orbitron'; text-align:center; font-size:18px;">
                        </div>
                        
                        <div><label>Tipo Vehículo</label><select name="tipo_final" id="tipo_final" class="dato-input"><option value="CAMIONETA">CAMIONETA</option><option value="CAMION">CAMION</option><option value="AUTO">AUTO</option><option value="MINIBUS">MINIBUS</option><option value="VOLQUETE">VOLQUETE</option></select></div>
                        <div><label>Remolque</label><input type="text" name="remolque_final" id="remolque_final" class="dato-input"></div>
                        
                        <div><label>Marca</label><input type="text" name="marca_final" id="marca_final" class="dato-input"></div>
                        <div><label>Modelo</label><input type="text" name="modelo_final" id="modelo_final" class="dato-input"></div>
                        
                        <div><label>Color</label><input type="text" name="color_final" id="color_final" class="dato-input"></div>
                        <div>
                            <label>Año (Min: 2023)</label>
                            <input type="text" name="anio_final" id="anio_final" class="dato-input" placeholder="2025" oninput="validarAnio(this)" maxlength="4">
                            <div id="msg_error_anio_final" style="display:none; color:#dc2626; font-size:11px; font-weight:800; margin-top:-10px; margin-bottom:10px;"><i class="fa-solid fa-triangle-exclamation"></i> ANTIGÜEDAD NO PERMITIDA</div>
                        </div> 
                        
                        <div class="full-width"><label>Empresa Transportista</label><input type="text" name="empresa_final" id="empresa_final" class="dato-input"></div>
                        
                        <div class="full-width">
                            <label style="color:#166534;">Vencimiento SOAT (Manual)</label>
                            <input type="text" name="soat_final" id="soat_final" class="dato-input" style="border-color:#16a34a; background:#f0fdf4 !important; color:#166534;" placeholder="DD/MM/YYYY" maxlength="10" oninput="formatoFecha(this); validarSoat(this)">
                            <div id="msg_error_soat_final" style="display:none; color:#dc2626; font-size:11px; font-weight:800; margin-top:-10px; margin-bottom:10px;"><i class="fa-solid fa-triangle-exclamation"></i> SOAT VENCIDO</div>
                        </div>
                    </div>

                    <label>Tipo de Movimiento</label>
                    <div class="switch-box">
                        <div class="switch-option active-in" id="opt-in" onclick="setMovimiento('INGRESO')">INGRESO</div>
                        <div class="switch-option" id="opt-out" onclick="setMovimiento('SALIDA')">SALIDA</div>
                    </div>
                    <input type="hidden" name="tipo_movimiento" id="tipo_movimiento" value="INGRESO">

                    <button type="submit" name="btn_registrar_vehiculo" id="btnSubmit" class="btn-register">
                        <i class="fa-solid fa-arrow-right"></i> VEHÍCULO OK, PASAR A PERSONAL
                    </button>
                </form>
            </div>
            
            <button onclick="location.reload()" style="background:#f1f5f9; color:#64748b; width:100%; border:none; padding:15px; border-radius:10px; margin-top:15px; font-weight:bold; cursor:pointer;">LIMPIAR / CANCELAR</button>
        </div>

        <div id="formNuevoVehiculo" class="hidden">
            <p style="font-size:12px; color:#64748b; text-align:center; margin-bottom:15px;">Placa no encontrada. Registre los datos ahora.</p>
            <form method="POST">
                <input type="hidden" name="passed_estado" id="passed_estado">
                <input type="hidden" name="passed_jefe" id="passed_jefe">
                <label>Placa Unidad</label>
                <input type="text" name="new_placa" id="new_placa" readonly style="background:#e2e8f0; color:#64748b;">
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div><label>Tipo</label><input type="text" name="new_tipo" required></div>
                    <div><label>Remolque</label><input type="text" name="new_remolque"></div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div><label>Marca</label><input type="text" name="new_marca" required></div>
                    <div><label>Modelo</label><input type="text" name="new_modelo" required></div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div><label>Color</label><input type="text" name="new_color" required></div>
                    <div>
                        <label>Año (Min: 2023)</label>
                        <input type="text" name="new_anio" id="new_anio" placeholder="2025" oninput="validarAnio(this)" maxlength="4">
                        <div id="msg_error_new_anio" style="display:none; color:#dc2626; font-size:11px; font-weight:800; margin-top:-10px; margin-bottom:10px;"><i class="fa-solid fa-triangle-exclamation"></i> ANTIGÜEDAD NO PERMITIDA</div>
                    </div> 
                </div>

                <label>Vencimiento SOAT (Manual)</label>
                <input type="text" name="new_soat" id="new_soat" placeholder="DD/MM/YYYY" maxlength="10" oninput="formatoFecha(this); validarSoat(this)">
                <div id="msg_error_new_soat" style="display:none; color:#dc2626; font-size:11px; font-weight:800; margin-top:-10px; margin-bottom:10px;"><i class="fa-solid fa-triangle-exclamation"></i> SOAT VENCIDO</div>

                <label>Empresa Transporte</label><input type="text" name="new_empresa" required>

                <div id="divNewMovimiento" class="hidden" style="margin-top:15px;">
                    <label>¿QUÉ MOVIMIENTO ES?</label>
                    <div class="switch-box">
                        <div class="switch-option active-in" id="opt-new-in" onclick="setNewMovimiento('INGRESO')">INGRESO</div>
                        <div class="switch-option" id="opt-new-out" onclick="setNewMovimiento('SALIDA')">SALIDA</div>
                    </div>
                    <input type="hidden" name="new_movimiento" id="new_movimiento" value="INGRESO">
                </div>

                <div id="msgNewNoAuth" class="hidden" style="color:#991b1b; background:#fef2f2; padding:10px; margin-top:15px; border:1px solid #fca5a5; border-radius:10px;">
                    <label style="color:#991b1b;">OBSERVACIÓN DE RECHAZO (OBLIGATORIO)</label>
                    <textarea name="new_obs_rechazo" rows="2" placeholder="Motivo de rechazo..."></textarea>
                    <div style="text-align:center; font-size:10px; margin-top:5px;">SE GUARDARÁ COMO DENEGADO</div>
                </div>

                <button type="submit" name="btn_crear_vehiculo" id="btnSaveNew" class="btn-save-new" style="width:100%; padding:15px; border-radius:10px; border:none; background:#2563eb; color:white; font-weight:bold; margin-top:10px;">GUARDAR Y PASAR A PERSONAL</button>
            </form>
            <button onclick="location.reload()" style="background:transparent; color:#ef4444; width:100%; border:none; padding:10px; margin-top:10px; font-weight:700; cursor:pointer;">Cancelar</button>
        </div>
    </div>

    <?php else: ?>
    
    <!-- FASE 3: PERSONAL -->
    <div class="card" style="grid-column: span 2; max-width: 700px; margin: 0 auto; width: 100%; padding:0; overflow:hidden;">
        <div style="background:var(--h-dark); padding:20px; color:white; text-align:center; border-bottom:4px solid var(--h-gold);">
            <h2 style="color:white; font-size:20px; margin:0;"><i class="fa-solid fa-users-gear"></i> 3. Registro de Personal</h2>
            <p style="margin:5px 0 0; color:var(--h-gold); font-size:12px; letter-spacing:1px;">VEHÍCULO: <?php echo htmlspecialchars($auto_placa); ?> | <?php echo htmlspecialchars($auto_mov); ?></p>
        </div>

        <div style="padding:25px;">
            <label>ESCANEAR O TÍPEAR DNI DEL CONDUCTOR</label>
            <div style="display: flex; gap: 10px; margin-bottom: 25px;">
                <input type="text" id="inputDniConductor" placeholder="DNI..." maxlength="8" style="margin-bottom:0; flex:1; font-family:'Orbitron'; font-size:20px; text-align:center;" autocomplete="off" autofocus>
                <button type="button" onclick="buscarConductor()" class="btn-mini btn-buscar-mini"><i class="fa-solid fa-magnifying-glass"></i></button>
                <button type="button" onclick="openScanner('conductor')" class="btn-mini btn-scan"><i class="fa-solid fa-barcode"></i></button>
            </div>

            <form method="POST">
                <input type="hidden" name="placa_vehiculo" value="<?php echo htmlspecialchars($auto_placa); ?>">
                <input type="hidden" name="mov_vehiculo" value="<?php echo htmlspecialchars($auto_mov); ?>">
                <input type="hidden" name="auth_vehiculo" value="<?php echo htmlspecialchars($auto_auth); ?>">

                <div id="boxConductor" class="hidden">
                    <input type="hidden" name="dni_c" id="final_dni_c">
                    <input type="hidden" name="es_nuevo" id="es_nuevo_c" value="0">
                    
                    <div id="formNuevoConductor" class="hidden" style="background:#f1f5f9; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #cbd5e1;">
                        <span style="color:#0ea5e9; font-weight:800; font-size:11px;"><i class="fa-solid fa-info-circle"></i> CONDUCTOR NO REGISTRADO. COMPLETE DATOS.</span>
                        <div class="type-tabs" style="margin-top:10px;">
                            <div class="type-tab active-perm" id="tab-main-p" onclick="setMainType('PERMANENTE')">PERMANENTE</div>
                            <div class="type-tab" id="tab-main-v" onclick="setMainType('VISITA')">VISITA</div>
                        </div>
                        <input type="hidden" name="tipo_personal_new" id="main_type" value="PERMANENTE">
                    </div>

                    <!-- NOMBRE BLOQUEADO, LOS DEMÁS SIEMPRE EDITABLES -->
                    <label>NOMBRE COMPLETO (CONDUCTOR)</label>
                    <input type="text" name="nom_c" id="final_nom_c" required readonly style="background:#e2e8f0;">
                    
                    <!-- EMPRESA EDITABLE -->
                    <label>EMPRESA</label>
                    <input type="text" name="emp_c" id="final_emp_c" required>

                    <!-- ÁREA Y CARGO EDITABLES SIEMPRE VISIBLES -->
                    <label style="margin-top:15px;">DATOS LABORALES</label>
                    <div class="grid-datos" id="boxExtraDatos" style="margin-bottom:15px;">
                        <div>
                            <label class="label-mini">Área</label>
                            <input type="text" name="area_c" id="final_area_c" class="dato-input" style="margin-bottom:0;" required>
                        </div>
                        <div>
                            <label class="label-mini">Cargo</label>
                            <input type="text" name="cargo_c" id="final_cargo_c" class="dato-input" style="margin-bottom:0;" required>
                        </div>
                    </div>

                    <div style="margin-top:20px; border:1px solid #bae6fd; border-radius:10px; padding:15px; background:#f0f9ff; margin-bottom:20px;">
                        <span style="font-size:11px; font-weight:800; color:#0369a1; text-transform:uppercase; letter-spacing:1px;"><i class="fa-solid fa-id-card"></i> Datos de Licencia MTC y Mina</span>
                        <div style="height:2px; background:#e0f2fe; margin:8px 0 15px;"></div>
                        
                        <div class="grid-datos">
                            <div><label class="label-mini">N° Licencia MTC</label><input type="text" name="lic_nro" id="lic_nro" class="input-comp" style="margin-bottom:0;" required></div>
                            <div><label class="label-mini">Categoría MTC</label><input type="text" name="lic_cat_mtc" id="lic_cat_mtc" class="input-comp" style="margin-bottom:0;" required></div>
                        </div>
                        
                        <!-- CAMPOS DE TEXTO CON AUTO-BARRAS (MÁSCARA) -->
                        <div class="grid-datos" style="margin-top:15px;">
                            <div>
                                <label class="label-mini">F. Expedición</label>
                                <input type="text" name="f_expedicion" id="f_expedicion" class="dato-input" style="margin-bottom:0;" placeholder="DD/MM/YYYY" maxlength="10" oninput="formatoFecha(this)">
                            </div>
                            <div>
                                <label class="label-mini">F. Revalidación</label>
                                <input type="text" name="f_revalidacion" id="f_revalidacion" class="dato-input" style="margin-bottom:0;" placeholder="DD/MM/YYYY" maxlength="10" oninput="formatoFecha(this); validarLicencia(this)">
                                <div id="msg_error_f_revalidacion" style="display:none; color:#dc2626; font-size:11px; font-weight:800; margin-top:-10px; margin-bottom:10px;"><i class="fa-solid fa-triangle-exclamation"></i> LICENCIA VENCIDA</div>
                            </div>
                        </div>

                        <div style="margin-top:15px;">
                            <label class="label-mini">Restricciones</label>
                            <input type="text" name="lic_restricciones" id="lic_restricciones" class="input-comp" style="margin-bottom:0;" required>
                        </div>
                        
                        <div class="grid-datos" style="margin-top:15px;">
                            <div><label class="label-mini">Grupo Sanguíneo</label><input type="text" name="lic_gs" id="lic_gs" class="input-comp" style="margin-bottom:0;" required></div>
                            <div><label class="label-mini">Categoría Mina</label><input type="text" name="lic_cat_mina" id="lic_cat_mina" class="input-comp" style="margin-bottom:0;" required></div>
                        </div>
                    </div>

                    <div id="visita-fields" class="hidden" style="background:#fff7ed; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #fed7aa;">
                        <label style="color:#ea580c;">ANFITRIÓN</label>
                        <input type="text" name="anfitrion" placeholder="Ing. Residente..." style="border-color:#fdba74;">
                        <label style="color:#ea580c;">MOTIVO</label>
                        <input type="text" name="motivo" placeholder="Reunión..." style="border-color:#fdba74;">
                    </div>

                    <!-- NUEVA LÓGICA DE ORIGEN Y DESTINO -->
                    <div style="margin-bottom:20px; background:#fef2f2; padding:15px; border-radius:10px; border:1px solid #fca5a5;">
                        <div class="grid-datos">
                            <div>
                                <label style="color:#991b1b;">ORIGEN</label>
                                <input type="text" name="origen_ui" id="inputOrigen" placeholder="Lugar de partida" required style="border-color:#fca5a5;">
                            </div>
                            <div>
                                <label style="color:#991b1b;">DESTINO</label>
                                <input type="text" name="destino_ui" id="inputDestino" placeholder="Lugar de llegada" required style="border-color:#fca5a5;">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:25px;">
                        <span style="font-size:11px; font-weight:800; color:var(--h-gold); text-transform:uppercase;">ACOMPAÑANTES (OPCIONAL)</span>
                        <div style="height:2px; background:#e2e8f0; margin:8px 0 15px;"></div>
                        
                        <?php for($i=1; $i<=4; $i++): ?>
                        <div class="companion-row">
                            <input type="text" name="ac<?php echo $i; ?>" id="ac<?php echo $i; ?>" placeholder="NOMBRE | CARGO | EMPRESA" style="margin-bottom:0; flex:1;">
                            <button type="button" class="btn-mini btn-buscar-mini" onclick="buscarAcompanante('ac<?php echo $i; ?>')"><i class="fa-solid fa-magnifying-glass"></i></button>
                            <button type="button" class="btn-mini btn-scan" onclick="openScanner('ac<?php echo $i; ?>')"><i class="fa-solid fa-barcode"></i></button>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div style="margin-top:20px;">
                        <label>OBSERVACIONES ADICIONALES</label>
                        <textarea name="obs" rows="3" placeholder="Comentarios..."></textarea>
                    </div>

                    <button type="submit" name="btn_registrar_conductor" class="btn-register" style="margin-top: 25px; padding:20px; font-size:18px; background:var(--h-dark); box-shadow:0 4px 0 #000;">
                        <i class="fa-solid fa-shield-check"></i> FINALIZAR REGISTRO GENERAL
                    </button>
                </div>
            </form>

            <button onclick="location.href='control_garita.php'" style="background:transparent; color:#ef4444; width:100%; border:none; padding:15px; margin-top:10px; font-weight:700; cursor:pointer;">
                <i class="fa-solid fa-xmark"></i> CANCELAR PROCESO
            </button>
        </div>
    </div>
    
    <?php endif; ?>

    <!-- HISTORIAL (Derecha) -->
    <div class="history-container">
        <h3 style="font-size:12px; color:#64748b; margin-bottom:15px; text-transform:uppercase; letter-spacing:1px; font-weight:800;">Línea de Tiempo (Últimos 10)</h3>
        <?php 
        $sql_hist = "SELECT * FROM registros_garita ORDER BY fecha_ingreso DESC LIMIT 10";
        $res_hist = mysqli_query($conn, $sql_hist);
        
        while($row = mysqli_fetch_assoc($res_hist)): 
            $v_placa = $row['placa_unidad'];
            $v_mov = $row['tipo_movimiento'];
            $dni_c = $row['dni_conductor'];
            
            // Obtenemos detalles extra del conductor (Prepared Statement)
            $stmt_det = $conn->prepare("SELECT f.area, f.cargo, d.nro_licencia, d.categoria_mtc, d.f_expedicion, d.f_revalidacion, d.restricciones, d.grupo_sanguineo, d.categoria_mina 
                        FROM fuerza_laboral f
                        LEFT JOIN detalles_conductor d ON f.dni = d.dni
                        WHERE f.dni = ? LIMIT 1");
            $stmt_det->bind_param("s", $dni_c);
            $stmt_det->execute();
            $row_d = $stmt_det->get_result()->fetch_assoc();

            $conductor = $row['nombre_conductor'];
            $auth_final = $row['autorizado_por'];

            $badge = ($v_mov == 'INGRESO') 
                ? '<span style="color:#166534; font-weight:800; background:#dcfce7; padding:4px 8px; border-radius:6px; font-size:10px;">IN</span>' 
                : (($v_mov == 'RECHAZADO') 
                    ? '<span style="color:#991b1b; font-weight:800; background:#fee2e2; padding:4px 8px; border-radius:6px; font-size:10px;">X</span>'
                    : '<span style="color:#991b1b; font-weight:800; background:#fee2e2; padding:4px 8px; border-radius:6px; font-size:10px;">OUT</span>');

            $modalData = [
                'mov' => $v_mov,
                'placa' => $v_placa,
                'empresa' => $row['empresa'],
                'auth' => $auth_final,
                'fecha' => date('H:i d/m', strtotime($row['fecha_ingreso'])),
                'conductor' => $conductor,
                'operador' => $row['operador_garita'], 
                'area' => !empty($row_d['area']) ? $row_d['area'] : '-',
                'cargo' => !empty($row_d['cargo']) ? $row_d['cargo'] : '-',
                'lic_nro' => !empty($row_d['nro_licencia']) ? $row_d['nro_licencia'] : '-',
                'lic_cat' => !empty($row_d['categoria_mtc']) ? $row_d['categoria_mtc'] : '-',
                'lic_rev' => !empty($row_d['f_revalidacion']) ? $row_d['f_revalidacion'] : '-',
                'lic_res' => !empty($row_d['restricciones']) ? $row_d['restricciones'] : '-',
                'lic_gs' => !empty($row_d['grupo_sanguineo']) ? $row_d['grupo_sanguineo'] : '-',
                'lic_cat_mina' => !empty($row_d['categoria_mina']) ? $row_d['categoria_mina'] : '-',
                'ac1' => !empty($row['acompanante_1']) ? $row['acompanante_1'] : '-',
                'ac2' => !empty($row['acompanante_2']) ? $row['acompanante_2'] : '-',
                'ac3' => !empty($row['acompanante_3']) ? $row['acompanante_3'] : '-',
                'ac4' => !empty($row['acompanante_4']) ? $row['acompanante_4'] : '-',
                'obs' => !empty($row['observaciones']) ? $row['observaciones'] : '-',
                'destino_final' => !empty($row['destino']) ? $row['destino'] : '-',
                'anfitrion' => !empty($row['anfitrion']) ? $row['anfitrion'] : '-',
                'motivo' => !empty($row['motivo']) ? $row['motivo'] : '-'
            ];
            $json = htmlspecialchars(json_encode($modalData), ENT_QUOTES, 'UTF-8');
        ?>
            <div class="history-item <?php echo ($v_mov=='SALIDA' || $v_mov=='RECHAZADO')?'item-out':''; ?>" onclick="showHistoryDetails('<?php echo $json; ?>')">
                <div>
                    <div class="h-placa"><?php echo $v_placa; ?> <span style="font-size:10px; color:#64748b; font-weight:400;">(<?php echo explode(' ', $conductor)[0]; ?>)</span></div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px;"><?php echo $row['empresa']; ?></div>
                </div>
                <div style="text-align:right;">
                    <?php echo $badge; ?>
                    <div style="font-size:10px; font-family:'Orbitron'; color:#333; margin-top:4px;"><?php echo date('H:i', strtotime($row['fecha_ingreso'])); ?></div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<audio id="beep" src="https://www.soundjay.com/buttons/beep-01a.mp3"></audio>

<?php if ($mensaje): ?>
<script>
    Swal.fire({
        title: "<?php echo ($tipo_mensaje == 'success' ? '¡Operación Exitosa!' : '¡Atención!'); ?>",
        text: "<?php echo $mensaje; ?>",
        icon: "<?php echo ($tipo_mensaje == 'success' ? 'success' : 'error'); ?>",
        confirmButtonColor: "#c5a059",
        confirmButtonText: "Entendido",
        timer: <?php echo ($tipo_mensaje == 'success' ? '3000' : 'null'); ?>, 
        timerProgressBar: <?php echo ($tipo_mensaje == 'success' ? 'true' : 'false'); ?>
    });
</script>
<?php endif; ?>

<script>
    // --- FUNCION MAGICA PARA AUTO-COMPLETAR FECHAS CON BARRITAS ---
    function formatoFecha(input) {
        let v = input.value.replace(/\D/g, ''); // Deja solo números
        if (v.length > 8) v = v.substring(0, 8); // Máximo 8 dígitos
        
        if (v.length > 4) {
            v = v.replace(/^(\d{2})(\d{2})(\d+)/, '$1/$2/$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d+)/, '$1/$2');
        }
        input.value = v;
    }

    // --- FUNCION AUXILIAR PARA FORMATEAR LA FECHA QUE VIENE DE LA BASE DE DATOS ---
    function formatearFechaInversa(fechaStr) {
        if(!fechaStr) return "";
        if(fechaStr === '2099-01-01' || fechaStr.includes('VIGENTE')) return "VIGENTE";
        if(fechaStr.includes('-')) {
            let p = fechaStr.split('-');
            if(p[0].length === 4) return p[2] + '/' + p[1] + '/' + p[0];
        } else if (fechaStr.includes('/')) {
            return fechaStr;
        }
        return fechaStr;
    }

    // --- NUEVAS FUNCIONES DE VALIDACIÓN ---

    // 1. Validar Año (Si es < 2023, alerta visual y texto)
    function validarAnio(input) {
        let anio = parseInt(input.value);
        let errorDivId = "msg_error_" + input.id; // Busca el div con ID "msg_error_" + id_del_input
        let errorDiv = document.getElementById(errorDivId);

        if(!isNaN(anio) && input.value.length === 4) {
            if(anio < 2023) {
                $(input).addClass('input-alert');
                if(errorDiv) errorDiv.style.display = 'block';
            } else {
                $(input).removeClass('input-alert');
                if(errorDiv) errorDiv.style.display = 'none';
            }
        } else {
            $(input).removeClass('input-alert');
            if(errorDiv) errorDiv.style.display = 'none';
        }
    }

    // 2. Validar Fecha SOAT (Texto) con Mensaje y Estilo Rojo
    function validarSoat(input) {
        let val = input.value;
        let errorDivId = "msg_error_" + input.id;
        let errorDiv = document.getElementById(errorDivId);

        if (val.toUpperCase().includes("VIGENTE")) {
             $(input).removeClass('input-alert');
             $(input).css('background', '#f0fdf4'); 
             $(input).css('border-color', '#16a34a');
             $(input).css('color', '#166534');
             if(errorDiv) errorDiv.style.display = 'none';
             return;
        }

        if(val.length === 10 && val.includes('/')) {
            let partes = val.split('/');
            let fechaSoat = new Date(partes[2], partes[1] - 1, partes[0]);
            let hoy = new Date();
            hoy.setHours(0,0,0,0);
            
            if(fechaSoat < hoy) {
                $(input).css('background', ''); 
                $(input).css('border-color', '');
                $(input).css('color', '');
                
                $(input).addClass('input-alert');
                
                if(errorDiv) errorDiv.style.display = 'block';
            } else {
                $(input).removeClass('input-alert');
                $(input).css('background', '#f0fdf4'); 
                $(input).css('border-color', '#16a34a');
                $(input).css('color', '#166534');
                if(errorDiv) errorDiv.style.display = 'none';
            }
        } else {
            $(input).removeClass('input-alert');
            if(errorDiv) errorDiv.style.display = 'none';
        }
    }

    // 3. Validar Licencia (Input Texto con DD/MM/YYYY) con Mensaje y Estilo Rojo
    function validarLicencia(input) {
        let val = input.value;
        let fechaLic = null;
        let hoy = new Date();
        hoy.setHours(0,0,0,0);
        
        let errorDivId = "msg_error_" + input.id;
        let errorDiv = document.getElementById(errorDivId);

        if(val.length === 10 && val.includes('/')) {
            let partes = val.split('/');
            fechaLic = new Date(partes[2], partes[1] - 1, partes[0]);
        } else if (val.length === 10 && val.includes('-')) {
            let partes = val.split('-');
            if(partes[0].length === 4) fechaLic = new Date(partes[0], partes[1] - 1, partes[2]);
        }

        if(fechaLic && !isNaN(fechaLic) && fechaLic < hoy) {
            $(input).addClass('input-alert');
            if(errorDiv) errorDiv.style.display = 'block';
        } else {
            $(input).removeClass('input-alert');
            if(errorDiv) errorDiv.style.display = 'none';
        }
    }

    $(document).ready(function() {
        <?php if ($fase_actual === 'tripulacion'): ?>
            let mov = "<?php echo $auto_mov; ?>";
            let iptOrigen = $("#inputOrigen");
            let iptDestino = $("#inputDestino");

            if(mov === "INGRESO") {
                iptOrigen.prop('readonly', false).val('').attr('placeholder', 'Especifique origen...');
                iptDestino.prop('readonly', true).val('UM INMACULADA').css('background', '#e2e8f0');
            } else {
                iptOrigen.prop('readonly', true).val('UM INMACULADA').css('background', '#e2e8f0');
                iptDestino.prop('readonly', false).val('').attr('placeholder', 'Especifique destino...');
            }

            if($("#f_revalidacion").val()) validarLicencia(document.getElementById("f_revalidacion"));
        <?php else: ?>
            if($("#anio_final").val()) validarAnio(document.getElementById("anio_final"));
            if($("#soat_final").val()) validarSoat(document.getElementById("soat_final"));
        <?php endif; ?>
    });


    function setMainType(type) {
        document.getElementById('main_type').value = type;
        if(type==='PERMANENTE') {
            document.getElementById('tab-main-p').classList.add('active-perm'); document.getElementById('tab-main-v').classList.remove('active-visita');
            document.getElementById('visita-fields').classList.add('hidden');
        } else {
            document.getElementById('tab-main-v').classList.add('active-visita'); document.getElementById('tab-main-p').classList.remove('active-perm');
            document.getElementById('visita-fields').classList.remove('hidden');
        }
    }

    function buscarConductor() {
        let dni = document.getElementById("inputDniConductor").value.trim();
        if(dni.length !== 8) { Swal.fire('Error', 'Ingrese un DNI válido de 8 dígitos', 'error'); return; }

        $.ajax({
            url: 'buscar_persona.php', type: 'GET', data: { dni: dni }, dataType: 'json',
            success: function(data) {
                document.getElementById("boxConductor").classList.remove("hidden");
                document.getElementById("final_dni_c").value = dni;
                let iptNom = document.getElementById("final_nom_c");
                let iptEmp = document.getElementById("final_emp_c");
                let formNuevo = document.getElementById("formNuevoConductor");
                let visFields = document.getElementById("visita-fields");

                if(data.success) { 
                    document.getElementById("es_nuevo_c").value = "0";
                    formNuevo.classList.add("hidden");
                    iptNom.value = data.nombre || "SIN NOMBRE"; 
                    
                    // EMPRESA SIEMPRE EDITABLE
                    iptEmp.value = (data.empresa && data.empresa !== "POR DEFINIR") ? data.empresa : "";
                    iptNom.readOnly = true; 
                    iptEmp.readOnly = false;
                    iptNom.style.background = "#e2e8f0"; 
                    iptEmp.style.background = "#fff";

                    // ÁREA Y CARGO SIEMPRE VISIBLES Y EDITABLES
                    document.getElementById("boxExtraDatos").style.display = "grid";
                    document.getElementById("final_area_c").value = (data.area && data.area !== '-') ? data.area : '';
                    document.getElementById("final_cargo_c").value = (data.cargo && data.cargo !== 'VISITA') ? data.cargo : '';

                    if(data.tipo_personal === 'VISITA') { visFields.classList.remove('hidden'); } 
                    else { visFields.classList.add('hidden'); }

                    if(data.licencia) {
                        document.getElementById("lic_nro").value = data.licencia.nro_licencia;
                        document.getElementById("lic_cat_mtc").value = data.licencia.categoria_mtc;
                        
                        document.getElementById("f_expedicion").value = formatearFechaInversa(data.licencia.f_expedicion);
                        document.getElementById("f_revalidacion").value = formatearFechaInversa(data.licencia.f_revalidacion);
                        
                        validarLicencia(document.getElementById("f_revalidacion"));

                        document.getElementById("lic_restricciones").value = data.licencia.restricciones;
                        document.getElementById("lic_gs").value = data.licencia.grupo_sanguineo;
                        document.getElementById("lic_cat_mina").value = data.licencia.categoria_mina;
                    } else {
                        document.getElementById("lic_nro").value = ""; document.getElementById("lic_cat_mtc").value = "";
                        document.getElementById("f_expedicion").value = ""; document.getElementById("f_revalidacion").value = "";
                        document.getElementById("lic_restricciones").value = ""; document.getElementById("lic_gs").value = "";
                        document.getElementById("lic_cat_mina").value = "";
                    }

                } else {
                    document.getElementById("es_nuevo_c").value = "1";
                    formNuevo.classList.remove("hidden");
                    
                    // AÚN SI ES NUEVO, HACEMOS VISIBLES LOS DATOS LABORALES PARA QUE LOS LLENEN
                    document.getElementById("boxExtraDatos").style.display = "grid";
                    document.getElementById("final_area_c").value = "";
                    document.getElementById("final_cargo_c").value = "";

                    iptNom.value = ""; 
                    iptEmp.value = "<?php echo addslashes($auto_emp); ?>"; 
                    
                    iptNom.readOnly = false; 
                    iptEmp.readOnly = false;
                    iptNom.style.background = "#fff"; 
                    iptEmp.style.background = "#fff";
                    setMainType('PERMANENTE'); 
                    document.getElementById("lic_nro").value = ""; document.getElementById("lic_cat_mtc").value = "";
                    document.getElementById("f_expedicion").value = ""; document.getElementById("f_revalidacion").value = "";
                    document.getElementById("lic_restricciones").value = ""; document.getElementById("lic_gs").value = "";
                    document.getElementById("lic_cat_mina").value = "";
                }
            },
            error: function() { Swal.fire('Error', 'Error de conexión al buscar', 'error'); }
        });
    }

    function buscarAcompanante(inputId) {
        let inputField = document.getElementById(inputId);
        let dni = inputField.value.trim();
        if(dni.includes("|")) { return; } 

        if(dni.length >= 5) {
            inputField.style.opacity = '0.5';
            $.ajax({
                url: 'buscar_persona.php', type: 'GET', data: { dni: dni }, dataType: 'json',
                success: function(data) {
                    inputField.style.opacity = '1';
                    if (data.success) {
                        let cargo = data.cargo || 'VISITA';
                        let empresa = data.empresa || '-';
                        inputField.value = data.nombre + " | " + cargo + " | " + empresa;
                        inputField.style.borderColor = "#16a34a"; inputField.style.backgroundColor = "#f0fdf4";
                    } else {
                        Swal.fire({
                            title: 'DNI no encontrado',
                            text: "¿Desea registrar un nuevo personal?",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#16a34a',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Sí, registrar',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                openNewAcModal(dni, inputId);
                            } else {
                                inputField.style.borderColor = "#eab308";
                            }
                        });
                    }
                },
                error: function() { inputField.style.opacity = '1'; }
            });
        }
    }

    function openNewAcModal(dni, inputId) {
        document.getElementById("new_ac_target").value = inputId;
        document.getElementById("new_ac_dni").value = dni;
        document.getElementById("new_ac_nombre").value = '';
        document.getElementById("new_ac_empresa").value = '<?php echo addslashes($auto_emp); ?>';
        document.getElementById("new_ac_cargo").value = ''; 
        setAcType('PERMANENTE');
        document.getElementById("modal-new-ac").style.display = "flex";
    }
    function closeNewAc() { document.getElementById("modal-new-ac").style.display = "none"; }
    function setAcType(type) {
        document.getElementById("new_ac_type").value = type;
        if(type === 'PERMANENTE'){
            document.getElementById("tab-ac-p").classList.add("active-perm"); document.getElementById("tab-ac-v").classList.remove("active-visita");
        } else {
            document.getElementById("tab-ac-v").classList.add("active-visita"); document.getElementById("tab-ac-p").classList.remove("active-perm");
        }
    }
    function saveNewCompanion() {
        const data = {
            ajax_create_companion: true,
            dni: document.getElementById("new_ac_dni").value,
            nombre: document.getElementById("new_ac_nombre").value,
            empresa: document.getElementById("new_ac_empresa").value,
            tipo: document.getElementById("new_ac_type").value,
            cargo: document.getElementById("new_ac_cargo").value
        };
        if(!data.nombre || !data.empresa) { Swal.fire('Atención', 'Complete todos los datos', 'warning'); return; }
        
        $.post("", data, function(res) {
            if(res.success) {
                const target = document.getElementById("new_ac_target").value;
                const field = document.getElementById(target);
                field.value = res.nombre_completo + " | " + res.cargo + " | " + res.empresa;
                field.style.borderColor = "#16a34a"; field.style.background = "#f0fdf4";
                closeNewAc();
                const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true});
                Toast.fire({icon: 'success', title: 'Personal Agregado'});
            } else { Swal.fire('Error', 'Error BD: ' + res.msg, 'error'); }
        }, "json");
    }

    let html5QrcodeScanner; let targetInputId = null;
    function openScanner(targetId) {
        targetInputId = targetId;
        document.getElementById('camera-modal').style.display = 'flex';
        html5QrcodeScanner = new Html5Qrcode("reader");
        html5QrcodeScanner.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } }, onScanSuccess)
        .catch(err => { Swal.fire('Error', 'No se pudo iniciar la cámara: ' + err, 'error'); closeCamera(); });
    }
    function closeCamera() {
        if (html5QrcodeScanner) { html5QrcodeScanner.stop().then(() => { document.getElementById('camera-modal').style.display = 'none'; document.getElementById('reader').innerHTML = ""; }); } 
        else { document.getElementById('camera-modal').style.display = 'none'; }
    }
    function onScanSuccess(decodedText) {
        let limpio = decodedText.trim();
        if (!/^\d{8}$/.test(limpio)) { return; }
        document.getElementById('beep').play(); closeCamera();
        if (targetInputId === 'conductor') {
            document.getElementById('inputDniConductor').value = limpio; buscarConductor();
        } else {
            document.getElementById(targetInputId).value = limpio; buscarAcompanante(targetInputId);
        }
    }

    function setNewMovimiento(tipo) {
        document.getElementById('new_movimiento').value = tipo;
        if (tipo === 'INGRESO') {
            document.getElementById('opt-new-in').classList.add('active-in'); document.getElementById('opt-new-out').classList.remove('active-out');
            btn.innerHTML = '<i class="fa-solid fa-arrow-right"></i> VEHÍCULO OK, PASAR A PERSONAL'; btn.classList.remove('btn-out');
        } else {
            document.getElementById('opt-in').classList.remove('active-in'); document.getElementById('opt-out').classList.add('active-out');
            btn.innerHTML = '<i class="fa-solid fa-arrow-right"></i> VEHÍCULO OK, PASAR A PERSONAL'; btn.classList.add('btn-out');
        }
    }

    function toggleJefe() {
        var estado = document.getElementById("selectEstado").value;
        var divJefe = document.getElementById("divJefe");
        if(estado === "AUTORIZADO") { 
            divJefe.classList.remove('hidden'); 
        } else { 
            divJefe.classList.add('hidden'); 
            document.querySelectorAll('.jefe-option').forEach(el => el.classList.remove('selected'));
            document.getElementById("inputJefeHidden").value = "";
        }
    }

    function selectJefe(element, nombreJefe) {
        document.querySelectorAll('.jefe-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('inputJefeHidden').value = nombreJefe;
    }

    function validarYBuscar() {
        var placa = document.getElementById("inputPlaca").value.trim().toUpperCase();
        var estado = document.getElementById("selectEstado").value;
        var jefe = document.getElementById("inputJefeHidden").value; 
        var autoriza = document.getElementById("selectAutoriza").value;

        if(placa == "") { Swal.fire('Atención', 'Ingrese la Placa de la Unidad', 'warning'); return; }
        if(estado == "") { Swal.fire('Atención', 'Seleccione un Estado', 'warning'); return; }
        if(estado == "AUTORIZADO" && (jefe == "" || autoriza == "")) { Swal.fire('Atención', 'Seleccione quién autoriza y el Jefe de Turno', 'warning'); return; }

        let skeletonHTML = `
        <div class="skeleton-loader">
            <div class="skeleton skeleton-title"></div>
            <div class="skeleton skeleton-text"></div>
            <div class="skeleton skeleton-text short"></div>
            <div class="skeleton skeleton-box" style="margin-top:10px;"></div>
        </div>
        <div style="text-align:center; margin-top:10px; color:var(--text-muted); font-size:12px; font-weight:700;"><i class="fa-solid fa-circle-notch fa-spin"></i> CONSULTANDO FACTILIZA...</div>
        `;
        $("#estadoEspera").html(skeletonHTML);
        $("#fichaVehiculo").hide(); $("#formNuevoVehiculo").hide();

        $.ajax({
            url: 'buscar_datos.php', type: 'POST', data: { tipo: 'vehiculo', placa: placa }, dataType: 'json',
            success: function(data) {
                if(data.encontrado) { mostrarResultado(data, estado, jefe); } 
                else {
                    $("#cardValidacion").slideUp(400); 
                    $("#cardResultado").slideDown(800, function() {
                        $('html, body').animate({ scrollTop: $("#cardResultado").offset().top - 100 }, 600);
                    });

                    $("#estadoEspera").hide(); $("#fichaVehiculo").hide();
                    $("#formNuevoVehiculo").fadeIn();
                    $("#new_placa").val(placa); $("#passed_estado").val(estado); $("#passed_jefe").val(jefe);
                    
                    var card = $("#cardResultado"); card.removeClass("card-green card-red").addClass("card-blue");
                    $("#headerResultado h2").html('<i class="fa-solid fa-plus-circle"></i> REGISTRAR NUEVO');

                    if(estado === "AUTORIZADO") {
                        $("#divNewMovimiento").show(); $("#msgNewNoAuth").hide();
                        $("#btnSaveNew").html('GUARDAR Y PASAR A PERSONAL'); $("#btnSaveNew").css("background", "#2563eb");
                    } else {
                        $("#divNewMovimiento").hide(); $("#msgNewNoAuth").show();
                        $("#btnSaveNew").html('SOLO GUARDAR FICHA (DENEGADO)'); $("#btnSaveNew").css("background", "#dc2626"); 
                    }
                }
            },
            error: function() { $("#estadoEspera").text("Error de conexión"); Swal.fire('Error', 'No se pudo conectar con la base de datos', 'error'); }
        });
    }

    function mostrarResultado(data, estado, jefe) {
        $("#cardValidacion").slideUp(400);

        $("#cardResultado").slideDown(800, function() {
            $('html, body').animate({ scrollTop: $("#cardResultado").offset().top - 100 }, 600);
        });

        $("#estadoEspera").hide(); $("#fichaVehiculo").fadeIn();
        
        $("#resPlaca").val(data.placa_unidad); 
        $("#tipo_final").val(data.tipo_vehiculo);
        $("#marca_final").val(data.marca);
        $("#modelo_final").val(data.modelo);
        $("#color_final").val(data.color);
        $("#anio_final").val(data.anio || ''); 
        
        // Ejecutar validacion al cargar datos SI EXISTE
        if(data.anio) validarAnio(document.getElementById('anio_final'));

        $("#remolque_final").val(data.placa_remolque || '-');
        $("#empresa_final").val(data.empresa_transporte);
        $("#soat_final").val(data.soat_vcto || '');
        if(data.soat_vcto) validarSoat(document.getElementById('soat_final'));

        $("#placa_final").val(data.placa_unidad); 
        $("#placa_rechazo").val(data.placa_unidad); 
        $("#jefe_autoriza_final").val(jefe);
        $("#estado_validacion_final").val(estado);

        var card = $("#cardResultado"); 
        var areaReg = $("#areaRegistro"); 
        var aviso = $("#avisoBloqueo");
        var obsRechazo = $("#divObsRechazo");
        
        card.removeClass("card-blue");

        if(estado === "AUTORIZADO") {
            card.removeClass("card-red").addClass("card-green"); 
            $("#headerResultado h2").html('<i class="fa-solid fa-check-circle"></i> VEHÍCULO AUTORIZADO (EDITABLE)');
            aviso.hide(); areaReg.show(); obsRechazo.hide();
            $("#btnSubmit").html('<i class="fa-solid fa-arrow-right"></i> VEHÍCULO OK, PASAR A PERSONAL');
            $("#btnSubmit").removeClass("btn-out");
        } else {
            card.removeClass("card-green").addClass("card-red"); 
            $("#headerResultado h2").html('<i class="fa-solid fa-ban"></i> NO AUTORIZADO');
            aviso.show(); areaReg.hide(); obsRechazo.show();
            $("#btnSubmit").html('<i class="fa-solid fa-save"></i> GUARDAR RECHAZO');
            $("#btnSubmit").addClass("btn-out");
        }
    }

    function setMovimiento(tipo) {
        document.getElementById('tipo_movimiento').value = tipo;
        var btn = document.getElementById('btnSubmit');
        if (tipo === 'INGRESO') {
            document.getElementById('opt-in').classList.add('active-in'); document.getElementById('opt-out').classList.remove('active-out');
            btn.innerHTML = '<i class="fa-solid fa-arrow-right"></i> VEHÍCULO OK, PASAR A PERSONAL'; btn.classList.remove('btn-out');
        } else {
            document.getElementById('opt-in').classList.remove('active-in'); document.getElementById('opt-out').classList.add('active-out');
            btn.innerHTML = '<i class="fa-solid fa-arrow-right"></i> VEHÍCULO OK, PASAR A PERSONAL'; btn.classList.add('btn-out');
        }
    }

    function showHistoryDetails(jsonStr) {
        const d = JSON.parse(jsonStr);
        const fAc = (ac) => {
            if (!ac || ac === 'NINGUNO') return '';
            const parts = ac.split('|');
            if (parts.length >= 3) {
                return `<li style="margin-bottom:5px;">
                            <strong>${parts[0].trim()}</strong><br>
                            <span style="font-size:11px; color:#666;">${parts[1].trim()} - ${parts[2].trim()}</span>
                        </li>`;
            }
            return `<li>${ac}</li>`;
        };

        let acList = fAc(d.ac1) + fAc(d.ac2) + fAc(d.ac3) + fAc(d.ac4);
        let destInfo = d.mov === 'SALIDA' ? `<div class="details-row"><span class="details-label" style="color:var(--h-gold);">Destino</span> ${d.destino_final}</div>` : '';
        let visitaInfo = (d.anfitrion && d.anfitrion !== '-') ? `<div class="details-row"><span class="details-label">Anfitrión / Motivo</span> ${d.anfitrion} - ${d.motivo}</div>` : '';

        let conductorDetails = '';
        if(d.conductor !== 'PENDIENTE (NO REGISTRADO)') {
            conductorDetails = `
                <div style="font-size:11px; color:#64748b; margin-top:5px; padding-left:10px; border-left:2px solid #e2e8f0; line-height: 1.6;">
                    <b>Área:</b> ${d.area} &nbsp;|&nbsp; <b>Cargo:</b> ${d.cargo}<br>
                    <b>Lic. MTC:</b> ${d.lic_nro} (${d.lic_cat}) &nbsp;|&nbsp; <b>G. Sanguíneo:</b> ${d.lic_gs}<br>
                    <b>Vcto. Lic:</b> ${d.lic_rev} &nbsp;|&nbsp; <b>Cat. Mina:</b> ${d.lic_cat_mina}<br>
                    <b>Restricciones:</b> ${d.lic_res}
                </div>
            `;
        }

        let html = `
            <div class="details-row"><span class="details-label">Movimiento</span> <strong style="${d.mov === 'SALIDA' ? 'color:#991b1b' : (d.mov === 'DENEGADO' ? 'color:#b91c1c' : 'color:#166534')}">${d.mov}</strong></div>
            <div class="details-row"><span class="details-label">Vehículo</span> <span style="font-family:'Orbitron'; font-size:14px; font-weight:bold;">${d.placa}</span></div>
            <div class="details-row"><span class="details-label">Empresa Transp.</span> ${d.empresa}</div>
            <div class="details-row"><span class="details-label">Autorizado Por</span> ${d.auth || '-'}</div>
            ${destInfo}
            
            <div style="margin-top:15px; border-top:2px solid #e2e8f0; padding-top:10px;"></div>
            <div class="details-row">
                <span class="details-label" style="color:#0ea5e9;">Conductor</span> 
                <strong style="color:var(--h-dark);">${d.conductor}</strong>
                ${conductorDetails}
            </div>
            ${visitaInfo}
            <div class="details-row"><span class="details-label" style="color:#0ea5e9;">Personal Acompañante</span>
                <ul style="margin:5px 0 0 15px; padding:0; list-style:none; font-size:13px; color:#475569;">
                    ${acList || '<i>Ninguno</i>'}
                </ul>
            </div>
            <div class="details-row"><span class="details-label">Observaciones</span> ${d.obs}</div>

            <div style="margin-top:15px; border-top:2px solid #e2e8f0; padding-top:10px;"></div>
            <div class="details-row"><span class="details-label">Fecha/Hora</span> ${d.fecha}</div>
            <div class="details-row"><span class="details-label">Operador</span> ${d.operador}</div>
        `;
        document.getElementById('modal-body').innerHTML = html;
        document.getElementById('details-modal').style.display = 'flex';
    }

    function closeDetails() { document.getElementById('details-modal').style.display = 'none'; }
    
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