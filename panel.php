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
$sql_user = "SELECT nombre, cargo_real FROM usuarios WHERE nombre_usuario = '$usuario_session' LIMIT 1";
$res_user = mysqli_query($conn, $sql_user);

$nombre_completo = $_SESSION['usuario'];
$cargo_real = "OPERADOR DE SEGURIDAD";

if ($res_user && mysqli_num_rows($res_user) > 0) {
    $fila_usuario = mysqli_fetch_assoc($res_user);
    $nombre_completo = $fila_usuario['nombre'];
    $cargo_real = $fila_usuario['cargo_real'];
}

// 4. QUICK STATS - consultas del día
$hoy = date('Y-m-d');
$sql_ingresos = "SELECT COUNT(*) as total FROM registros_garita WHERE tipo_movimiento='INGRESO' AND DATE(fecha_ingreso)='$hoy'";
$sql_salidas  = "SELECT COUNT(*) as total FROM registros_garita WHERE tipo_movimiento='SALIDA' AND DATE(fecha_ingreso)='$hoy'";
$sql_vehiculos = "SELECT COUNT(DISTINCT placa_unidad) as total FROM registros_garita WHERE DATE(fecha_ingreso)='$hoy'";
$sql_peak = "SELECT HOUR(fecha_ingreso) as h, COUNT(*) as c FROM registros_garita WHERE DATE(fecha_ingreso)='$hoy' GROUP BY HOUR(fecha_ingreso) ORDER BY c DESC LIMIT 1";

$stat_ingresos = 0; $stat_salidas = 0; $stat_vehiculos = 0; $stat_peak = '--';
$r = mysqli_query($conn, $sql_ingresos); if ($r && $row = mysqli_fetch_assoc($r)) $stat_ingresos = (int)$row['total'];
$r = mysqli_query($conn, $sql_salidas);  if ($r && $row = mysqli_fetch_assoc($r)) $stat_salidas = (int)$row['total'];
$r = mysqli_query($conn, $sql_vehiculos);if ($r && $row = mysqli_fetch_assoc($r)) $stat_vehiculos = (int)$row['total'];
$r = mysqli_query($conn, $sql_peak);     if ($r && $row = mysqli_fetch_assoc($r)) $stat_peak = str_pad($row['h'],2,'0',STR_PAD_LEFT).':00';

$hora = (int)date('H');
if ($hora < 12)     $saludo = 'Buenos días';
elseif ($hora < 19) $saludo = 'Buenas tardes';
else                $saludo = 'Buenas noches';

