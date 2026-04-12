<?php
// ==============================================================================
// CONTROL PEATONAL - FINAL STABLE (SIN ERROR 500 + FLECHA NEGRA)
// ==============================================================================
ob_start();
session_start();

// Configuración de errores (Esto evita la pantalla blanca y muestra el error real si ocurre)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    $busqueda = mysqli_real_escape_string($conn, $_POST['dni_buscar']);
    $sql = "SELECT * FROM fuerza_laboral WHERE dni = '$busqueda' LIMIT 1";
    $res = mysqli_query($conn, $sql);

    if ($res && mysqli_num_rows($res) > 0) {
        $persona = mysqli_fetch_assoc($res);
    } else {
        $nuevo_dni = $busqueda;
    }
}

// ---------------------------------------------------------
// 3. REGISTRO (CORREGIDO: ELIMINADO num_acompanantes)
// ---------------------------------------------------------
if (isset($_POST['btn_registrar'])) {
    $dni = $_POST['dni_final'];
    $nombre = strtoupper($_POST['nombre_final']);
    $empresa = strtoupper($_POST['empresa_final']);

    // Si es nuevo, insertamos en fuerza_laboral
    if (isset($_POST['es_nuevo']) && $_POST['es_nuevo'] == '1') {
        $tipo_p = $_POST['tipo_personal_new'];
        $check = mysqli_query($conn, "SELECT dni FROM fuerza_laboral WHERE dni = '$dni'");
        if (mysqli_num_rows($check) == 0) {
            $sql_new = "INSERT INTO fuerza_laboral (dni, nombres, apellidos, empresa, tipo_personal, area, cargo, estado_validacion)
                        VALUES ('$dni', '$nombre', '-', '$empresa', '$tipo_p', '-', 'PEATON', 'ACTIVO')";
            if(!mysqli_query($conn, $sql_new)) {
                die("Error al crear personal: " . mysqli_error($conn));
            }
        }
    }

    $mov = $_POST['tipo_movimiento'];

    if ($mov === 'SALIDA') {
        $destino = strtoupper($_POST['destino_salida']);
        $autoriza = $_POST['autoriza_salida'];
    } else {
        $destino = 'INTERIOR MINA';
        $autoriza = 'VERIFICADO EN GARITA';
    }

    $anfitrion = isset($_POST['anfitrion']) ? strtoupper($_POST['anfitrion']) : '-';
    $motivo    = isset($_POST['motivo']) ? strtoupper($_POST['motivo']) : '-';
    $op = $_SESSION['usuario'];

    // SQL INSERT CORREGIDO (Quitamos num_acompanantes que causaba el error 500)
    $sql_reg = "INSERT INTO registros_garita (
                    dni_conductor, nombre_conductor, empresa, tipo_movimiento,
                    destino, autorizado_por, anfitrion, motivo, operador_garita,
                    fecha_ingreso, acompanante_1, acompanante_2, acompanante_3, acompanante_4
                ) VALUES (
                    '$dni', '$nombre', '$empresa', '$mov',
                    '$destino', '$autoriza', '$anfitrion', '$motivo', '$op',
                    NOW(), 'NINGUNO', 'NINGUNO', 'NINGUNO', 'NINGUNO'
                )";

    if (mysqli_query($conn, $sql_reg)) {
        header("Location: control_personal.php?status=ok");
        exit();
    } else {
        // Si falla, mostramos el error en pantalla
        die("Error FATAL SQL: " . mysqli_error($conn));
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#F4F4F4">
    <title>Control Personal | SITRAN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
            --rb: #FEF2F2;
            --rbo: rgba(252,165,165,.4);
            --font: 'Inter', system-ui, sans-serif;
            --mono: 'JetBrains Mono', monospace;
            --h-gold: #C49A2C;
            --h-dark: #0A0A0A;
            --visita: #EA580C;
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
            background: var(--bg);
            color: var(--ink);
            min-height: 100svh;
            padding-bottom: 60px;
            -webkit-font-smoothing: antialiased;
            -webkit-tap-highlight-color: transparent;
        }
        a { text-decoration: none; color: inherit; }
        ::selection { background: var(--gl); color: var(--gd); }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

        /* ═══ TOPBAR ═══ */
        .header-main {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255,255,255,.97);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: 0 16px;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,.07);
            box-shadow: 0 1px 0 rgba(255,255,255,1), 0 2px 16px rgba(0,0,0,.05);
        }
        .header-main::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--ink) 0%, var(--g) 20%, #E8C85A 50%, var(--g) 80%, transparent 100%);
            opacity: .7;
        }
        .btn-back-top {
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
        }
        .btn-back-top:hover { color: var(--g); border-color: var(--b-gold); background: var(--gl); transform: translateX(-2px); }
        .topbar-logos { display: flex; gap: 12px; align-items: center; }
        .logo-header { height: 32px; width: auto; object-fit: contain; }

        /* ═══ CONTAINER ═══ */
        .container { max-width: 480px; margin: 0 auto; padding: 18px 14px; display: flex; flex-direction: column; gap: 14px; }

        /* ═══ ALERT ═══ */
        .alert-box {
            padding: 14px 18px;
            border-radius: var(--r-lg);
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .success { background: var(--gb); color: var(--green); border: 1px solid var(--gbo); }
        .error { background: var(--rb); color: var(--red); border: 1px solid var(--rbo); }
        .info { background: var(--gl); color: var(--gd); border: 1px solid var(--b-gold); }

        /* ═══ SCANNER HERO CARD ═══ */
        .driver-search-box {
            background: var(--surf);
            border: 1px solid rgba(0,0,0,.07);
            border-radius: var(--r-2xl);
            padding: 40px 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(0,0,0,.06), 0 1px 0 #fff inset;
        }
        .driver-search-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--g-grad);
            border-radius: var(--r-2xl) var(--r-2xl) 0 0;
        }
        .big-scan-btn {
            background: var(--ink);
            color: var(--g);
            border: none;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            font-size: 30px;
            cursor: pointer;
            box-shadow: 0 0 0 8px rgba(196,154,44,.12), 0 8px 24px rgba(0,0,0,.2);
            margin-bottom: 20px;
            transition: all .25s;
            position: relative;
            z-index: 1;
        }
        .big-scan-btn:hover { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(196,154,44,.18), 0 12px 32px rgba(0,0,0,.25); }
        .big-scan-btn:active { transform: scale(.97); }

        .scan-title {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--ink);
            margin: 16px 0 24px;
        }

        .input-driver {
            background: var(--bg);
            border: 2px solid var(--b);
            color: var(--ink);
            padding: 16px;
            width: 100%;
            max-width: 280px;
            border-radius: var(--r-md);
            text-align: center;
            font-family: var(--mono);
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 3px;
            outline: none;
            transition: all .25s;
        }
        .input-driver:focus { border-color: var(--g); background: var(--surf); box-shadow: 0 0 0 4px var(--gr); }
        .input-driver::placeholder { color: var(--ink6); font-weight: 500; letter-spacing: 1px; }

        .scan-hint {
            font-size: 10px;
            color: var(--ink5);
            margin-top: 16px;
            font-weight: 500;
        }

        /* ═══ RESULT CARD ═══ */
        .result-card {
            background: var(--surf);
            border-radius: var(--r-2xl);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,.07);
            box-shadow: 0 2px 16px rgba(0,0,0,.06), 0 8px 32px rgba(0,0,0,.04);
            animation: slideUp 0.35s ease-out;
        }
        @keyframes slideUp { from { transform: translateY(16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .card-header {
            background: var(--ink);
            padding: 28px 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .card-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--g-grad);
        }
        .card-header h2 {
            margin: 0;
            color: var(--surf);
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .card-header p {
            margin: 6px 0 0;
            color: var(--g);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .5px;
        }
        .card-header .badge-tipo {
            display: inline-block;
            margin-top: 10px;
            font-size: 9px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 6px;
            letter-spacing: .5px;
        }
        .badge-permanente { background: rgba(255,255,255,.15); color: #fff; }
        .badge-visita { background: var(--visita); color: #fff; }

        .form-section { padding: 24px; }

        /* ═══ LABELS & INPUTS ═══ */
        .label-mini {
            font-size: 9px;
            font-weight: 700;
            color: var(--ink5);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: block;
            margin-bottom: 6px;
        }
        .input-comp {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--b);
            border-radius: var(--r-md);
            font-size: 14px;
            font-weight: 600;
            color: var(--ink2);
            background: var(--bg);
            font-family: var(--font);
            margin-bottom: 14px;
            transition: all .25s;
            box-sizing: border-box;
        }
        .input-comp:focus { outline: none; border-color: var(--g); background: var(--surf); box-shadow: 0 0 0 3px var(--gr); }

        /* ═══ ACTION BUTTONS (INGRESO/SALIDA + PERMANENTE/VISITA) ═══ */
        .action-buttons { display: flex; gap: 8px; margin-bottom: 18px; }
        .btn-sel {
            flex: 1;
            padding: 16px 12px;
            border-radius: var(--r-lg);
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            font-size: 12px;
            transition: all .25s cubic-bezier(.4,0,.2,1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid var(--b);
            color: var(--ink5);
            background: var(--surf2);
            font-family: var(--font);
            letter-spacing: .3px;
        }
        .btn-sel:hover { border-color: var(--b-gold); background: var(--gl); color: var(--gd); }
        .btn-sel:active { transform: scale(.97); }

        .active-gold {
            background: var(--ink) !important;
            color: var(--g) !important;
            border-color: var(--ink) !important;
            box-shadow: 0 4px 16px rgba(0,0,0,.2), inset 0 1px 0 rgba(255,255,255,.1);
            transform: translateY(-1px);
        }
        .active-black {
            background: var(--red) !important;
            color: var(--surf) !important;
            border-color: var(--red) !important;
            box-shadow: 0 4px 16px rgba(220,38,38,.25), inset 0 1px 0 rgba(255,255,255,.15);
            transform: translateY(-1px);
        }

        /* ═══ SUBMIT BUTTONS ═══ */
        .btn-confirm {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: var(--r-lg);
            font-weight: 800;
            font-size: 14px;
            margin-top: 10px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: var(--font);
            color: white;
            transition: all .25s cubic-bezier(.4,0,.2,1);
            position: relative;
            overflow: hidden;
        }
        .btn-confirm:hover { transform: translateY(-1px); }
        .btn-confirm:active { transform: scale(.98); }
        .bg-gold {
            background: var(--ink);
            box-shadow: 0 4px 20px rgba(0,0,0,.2), 0 0 0 0 var(--gr);
        }
        .bg-gold:hover { box-shadow: 0 6px 24px rgba(0,0,0,.25), 0 0 24px var(--gr); }
        .bg-black {
            background: var(--red);
            box-shadow: 0 4px 20px rgba(220,38,38,.2);
        }
        .bg-black:hover { box-shadow: 0 6px 24px rgba(220,38,38,.3); }

        /* ═══ SALIDA BOX ═══ */
        .strict-box {
            background: var(--rb);
            border: 1px solid var(--rbo);
            padding: 16px;
            border-radius: var(--r-lg);
            margin-bottom: 18px;
            display: none;
            position: relative;
            overflow: hidden;
        }
        .strict-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 3px;
            background: var(--red);
            border-radius: var(--r-lg) 0 0 var(--r-lg);
        }
        .strict-label {
            color: var(--red);
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
            display: block;
        }

        /* ═══ VISITA BOX ═══ */
        #visita-box {
            background: var(--gl);
            border: 1px solid var(--b-gold);
            padding: 16px;
            border-radius: var(--r-lg);
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
        }
        #visita-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 3px;
            background: var(--g-grad);
            border-radius: var(--r-lg) 0 0 var(--r-lg);
        }
        #visita-box .label-mini { color: var(--gd); }

        /* ═══ SECTION LABEL ═══ */
        .sec-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 2px;
        }
        .sec-txt { font-size: 9px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: var(--ink); white-space: nowrap; }
        .sec-line { flex: 1; height: 1px; background: linear-gradient(90deg, rgba(0,0,0,.12), transparent); }

        /* ═══ HISTORY ═══ */
        .history-box { display: flex; flex-direction: column; gap: 8px; }

        .history-item {
            background: var(--surf);
            padding: 14px 16px;
            border-radius: var(--r-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(0,0,0,.07);
            box-shadow: 0 1px 4px rgba(0,0,0,.03);
            cursor: pointer;
            transition: all .22s cubic-bezier(.4,0,.2,1);
            position: relative;
            overflow: hidden;
        }
        .history-item::before {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 4px;
            border-radius: var(--r-lg) 0 0 var(--r-lg);
        }
        .history-item:hover { transform: translateX(2px); box-shadow: 0 4px 16px rgba(0,0,0,.06); }

        .history-item.item-in::before { background: var(--green); }
        .history-item.item-in:hover { border-color: rgba(22,163,74,.2); background: linear-gradient(90deg, rgba(240,253,244,.5), var(--surf)); }

        .history-item.item-out::before { background: var(--red); }
        .history-item.item-out:hover { border-color: rgba(220,38,38,.15); background: linear-gradient(90deg, rgba(254,242,242,.5), var(--surf)); }

        .h-name { font-weight: 700; font-size: 13px; color: var(--ink); display: flex; align-items: center; gap: 8px; }
        .h-badge-in, .h-badge-out {
            font-size: 8px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: .5px;
        }
        .h-badge-in { background: var(--gb); color: var(--green); }
        .h-badge-out { background: var(--rb); color: var(--red); }

        .h-desc { font-size: 10px; color: var(--ink5); margin-top: 3px; padding-left: 2px; }
        .h-time { font-family: var(--mono); font-weight: 600; color: var(--ink); font-size: 12px; }
        .btn-details-icon {
            width: 28px; height: 28px;
            background: var(--bg);
            border: 1px solid var(--b);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            color: var(--ink5);
            transition: all .2s;
            margin-top: 6px;
        }
        .history-item:hover .btn-details-icon { background: var(--gl); color: var(--g); border-color: var(--b-gold); }

        /* ═══ MODALS ═══ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .modal-card {
            background: var(--surf);
            padding: 28px 24px;
            border-radius: var(--r-2xl);
            width: 90%;
            max-width: 400px;
            box-shadow: 0 24px 80px rgba(0,0,0,.2);
            animation: modalIn 0.3s ease-out;
            position: relative;
            overflow: hidden;
        }
        .modal-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--g-grad);
            border-radius: var(--r-2xl) var(--r-2xl) 0 0;
        }
        @keyframes modalIn { from { transform: translateY(16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-icon-wrap {
            width: 56px; height: 56px;
            background: var(--gl);
            border: 1px solid var(--b-gold);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            font-size: 22px;
            color: var(--gd);
        }
        .modal-title { margin: 0 0 20px; color: var(--ink); font-size: 16px; font-weight: 800; text-align: center; letter-spacing: -.2px; }

        .det-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 11px 0;
            font-size: 13px;
        }
        .det-row:last-child { border-bottom: none; }
        .det-label { font-weight: 700; color: var(--ink5); text-transform: uppercase; font-size: 9px; letter-spacing: .5px; }
        .det-val { font-weight: 700; color: var(--ink); text-align: right; }

        .btn-modal-close {
            width: 100%;
            padding: 14px;
            background: var(--ink);
            color: var(--surf);
            border: none;
            border-radius: var(--r-md);
            margin-top: 20px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            font-family: var(--font);
            letter-spacing: .5px;
            transition: all .2s;
        }
        .btn-modal-close:hover { opacity: .9; }

        /* Camera modal */
        .camera-inner {
            width: 100%;
            max-width: 380px;
            background: var(--surf);
            border-radius: var(--r-2xl);
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(0,0,0,.3);
            position: relative;
        }
        .camera-inner::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--g-grad);
            z-index: 1;
        }
        .camera-header {
            background: var(--ink);
            padding: 16px;
            text-align: center;
            color: var(--g);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
        }
        .camera-close {
            width: 100%;
            padding: 14px;
            background: var(--ink);
            color: var(--surf);
            border: none;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            font-family: var(--font);
            transition: opacity .2s;
        }
        .camera-close:hover { opacity: .85; }

        /* Cancel link */
        .cancel-link {
            display: block;
            text-align: center;
            margin-top: 14px;
            color: var(--red);
            font-weight: 700;
            font-size: 12px;
            transition: opacity .2s;
        }
        .cancel-link:hover { opacity: .7; }

        /* ═══ TYPE TABS ═══ */
        .type-tabs { display: flex; gap: 4px; margin-bottom: 16px; background: var(--bg); padding: 4px; border-radius: var(--r-md); }
        .type-tab { flex: 1; text-align: center; padding: 10px; border-radius: var(--r-sm); font-weight: 700; font-size: 11px; cursor: pointer; color: var(--ink5); transition: all .2s; }
    </style>
