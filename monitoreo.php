<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

// 1. CONEXIÓN A LA BASE DE DATOS
require_once 'config.php';
// $conn disponible desde config.php (Hostinger)
// 2. LÓGICA AJAX / ACCIONES INDEPENDIENTES
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? 'dashboard';

    // --- ACCIÓN: BUSCAR PERSONAL AUTÓNOMO (Base de Datos Maestra) ---
    if ($action == 'search_person') {
        $q = trim(mysqli_real_escape_string($conn, $_GET['q']));
        if (empty($q)) { echo json_encode(['error' => 'Ingrese un término']); exit; }

        $sql = "SELECT f.*, d.nro_licencia, d.categoria_mtc, d.f_revalidacion as d_vcto, d.categoria_mina
                FROM fuerza_laboral f
                LEFT JOIN detalles_conductor d ON f.dni = d.dni
                WHERE f.dni = '$q' OR f.nombres LIKE '%$q%' OR f.apellidos LIKE '%$q%'
                LIMIT 1";
        $res = mysqli_query($conn, $sql);
        $person = mysqli_fetch_assoc($res);

        if ($person) {
            echo json_encode(['success' => true, 'data' => $person]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Persona no encontrada en la Base de Datos Maestra.']);
        }
        exit;
    }

    // --- ACCIÓN: DASHBOARD Y RADAR (Movimientos) ---
    $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? mysqli_real_escape_string($conn, $_GET['fecha']) : date('Y-m-d');
    $search_radar = isset($_GET['q']) ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';

    $where_radar = "DATE(r.fecha_ingreso) = '$fecha'";
    if (!empty($search_radar)) {
        $where_radar .= " AND (r.placa_unidad LIKE '%$search_radar%' OR r.nombre_conductor LIKE '%$search_radar%' OR r.dni_conductor LIKE '%$search_radar%')";
    }

    // KPIs
    $q_kpis = mysqli_query($conn, "SELECT
        SUM(CASE WHEN tipo_movimiento = 'INGRESO' THEN 1 ELSE 0 END) as in_today,
        SUM(CASE WHEN tipo_movimiento = 'SALIDA' THEN 1 ELSE 0 END) as out_today
        FROM registros_garita WHERE DATE(fecha_ingreso) = '$fecha'");
    $res_kpis = mysqli_fetch_assoc($q_kpis);

    // Ranking
    $sql_top = "SELECT empresa, COUNT(*) as total FROM registros_garita WHERE tipo_movimiento = 'SALIDA' AND DATE(fecha_ingreso) = '$fecha' GROUP BY empresa ORDER BY total DESC LIMIT 5";
    $q_top = mysqli_query($conn, $sql_top);
    $top_empresas = [];
    while($row = mysqli_fetch_assoc($q_top)) { $top_empresas[] = $row; }

    // Radar
    $sql_feed = "SELECT r.*, v.marca as v_marca, v.tipo_vehiculo as v_tipo, d.nro_licencia as d_licencia
                 FROM registros_garita r
                 LEFT JOIN vehiculos v ON r.placa_unidad = v.placa
                 LEFT JOIN detalles_conductor d ON r.dni_conductor = d.dni
                 WHERE $where_radar
                 ORDER BY r.fecha_ingreso DESC LIMIT 100";
    $q_feed = mysqli_query($conn, $sql_feed);
    $feed = [];
    while ($row = mysqli_fetch_assoc($q_feed)) {
        $row['fecha_fmt'] = date('d/m/Y', strtotime($row['fecha_ingreso']));
        $row['hora_fmt'] = date('H:i', strtotime($row['fecha_ingreso']));
        $feed[] = $row;
    }

    echo json_encode([
        'kpis' => [
            'ingresos' => (int)($res_kpis['in_today'] ?? 0),
            'salidas' => (int)($res_kpis['out_today'] ?? 0),
            'total' => (int)($res_kpis['in_today'] ?? 0) + (int)($res_kpis['out_today'] ?? 0)
        ],
        'top_empresas' => $top_empresas,
        'feed' => $feed
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SITRAN | Centro de Monitoreo Profesional</title>
    <!-- FUENTES E ICONOS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --bg: #F4F4F4;
            --surf: #FFFFFF;
            --b: rgba(0,0,0,.08);
            --b-gold: rgba(196,154,44,.3);
            --red: #DC2626;
            --green: #16A34A;
            --font: 'Inter', system-ui, sans-serif;
            --mono: 'JetBrains Mono', monospace;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--ink);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── TOPBAR GLASS ─── */
        .header-main {
            background: rgba(255,255,255,.97);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: 0 40px;
            height: 64px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,.07);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-main::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--ink), var(--g), var(--ink));
        }

        .brand { display: flex; align-items: center; gap: 14px; }
        .brand img { height: 32px; }
        .brand-text {
            font-weight: 800;
            font-size: .85rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            background: var(--g-grad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-right { display: flex; align-items: center; gap: 20px; }
        .header-user { text-align: right; }
        .header-user-name { font-size: 13px; font-weight: 700; color: var(--ink); letter-spacing: .5px; }
        .header-user-role { font-size: 9px; color: var(--ink5); font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; }
        .header-home {
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 10px;
            color: var(--ink3);
            font-size: 16px;
            transition: all .25s;
            border: 1px solid var(--b);
        }
        .header-home:hover { background: var(--gl); color: var(--g); border-color: var(--b-gold); }

        /* ─── SEARCH SECTION ─── */
        .search-section {
            background: var(--surf);
            padding: 24px 40px;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 20px;
            align-items: flex-end;
            border-bottom: 1px solid var(--b);
        }

        .input-group label {
            display: block;
            font-size: 8px;
            color: var(--ink5);
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1.5px;
        }

        .input-wrapper { position: relative; }
        .input-wrapper input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--b);
            padding: 12px 12px 12px 44px;
            border-radius: 12px;
            color: var(--ink);
            font-size: 13px;
            font-family: var(--font);
            outline: none;
            transition: all .25s;
        }
        .input-wrapper input:focus {
            border-color: var(--b-gold);
            background: var(--surf);
            box-shadow: 0 0 0 3px var(--gr);
        }
        .input-wrapper input::placeholder { color: var(--ink5); font-weight: 400; }
        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ink5);
            font-size: 14px;
        }

        .search-actions label {
            display: block;
            font-size: 8px;
            color: var(--ink5);
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1.5px;
        }
        .search-actions-row { display: flex; gap: 10px; }

        .filter-date {
            background: var(--bg);
            border: 1px solid var(--b);
            color: var(--ink);
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-family: var(--font);
            outline: none;
            transition: all .25s;
        }
        .filter-date:focus { border-color: var(--b-gold); box-shadow: 0 0 0 3px var(--gr); }

        .btn-refresh {
            background: var(--ink);
            color: var(--surf);
            border: none;
            padding: 12px 22px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all .25s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            letter-spacing: .5px;
            font-family: var(--font);
        }
        .btn-refresh:hover { background: var(--ink2); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }

        /* ─── CONTAINER ─── */
        .container { padding: 32px 40px; max-width: 1700px; margin: 0 auto; }

        /* ─── SECTION ROW HEADERS ─── */
        .sec-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .sec-row span {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            color: var(--ink5);
            white-space: nowrap;
        }
        .sec-row::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, var(--ink), var(--g), transparent);
        }

        /* ─── KPI CARDS ─── */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; }

        .kpi-card {
            background: var(--surf);
            padding: 28px 24px;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,.07);
            box-shadow: 0 2px 16px rgba(0,0,0,.04);
            transition: all .3s;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--g-grad);
            opacity: 0;
            transition: opacity .3s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,.08); border-color: var(--b-gold); }
        .kpi-card:hover::before { opacity: 1; }

        .kpi-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            color: rgba(0,0,0,.3);
            margin-bottom: 8px;
        }
        .kpi-value {
            font-family: var(--mono);
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--ink);
            line-height: 1;
        }
        .kpi-icon {
            position: absolute;
            top: 24px;
            right: 24px;
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px;
            background: var(--bg);
            color: var(--ink5);
            font-size: 16px;
        }
        .kpi-icon.gold { background: var(--gl); color: var(--g); }
        .kpi-icon.green { background: #F0FDF4; color: var(--green); }
        .kpi-icon.red { background: #FEF2F2; color: var(--red); }

        /* ─── MAIN GRID ─── */
        .main-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; }
        @media (max-width: 1200px) { .main-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .search-section { grid-template-columns: 1fr; }
            .container { padding: 20px 16px; }
            .header-main { padding: 0 16px; }
            .search-section { padding: 20px 16px; }
        }

        /* ─── CONTENT BOXES ─── */
        .content-box {
            background: var(--surf);
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,.07);
            box-shadow: 0 2px 16px rgba(0,0,0,.04);
            overflow: hidden;
        }

        .box-header {
            padding: 20px 24px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            color: var(--ink5);
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--b);
            position: relative;
        }
        .box-header::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: var(--g-grad);
            border-radius: 0 3px 3px 0;
        }
        .box-header i { color: var(--g); font-size: 14px; }

        /* ─── RANKING SIDEBAR ─── */
        .rank-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 24px;
            border-bottom: 1px solid var(--b);
            transition: background .2s;
        }
        .rank-item:last-child { border-bottom: none; }
        .rank-item:hover { background: var(--gl); }
        .rank-name { font-size: 13px; font-weight: 600; color: var(--ink2); }
        .rank-badge {
            font-family: var(--mono);
            font-size: 12px;
            font-weight: 700;
            background: var(--gl);
            color: var(--gd);
            padding: 4px 12px;
            border-radius: 8px;
        }

        /* ─── SERVER TIME CARD ─── */
        .time-card {
            background: var(--surf);
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,.07);
            box-shadow: 0 2px 16px rgba(0,0,0,.04);
            padding: 28px 24px;
            text-align: center;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }
        .time-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--g-grad);
        }
        .time-value {
            font-family: var(--mono);
            font-size: 2rem;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: 2px;
        }
        .time-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            color: rgba(0,0,0,.25);
            margin-top: 8px;
        }

        /* ─── TABLE ─── */
        .table-container { overflow-x: auto; max-height: 700px; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        thead { position: sticky; top: 0; z-index: 10; }
        th {
            background: var(--surf);
            padding: 14px 18px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-align: left;
            color: rgba(0,0,0,.35);
            font-weight: 700;
            border-bottom: 1px solid var(--b);
        }
        td {
            padding: 14px 18px;
            font-size: 13px;
            border-bottom: 1px solid rgba(0,0,0,.04);
            vertical-align: middle;
        }
        tr:hover td { background: rgba(0,0,0,.015); }

        .badge-placa {
            font-family: var(--mono);
            font-weight: 700;
            font-size: 12px;
            color: var(--ink);
            background: var(--bg);
            padding: 4px 10px;
            border-radius: 8px;
            border: 1px solid var(--b);
            display: inline-block;
        }
        .badge-mov {
            font-weight: 700;
            font-size: 10px;
            padding: 5px 12px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: inline-block;
        }
        .bg-in { background: #F0FDF4; color: var(--green); }
        .bg-out { background: #FEF2F2; color: var(--red); }

        .td-meta { font-size: 11px; color: var(--ink5); font-weight: 500; margin-top: 2px; }

        /* ─── MODAL ─── */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,.5);
            display: none; justify-content: center; align-items: center;
            z-index: 2000;
            backdrop-filter: blur(8px);
        }
        .modal-content {
            background: var(--surf);
            width: 640px;
            max-width: 95vw;
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(0,0,0,.2);
            overflow: hidden;
            animation: slideIn 0.35s ease-out;
            position: relative;
        }
        .modal-content::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--g-grad);
        }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header {
            padding: 28px 32px 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--b);
        }
        .modal-title { font-size: 16px; font-weight: 800; color: var(--ink); letter-spacing: .5px; }
        .modal-subtitle { font-size: 9px; color: var(--g); font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; margin-top: 4px; }

        .btn-close {
            background: transparent;
            border: 1px solid var(--b);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 11px;
            color: var(--ink4);
            font-family: var(--font);
            transition: all .25s;
        }
        .btn-close:hover { background: var(--bg); border-color: var(--b-gold); color: var(--ink); }

        .modal-body { padding: 24px 32px 32px; }

        .tag-title {
            display: inline-block;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--g);
            background: var(--gl);
            padding: 6px 14px;
            border-radius: 8px;
            margin: 20px 0 12px 0;
        }
        .tag-title:first-child { margin-top: 0; }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,.05);
        }
        .info-row:last-child { border-bottom: none; }
        .info-row b { color: var(--ink5); font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
        .info-row span { font-weight: 700; color: var(--ink); font-size: 14px; }

        .spin { animation: fa-spin 1s infinite linear; }
    </style>
</head>
<body>

<!-- MODAL: FICHA MAESTRA DE PERSONAL -->
<div class="modal-overlay" id="modal-person">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <div class="modal-title">Consulta de Seguridad</div>
                <div class="modal-subtitle">Inteligencia de Datos Hochschild</div>
            </div>
            <button class="btn-close" onclick="closeModal()">Cerrar</button>
        </div>
        <div class="modal-body" id="person-content">
            <!-- Cargado dinámicamente -->
        </div>
    </div>
</div>

<header class="header-main">
    <div class="brand">
        <img src="Assets Index/logo.png" alt="Logo" onerror="this.style.display='none';">
        <span class="brand-text">SITRAN MASTER MONITOR</span>
    </div>
    <div class="header-right">
        <div class="header-user">
            <div class="header-user-name"><?php echo strtoupper($_SESSION['usuario']); ?></div>
            <div class="header-user-role">Command Center</div>
        </div>
        <a href="panel.php" class="header-home"><i class="fa-solid fa-house-chimney"></i></a>
    </div>
</header>

<div class="search-section">
    <!-- BUSCADOR INDEPENDIENTE DE PERSONAL -->
    <div class="input-group">
        <label>Buscador de Personal (Base de Datos Central)</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-user-shield"></i>
            <input type="text" id="person-search" placeholder="Ingresar DNI o Apellidos para ficha tecnica..." onkeypress="if(event.keyCode==13) searchPerson()">
        </div>
    </div>

    <!-- FILTRO DE RADAR -->
    <div class="input-group">
        <label>Filtro de Radar Operativo (Transito Hoy)</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-radar"></i>
            <input type="text" id="radar-search" placeholder="Filtrar placa, conductor o contratista..." onkeyup="handleRadarSearch(event)">
        </div>
    </div>

    <div class="search-actions">
        <label>Calendario</label>
        <div class="search-actions-row">
            <input type="date" id="filter-date" value="<?= date('Y-m-d') ?>" class="filter-date" onchange="loadDashboard()">
            <button onclick="loadDashboard()" id="btn-refresh" class="btn-refresh">
                <i class="fa-solid fa-sync"></i> Actualizar
            </button>
        </div>
    </div>
</div>

<div class="container">
    <div class="sec-row"><span>Indicadores en Tiempo Real</span></div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Ingresos Hoy</div>
            <div class="kpi-value" id="kpi-in">0</div>
            <div class="kpi-icon"><i class="fa-solid fa-arrow-right-to-bracket"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Salidas Hoy</div>
            <div class="kpi-value" id="kpi-out">0</div>
            <div class="kpi-icon red"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Transito</div>
            <div class="kpi-value" id="kpi-total">0</div>
            <div class="kpi-icon gold"><i class="fa-solid fa-truck-fast"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Estado del Sistema</div>
            <div class="kpi-value" style="font-size:1.2rem; color:var(--green);">ONLINE</div>
            <div class="kpi-icon green"><i class="fa-solid fa-shield-halved"></i></div>
        </div>
    </div>

    <div class="sec-row"><span>Panel de Control</span></div>

    <div class="main-grid">
        <aside>
            <div class="content-box">
                <div class="box-header"><i class="fa-solid fa-chart-line"></i> Contratistas con mas Salidas</div>
                <div id="ranking-list"></div>
            </div>

            <div class="time-card">
                <div class="time-value" id="last-update">--:--:--</div>
                <div class="time-label">Hora del Servidor</div>
            </div>
        </aside>

        <section class="content-box">
            <div class="box-header"><i class="fa-solid fa-satellite"></i> Radar Maestro de Control de Accesos</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Movimiento</th>
                            <th>Unidad / Marca</th>
                            <th>Conductor / DNI</th>
                            <th>Empresa / Destino</th>
                            <th>Autorizacion</th>
                            <th>Operador</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr><td colspan="7" style="text-align:center; padding:100px; color:var(--ink5); font-size:13px; font-weight:500;">Sincronizando con estacion garita SITRAN...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script>
    let radarTimeout;

    // 1. BUSCADOR MAESTRO DE PERSONAL (Base de Datos Autónoma)
    function searchPerson() {
        const query = document.getElementById('person-search').value.trim();
        if(!query) return;

        fetch(`monitoreo.php?ajax=1&action=search_person&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const p = data.data;
                    document.getElementById('person-content').innerHTML = `
                        <span class="tag-title">Informacion Laboral</span>
                        <div class="info-row"><b>Nombre Completo:</b> <span>${p.nombres} ${p.apellidos}</span></div>
                        <div class="info-row"><b>DNI / ID:</b> <span>${p.dni}</span></div>
                        <div class="info-row"><b>Empresa Asignada:</b> <span>${p.empresa}</span></div>
                        <div class="info-row"><b>Cargo Registrado:</b> <span>${p.cargo}</span></div>

                        <span class="tag-title">Certificaciones de Conduccion</span>
                        <div class="info-row"><b>Nro. Licencia MTC:</b> <span>${p.nro_licencia || 'S/L'}</span></div>
                        <div class="info-row"><b>Categoria MTC:</b> <span>${p.categoria_mtc || '-'}</span></div>
                        <div class="info-row"><b>Vencimiento Licencia:</b> <span style="color:var(--red);">${p.d_vcto || '-'}</span></div>
                        <div class="info-row"><b>Autorizacion Mina:</b> <span>${p.categoria_mina || '-'}</span></div>
                    `;
                    document.getElementById('modal-person').style.display = 'flex';
                } else {
                    alert(data.message);
                }
            });
    }

    // 2. RADAR DE MOVIMIENTOS
    function handleRadarSearch(e) {
        clearTimeout(radarTimeout);
        radarTimeout = setTimeout(() => {
            loadDashboard(e.target.value);
        }, 500);
    }

    function loadDashboard(q = '') {
        const date = document.getElementById('filter-date').value;
        const btn = document.getElementById('btn-refresh');
        btn.querySelector('i').classList.add('spin');

        fetch(`monitoreo.php?ajax=1&fecha=${date}&q=${encodeURIComponent(q)}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('kpi-in').innerText = data.kpis.ingresos;
                document.getElementById('kpi-out').innerText = data.kpis.salidas;
                document.getElementById('kpi-total').innerText = data.kpis.total;

                let rankHtml = '';
                data.top_empresas.forEach(e => {
                    rankHtml += `
                        <div class="rank-item">
                            <span class="rank-name">${e.empresa}</span>
                            <span class="rank-badge">${e.total}</span>
                        </div>`;
                });
                document.getElementById('ranking-list').innerHTML = rankHtml || '<div style="padding:24px; color:var(--ink5); font-size:13px; text-align:center;">Sin registros hoy.</div>';

                let html = '';
                if(data.feed.length === 0) {
                    html = '<tr><td colspan="7" style="text-align:center; padding:60px; color:var(--ink5); font-weight:500; font-size:13px;">No se detecto transito bajo estos criterios.</td></tr>';
                } else {
                    data.feed.forEach(row => {
                        const badge = row.tipo_movimiento === 'INGRESO' ? 'bg-in' : 'bg-out';
                        html += `
                            <tr>
                                <td><strong style="color:var(--ink);">${row.fecha_fmt}</strong><div class="td-meta">${row.hora_fmt}</div></td>
                                <td><span class="badge-mov ${badge}">${row.tipo_movimiento}</span></td>
                                <td><span class="badge-placa">${row.placa_unidad}</span><div class="td-meta">${row.v_marca || '-'}</div></td>
                                <td><strong style="font-size:13px;">${row.nombre_conductor}</strong><div class="td-meta">DNI: ${row.dni_conductor}</div></td>
                                <td><strong>${row.empresa}</strong><div class="td-meta"><i class="fa-solid fa-location-dot" style="color:var(--g);"></i> ${row.destino || '-'}</div></td>
                                <td><span style="font-size:12px; color:var(--ink4); font-weight:500;">${row.autorizado_por || '-'}</span></td>
                                <td><span style="font-weight:700; color:var(--ink); font-size:12px;"><i class="fa-solid fa-user-check" style="color:var(--g); margin-right:4px;"></i>${row.operador_garita}</span></td>
                            </tr>`;
                    });
                }
                document.getElementById('table-body').innerHTML = html;
                document.getElementById('last-update').innerText = new Date().toLocaleTimeString();
            })
            .finally(() => btn.querySelector('i').classList.remove('spin'));
    }

    function closeModal() { document.getElementById('modal-person').style.display = 'none'; }
    window.onclick = (e) => { if(e.target == document.getElementById('modal-person')) closeModal(); }

    document.addEventListener('DOMContentLoaded', () => loadDashboard());
</script>
</body>
</html>