$partes = explode(' ', trim($nombre_completo));
$iniciales = strtoupper(substr($partes[0],0,1).(isset($partes[1])?substr($partes[1],0,1):''));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#F4F4F4">
    <title>Panel SINTEGRA | Hochschild</title>
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
            --safe-t: env(safe-area-inset-top, 0px);
            --safe-b: env(safe-area-inset-bottom, 0px);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
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

        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

        /* ═══ TOPBAR ═══ */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 200;
            background: rgba(255,255,255,.97);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid rgba(0,0,0,.07);
            padding-top: var(--safe-t);
            box-shadow: 0 1px 0 rgba(255,255,255,1), 0 2px 16px rgba(0,0,0,.05);
        }
        .topbar-inner {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 16px;
            height: 66px;
            display: flex;
            align-items: center;
        }
        .logo-block {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .logo-img { height: 36px; width: auto; object-fit: contain; }
        .logo-sep { width: 1px; height: 28px; background: rgba(0,0,0,.1); margin: 0 4px; }
        .logo-wordmark { display: flex; flex-direction: column; }
        .logo-name { font-size: 11px; font-weight: 900; color: var(--ink); letter-spacing: 4px; text-transform: uppercase; line-height: 1; }
        .logo-sub { font-size: 6.5px; font-weight: 500; color: var(--ink5); letter-spacing: 2px; text-transform: uppercase; margin-top: 3px; }

        .top-space { flex: 1; }

        .top-live {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--gb);
            border: 1px solid var(--gbo);
            border-radius: 99px;
            padding: 5px 11px;
            margin-right: 10px;
        }
        .top-live-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #22C55E;
            box-shadow: 0 0 7px rgba(34,197,94,.7);
            animation: live-pulse 2s ease-in-out infinite;
        }
        @keyframes live-pulse {
            0%,100% { opacity:1; box-shadow: 0 0 6px rgba(34,197,94,.7); }
            50% { opacity:.6; box-shadow: 0 0 12px rgba(34,197,94,.2); }
        }
        .top-live-txt { font-size: 7.5px; font-weight: 700; color: var(--green); letter-spacing: .6px; text-transform: uppercase; }

        .top-av {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--ink);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800; color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
            border: 2px solid var(--g);
            flex-shrink: 0;
        }

        .top-logout {
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
            margin-left: 8px;
            transition: all .2s;
        }
        .top-logout:hover { color: var(--red); border-color: rgba(252,165,165,.4); background: #FEF2F2; }

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

        /* ═══ HERO ═══ */
        .hero {
            background: var(--surf);
            border: 1px solid rgba(0,0,0,.07);
            border-radius: var(--r-2xl);
            padding: 22px 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(0,0,0,.06), 0 1px 0 #fff inset;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, #0A0A0A 0%, #C49A2C 35%, #E8C85A 60%, transparent 100%);
            border-radius: var(--r-2xl) var(--r-2xl) 0 0;
        }
        .hero-eyebrow {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .hero-eyebrow-bar { width: 16px; height: 2px; background: var(--g); border-radius: 1px; }
        .hero-eyebrow-txt { font-size: 8px; font-weight: 700; letter-spacing: 2.5px; color: var(--g); text-transform: uppercase; }

        .hero-name {
            font-size: 28px;
            font-weight: 900;
            color: var(--ink);
            letter-spacing: -1px;
            line-height: 1.05;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .hero-role {
            font-size: 10px;
            color: var(--ink5);
            margin-bottom: 14px;
            font-weight: 400;
        }
        .hero-chips {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        .chip {
            font-size: 9px;
            font-weight: 600;
            padding: 4px 11px;
            border-radius: 6px;
            letter-spacing: .1px;
            white-space: nowrap;
        }
        .chip-gold { background: var(--ink); border: 1px solid var(--ink); color: #fff; }
        .chip-silver { background: transparent; border: 1px solid rgba(0,0,0,.15); color: var(--ink3); }
        .chip-green { background: var(--gb); border: 1px solid var(--gbo); color: #15652A; }

        /* ═══ SECTION LABEL ═══ */
        .sec-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 2px;
        }
        .sec-txt {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--ink);
            white-space: nowrap;
        }
        .sec-line { flex: 1; height: 1px; background: rgba(0,0,0,.1); }

        /* ═══ MONITOR BANNER ═══ */
        .monitor-banner {
            background: var(--surf);
            border: 1px solid rgba(0,0,0,.07);
            border-radius: var(--r-xl);
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,.06), 0 1px 0 #fff inset;
            transition: transform .22s, box-shadow .22s, border-color .22s;
        }
        .monitor-banner::before {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, #E8C85A, #C49A2C, #8A6A14);
            border-radius: var(--r-xl) 0 0 var(--r-xl);
        }
        .monitor-banner::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, #C49A2C 0%, #E8C85A 40%, transparent 100%);
            border-radius: var(--r-xl) var(--r-xl) 0 0;
            opacity: .6;
        }
        .monitor-banner:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(196,154,44,.14), 0 0 0 1px var(--b-gold); border-color: var(--b-gold); }
        .monitor-banner:active { transform: scale(.99); }

        .mon-ico-wrap {
            width: 46px; height: 46px;
            border-radius: 12px;
            background: var(--gl);
            border: 1px solid var(--b-gold);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            position: relative; z-index: 1;
            font-size: 20px;
            color: var(--gd);
        }
        .mon-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #22C55E;
            box-shadow: 0 0 9px rgba(34,197,94,.8);
            animation: live-pulse 2s infinite;
            flex-shrink: 0;
        }
        .mon-content { flex: 1; min-width: 0; position: relative; z-index: 1; }
        .mon-label { font-size: 8px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--gd); margin-bottom: 3px; display: flex; align-items: center; gap: 6px; }
        .mon-t { font-size: 14px; font-weight: 800; color: var(--ink); letter-spacing: -.2px; line-height: 1.2; }
        .mon-s { font-size: 10px; color: var(--ink5); margin-top: 3px; }
        .mon-arr { margin-left: auto; flex-shrink: 0; position: relative; z-index: 1; color: #D8D4CE; font-size: 18px; transition: all .2s; }
        .monitor-banner:hover .mon-arr { color: var(--g); transform: translateX(3px); }

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
            padding: 32px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        .footer-logos { display: flex; gap: 24px; align-items: center; }
        .footer-logo {
            height: 40px;
            object-fit: contain;
            filter: grayscale(.6);
            opacity: .5;
            transition: all .3s;
        }
        .footer-logo:hover { filter: grayscale(0); opacity: .9; transform: scale(1.05); }
        .footer-text {
            font-size: 8px;
            font-weight: 600;
            letter-spacing: 2.5px;
            color: var(--ink6);
            text-transform: uppercase;
        }

        /* ═══ WELCOME OVERLAY ═══ */
        /* ═══ WELCOME OVERLAY — CINEMATIC ═══ */
        .welcome-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 9999;
            background: #050505;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 1;
            overflow: hidden;
        }
        .welcome-overlay.phase-out {
            animation: welcomeFadeOut .8s .2s ease forwards;
        }
        @keyframes welcomeFadeOut {
            0% { opacity: 1; }
            100% { opacity: 0; pointer-events: none; }
        }

        /* Fondo radial dorado */
        .w-bg-glow {
            position: absolute;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(196,154,44,.15) 0%, rgba(196,154,44,.05) 40%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%,-50%) scale(0);
            animation: glowExpand 2s .3s cubic-bezier(.22,1,.36,1) forwards;
        }
        @keyframes glowExpand {
            to { transform: translate(-50%,-50%) scale(1); }
        }

        /* Líneas horizontales decorativas */
        .w-line {
            position: absolute;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(196,154,44,.4), transparent);
            top: 50%; left: 50%;
            transform: translateX(-50%) scaleX(0);
        }
        .w-line-1 { width: 500px; margin-top: -80px; animation: lineReveal 1s .5s ease forwards; }
        .w-line-2 { width: 500px; margin-top: 80px; animation: lineReveal 1s .7s ease forwards; }
        .w-line-3 { width: 300px; margin-top: 120px; animation: lineReveal 1s .9s ease forwards; opacity: .4; }
        @keyframes lineReveal {
            to { transform: translateX(-50%) scaleX(1); }
        }

        /* Logo Hochschild */
        .w-logo-container {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
        }
        .w-logo-img {
            width: 80px;
            height: auto;
            filter: brightness(0) invert(1);
            opacity: 0;
            transform: scale(.7);
            animation: logoReveal .8s .4s cubic-bezier(.22,1,.36,1) forwards;
        }
        @keyframes logoReveal {
            to { opacity: 1; transform: scale(1); }
        }

        /* Anillo dorado alrededor del logo */
        .w-logo-ring {
            position: absolute;
            top: -15px;
            width: 110px; height: 110px;
            border-radius: 50%;
            border: 1.5px solid transparent;
            background: conic-gradient(from 0deg, transparent 0%, rgba(196,154,44,.6) 25%, transparent 50%, rgba(196,154,44,.6) 75%, transparent 100%) border-box;
            -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            animation: ringReveal 1.2s .6s ease forwards, ringSpin 4s 1s linear infinite;
        }
        @keyframes ringReveal { to { opacity: .7; } }
        @keyframes ringSpin { to { transform: rotate(360deg); } }

        /* Texto SINTEGRA */
        .w-brand {
            margin-top: 28px;
            font-family: var(--font);
            font-size: 38px;
            font-weight: 900;
            letter-spacing: 14px;
            text-indent: 14px;
            background: linear-gradient(135deg, #B8860B, #E8C85A, #FFD700, #E8C85A, #B8860B);
            background-size: 300% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0;
            transform: translateY(10px);
            animation: textUp .7s .8s ease forwards, goldShimmer 3s 1.5s ease infinite;
        }
        .w-subtitle {
            font-family: var(--font);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 6px;
            text-indent: 6px;
            text-transform: uppercase;
            color: rgba(255,255,255,.35);
            margin-top: 6px;
            opacity: 0;
            animation: textUp .6s 1s ease forwards;
        }

        /* Separador dorado */
        .w-divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #C49A2C, transparent);
            margin-top: 24px;
            opacity: 0;
            transform: scaleX(0);
            animation: dividerIn .6s 1.2s ease forwards;
        }
        @keyframes dividerIn {
            to { opacity: 1; transform: scaleX(1); }
        }

        /* Bienvenido */
        .w-welcome-text {
            font-family: var(--font);
            font-size: 13px;
            font-weight: 400;
            color: rgba(255,255,255,.5);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 20px;
            opacity: 0;
            animation: textUp .6s 1.4s ease forwards;
        }
        .w-user-name {
            font-family: var(--font);
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 2px;
            margin-top: 6px;
            opacity: 0;
            animation: textUp .6s 1.6s ease forwards;
        }

        /* Barra de carga */
        .w-loader {
            position: absolute;
            bottom: 60px;
            width: 120px;
            height: 2px;
            background: rgba(255,255,255,.08);
            border-radius: 2px;
            overflow: hidden;
        }
        .w-loader-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #C49A2C, #FFD700);
            border-radius: 2px;
            animation: loaderFill 2.8s .8s ease-in-out forwards;
        }
        @keyframes loaderFill {
            0% { width: 0; }
            100% { width: 100%; }
        }

        /* Texto inferior */
        .w-footer {
            position: absolute;
            bottom: 30px;
            font-family: var(--font);
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 3px;
            color: rgba(255,255,255,.2);
            text-transform: uppercase;
            opacity: 0;
            animation: textUp .5s 1s ease forwards;
        }

        @keyframes textUp {
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes goldShimmer {
            0%,100% { background-position: 0% 50%; }
            50%     { background-position: 100% 50%; }
        }

        /* Partículas flotantes premium */
        .w-particle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .w-particle-gold {
            background: radial-gradient(circle, rgba(232,200,90,.8), rgba(196,154,44,.3));
            animation: pFloat var(--dur) var(--delay) ease-in-out infinite;
        }
        .w-particle-white {
            background: radial-gradient(circle, rgba(255,255,255,.5), rgba(255,255,255,.1));
            animation: pFloat var(--dur) var(--delay) ease-in-out infinite;
        }
        @keyframes pFloat {
            0%   { opacity: 0; transform: translateY(0) scale(.3); }
            15%  { opacity: 1; }
            85%  { opacity: .6; }
            100% { opacity: 0; transform: translateY(var(--travel)) scale(0); }
        }

        /* ═══ QUICK STATS STRIP ═══ */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }
        .stat-card {
            background: var(--surf);
            border: 1px solid rgba(0,0,0,.07);
            border-radius: var(--r-md);
            padding: 12px 8px;
            text-align: center;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
            opacity: 0;
            animation: card-fade-in .5s ease forwards;
        }
        .stat-card:nth-child(1) { animation-delay: .1s; }
        .stat-card:nth-child(2) { animation-delay: .2s; }
        .stat-card:nth-child(3) { animation-delay: .3s; }
        .stat-card:nth-child(4) { animation-delay: .4s; }
        .stat-num {
            font-family: var(--mono);
            font-size: 22px;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.1;
        }
        .stat-label {
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--ink5);
            margin-top: 4px;
        }
        .stat-ico {
            font-size: 11px;
            color: var(--g);
            margin-bottom: 4px;
        }

        /* ═══ MICRO ANIMATIONS ═══ */
        @keyframes card-fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hero, .monitor-banner, .mcard {
            opacity: 0;
            animation: card-fade-in .5s ease forwards;
        }
        .hero { animation-delay: .05s; }
        .monitor-banner { animation-delay: .15s; }
        .mcard:nth-child(1) { animation-delay: .2s; }
        .mcard:nth-child(2) { animation-delay: .3s; }
        .mcard:nth-child(3) { animation-delay: .4s; }

        /* Ripple effect */
        .ripple-wrap { position: relative; overflow: hidden; }
        .ripple-wrap .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(196,154,44,.25);
            transform: scale(0);
            animation: ripple-anim .6s ease-out;
            pointer-events: none;
        }
        @keyframes ripple-anim {
            to { transform: scale(4); opacity: 0; }
        }

        /* Smooth hover extras for module cards */
        .mcard-ico { transition: transform .25s ease, box-shadow .25s ease; }
        .mcard:hover .mcard-ico { transform: scale(1.08); box-shadow: 0 2px 10px rgba(196,154,44,.18); }
    </style>