</head>
<body>

<div id="camera-modal" class="modal-overlay">
    <div class="camera-inner">
        <div class="camera-header">ESCANEAR DNI</div>
        <div id="reader"></div>
        <button onclick="document.getElementById('camera-modal').style.display='none'" class="camera-close">CERRAR CÁMARA</button>
    </div>
</div>

<div id="details-modal" class="modal-overlay" onclick="closeDetails()">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div class="modal-icon-wrap"><i class="fa-solid fa-file-invoice"></i></div>
        <h3 class="modal-title">Detalle del Movimiento</h3>
        <div id="modal-body"></div>
        <button onclick="closeDetails()" class="btn-modal-close">CERRAR</button>
    </div>
</div>

<nav class="header-main">
    <a href="control_garita_principal.php" class="btn-back-top">
        <i class="fa-solid fa-chevron-left" style="font-size:11px;"></i>
        <span>Volver</span>
    </a>
    <div class="topbar-logos">
        <img src="Assets Index/logo.png" class="logo-header">
        <img src="Assets Index/seguridadcivil.png" class="logo-header">
    </div>
    <div style="width:70px;"></div>
</nav>

<div class="container">

    <?php if ($mensaje): ?> <div class="alert-box <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div> <?php endif; ?>

    <?php if (!$persona && !$nuevo_dni): ?>
        <div class="driver-search-box">
            <button onclick="openScanner()" class="big-scan-btn"><i class="fa-solid fa-qrcode"></i></button>
            <h2 class="scan-title">Control Peatonal</h2>
            <form method="POST" id="formScan">
                <input type="text" name="dni_buscar" id="inputDni" class="input-driver" placeholder="DNI..." autocomplete="off">
            </form>
            <p class="scan-hint">Escanee el código o ingrese el DNI manualmente</p>
        </div>
    <?php endif; ?>

    <?php if ($persona || $nuevo_dni): ?>
        <div class="result-card">
            <div class="card-header">
                <?php if ($persona): ?>
                    <h2><?php echo explode(" ", $persona['nombres'])[0] . " " . explode(" ", $persona['apellidos'])[0]; ?></h2>
                    <p><?php echo $persona['empresa']; ?> | DNI: <?php echo $persona['dni']; ?></p>

                    <?php if (isset($persona['tipo_personal']) && $persona['tipo_personal'] == 'VISITA'): ?>
                        <div class="badge-tipo badge-visita">VISITA</div>
                    <?php else: ?>
                        <div class="badge-tipo badge-permanente">PERMANENTE</div>
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

                    <div id="visita-box" style="display:<?php echo (isset($persona) && $persona['tipo_personal']=='VISITA')?'block':'none'; ?>;">
                        <label class="label-mini">ANFITRIÓN</label>
                        <input type="text" name="anfitrion" class="input-comp" placeholder="A quien visita...">
                        <label class="label-mini">MOTIVO</label>
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

                    <a href="control_personal.php" class="cancel-link">CANCELAR</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- HISTORIAL -->
    <div class="sec-row">
        <span class="sec-txt">Últimos Movimientos</span>
        <div class="sec-line"></div>
    </div>

    <div class="history-box">
        <?php while($row = mysqli_fetch_assoc($res_hist)):
            $is_out = ($row['tipo_movimiento'] == 'SALIDA');
            $data_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
        ?>
            <div class="history-item <?php echo $is_out?'item-out':'item-in'; ?>" onclick="showHistoryDetails('<?php echo $data_json; ?>')">
                <div style="flex:1;">
                    <div class="h-name">
                        <?php if($is_out): ?>
                            <span class="h-badge-out">SALIDA</span>
                        <?php else: ?>
                            <span class="h-badge-in">INGRESO</span>
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
</script>

</body>
</html>