</head>
<body>

<!-- WELCOME OVERLAY — CINEMATIC -->
<div class="welcome-overlay" id="welcomeOverlay">
    <!-- Background glow -->
    <div class="w-bg-glow"></div>
    <!-- Decorative lines -->
    <div class="w-line w-line-1"></div>
    <div class="w-line w-line-2"></div>
    <div class="w-line w-line-3"></div>

    <!-- Center content -->
    <div class="w-logo-container">
        <div style="position:relative; display:flex; align-items:center; justify-content:center;">
            <div class="w-logo-ring"></div>
            <img src="Assets Index/logo.png" alt="Hochschild Mining" class="w-logo-img">
        </div>
        <div class="w-brand">SINTEGRA</div>
        <div class="w-subtitle">Sistema de Control Integrado</div>
        <div class="w-divider"></div>
        <div class="w-welcome-text">Bienvenido</div>
        <div class="w-user-name"><?php echo htmlspecialchars($nombre_completo); ?></div>
    </div>

    <!-- Loader bar -->
    <div class="w-loader"><div class="w-loader-bar"></div></div>
    <!-- Footer -->
    <div class="w-footer">Hochschild Mining &bull; Seguridad Integral</div>
</div>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-inner">
        <div class="logo-block">
            <img src="Assets Index/logo.png" alt="Hochschild" class="logo-img">
            <div class="logo-sep"></div>
            <div class="logo-wordmark">
                <span class="logo-name">SINTEGRA</span>
                <span class="logo-sub">Sistema de Control Integrado</span>
            </div>
        </div>
        <div class="top-space"></div>
        <div class="top-live">
            <div class="top-live-dot"></div>
            <span class="top-live-txt">Activo</span>
        </div>
        <div class="top-av"><?php echo $iniciales; ?></div>
        <a href="logout.php" class="top-logout">
            <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Salir
        </a>
    </div>
    <div class="topbar-gold"></div>
</div>

<div class="main-wrap">

    <!-- HERO -->
    <div class="hero">
        <div class="hero-eyebrow">
            <div class="hero-eyebrow-bar"></div>
            <span class="hero-eyebrow-txt"><?php echo $saludo; ?></span>
        </div>
        <div class="hero-name"><?php echo mb_strtoupper($nombre_completo); ?></div>
        <div class="hero-role"><?php echo htmlspecialchars($cargo_real); ?></div>
        <div class="hero-chips">
            <span class="chip chip-gold"><?php echo mb_strtoupper($cargo_real); ?></span>
            <span class="chip chip-green"><i class="fa-solid fa-circle" style="font-size:5px; vertical-align:middle; margin-right:4px;"></i>En servicio</span>
            <span class="chip chip-silver"><?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <!-- QUICK STATS -->
    <div class="stats-strip">
        <div class="stat-card">
            <div class="stat-ico"><i class="fa-solid fa-arrow-right-to-bracket"></i></div>
            <div class="stat-num" data-target="<?php echo $stat_ingresos; ?>">0</div>
            <div class="stat-label">Ingresos</div>
        </div>
        <div class="stat-card">
            <div class="stat-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
            <div class="stat-num" data-target="<?php echo $stat_salidas; ?>">0</div>
            <div class="stat-label">Salidas</div>
        </div>
        <div class="stat-card">
            <div class="stat-ico"><i class="fa-solid fa-truck"></i></div>
            <div class="stat-num" data-target="<?php echo $stat_vehiculos; ?>">0</div>
            <div class="stat-label">Vehículos</div>
        </div>
        <div class="stat-card">
            <div class="stat-ico"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-num"><?php echo $stat_peak; ?></div>
            <div class="stat-label">Hora Pico</div>
        </div>
    </div>

    <!-- MONITOR BANNER -->
    <div class="sec-row">
        <span class="sec-txt">Centro de Operaciones</span>
        <div class="sec-line"></div>
    </div>

    <a href="monitoreo.php" class="monitor-banner">
        <div class="mon-ico-wrap">
            <i class="fa-solid fa-desktop"></i>
        </div>
        <div class="mon-content">
            <div class="mon-label">
                <span>Monitoreo en vivo</span>
                <div class="mon-dot"></div>
            </div>
            <div class="mon-t">Centro de Monitoreo Real-Time</div>
            <div class="mon-s">KPIs operativos, aforo en vivo y radar de movimientos</div>
        </div>
        <div class="mon-arr"><i class="fa-solid fa-chevron-right"></i></div>
    </a>

    <!-- MODULES -->
    <div class="sec-row" style="margin-top: 6px;">
        <span class="sec-txt">Módulos</span>
        <div class="sec-line"></div>
    </div>

    <div class="mod-list">
        <!-- Control de Acceso -->
        <a href="control_garita_principal.php" class="mcard">
            <div class="mcard-accent" style="background: var(--ink);"></div>
            <div class="mcard-body">
                <div class="mcard-ico" style="background: var(--surf2); color: var(--ink);">
                    <i class="fa-solid fa-id-card-clip"></i>
                </div>
                <div class="mcard-text">
                    <div class="mcard-t">Control de Acceso Integral</div>
                    <div class="mcard-s">Gestión centralizada de ingresos, personal y vehículos</div>
                </div>
                <div class="mcard-arr"><i class="fa-solid fa-chevron-right"></i></div>
            </div>
        </a>

        <!-- Plan Torque -->
        <a href="#" class="mcard" onclick="alert('Módulo de Plan Torque y Procedimientos estará disponible próximamente.')">
            <div class="mcard-accent" style="background: var(--g);"></div>
            <div class="mcard-body">
                <div class="mcard-ico" style="background: var(--gl); color: var(--gd);">
                    <i class="fa-solid fa-file-contract"></i>
                </div>
                <div class="mcard-text">
                    <div class="mcard-t">Plan Torque y Procedimientos</div>
                    <div class="mcard-s">Consulta de lineamientos y documentación operativa</div>
                </div>
                <div class="mcard-arr"><i class="fa-solid fa-chevron-right"></i></div>
            </div>
            <span class="mcard-badge" style="background: var(--gl); color: var(--gd);">Pronto</span>
        </a>

        <!-- Capacitaciones -->
        <a href="#" class="mcard" onclick="alert('Módulo de Capacitaciones estará disponible próximamente.')">
            <div class="mcard-accent" style="background: var(--green);"></div>
            <div class="mcard-body">
                <div class="mcard-ico" style="background: var(--gb); color: var(--green);">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div class="mcard-text">
                    <div class="mcard-t">Capacitaciones</div>
                    <div class="mcard-s">Registro y seguimiento de formación del personal</div>
                </div>
                <div class="mcard-arr"><i class="fa-solid fa-chevron-right"></i></div>
            </div>
            <span class="mcard-badge" style="background: var(--gb); color: var(--green);">Pronto</span>
        </a>
    </div>
</div>

<!-- FOOTER -->
<div class="footer-wrap">
    <div class="footer-logos">
        <img src="Assets Index/Hochscild_logo3.png" alt="Hochschild" class="footer-logo">
        <img src="Assets Index/Torque SC.png" alt="Torque" class="footer-logo">
    </div>
    <div class="footer-text">Hochschild Mining · 2026</div>
</div>

<script>
// ═══ WELCOME OVERLAY — CINEMATIC ═══
(function(){
    var overlay = document.getElementById('welcomeOverlay');
    if (!overlay) return;
    if (sessionStorage.getItem('sintegra_welcomed')) {
        overlay.remove();
        return;
    }

    // Generar partículas premium (doradas + blancas)
    for (var i = 0; i < 35; i++) {
        var p = document.createElement('div');
        var isGold = Math.random() > .3;
        p.className = 'w-particle ' + (isGold ? 'w-particle-gold' : 'w-particle-white');
        var size = 2 + Math.random() * 5;
        p.style.width = size + 'px';
        p.style.height = size + 'px';
        p.style.left = (10 + Math.random() * 80) + '%';
        p.style.top = (30 + Math.random() * 50) + '%';
        p.style.setProperty('--dur', (2.5 + Math.random() * 2.5) + 's');
        p.style.setProperty('--delay', (Math.random() * 3) + 's');
        p.style.setProperty('--travel', '-' + (80 + Math.random() * 120) + 'px');
        overlay.appendChild(p);
    }

    // Fase de salida después de 3.8s
    setTimeout(function(){
        overlay.classList.add('phase-out');
        setTimeout(function(){ overlay.remove(); }, 1000);
    }, 3800);

    sessionStorage.setItem('sintegra_welcomed', '1');
})();

// ═══ ANIMATED COUNTERS ═══
(function(){
    var nums = document.querySelectorAll('.stat-num[data-target]');
    nums.forEach(function(el){
        var target = parseInt(el.getAttribute('data-target')) || 0;
        if (target === 0) return;
        var duration = 1200, start = null;
        function step(ts){
            if (!start) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(ease * target);
            if (progress < 1) requestAnimationFrame(step);
        }
        setTimeout(function(){ requestAnimationFrame(step); }, 400);
    });
})();

// ═══ RIPPLE EFFECT ═══
(function(){
    var targets = document.querySelectorAll('.mcard, .monitor-banner, button');
    targets.forEach(function(el){
        el.classList.add('ripple-wrap');
        el.addEventListener('click', function(e){
            var rect = el.getBoundingClientRect();
            var ripple = document.createElement('span');
            ripple.className = 'ripple';
            var size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
            el.appendChild(ripple);
            setTimeout(function(){ ripple.remove(); }, 600);
        });
    });
})();
</script>
</body>
</html>
