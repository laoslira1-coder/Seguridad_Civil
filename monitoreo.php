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
        $q = trim($_GET['q'] ?? '');
        if (empty($q)) { echo json_encode(['error' => 'Ingrese un término']); exit; }

        $q_like = '%' . $q . '%';
        $stmt_sp = $conn->prepare(
            "SELECT f.*, d.nro_licencia, d.categoria_mtc, d.f_revalidacion as d_vcto, d.categoria_mina
             FROM fuerza_laboral f
             LEFT JOIN detalles_conductor d ON f.dni = d.dni
             WHERE f.dni = ? OR f.nombres LIKE ? OR f.apellidos LIKE ?
             LIMIT 1"
        );
        $stmt_sp->bind_param("sss", $q, $q_like, $q_like);
        $stmt_sp->execute();
        $res_sp = $stmt_sp->get_result();
        $person = $res_sp->fetch_assoc();

        if ($person) {
            echo json_encode(['success' => true, 'data' => $person]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Persona no encontrada en la Base de Datos Maestra.']);
        }
        exit;
    }

    // --- ACCIÓN: DASHBOARD Y RADAR (Movimientos) ---
    $fecha_raw = $_GET['fecha'] ?? '';
    $fecha = (!empty($fecha_raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) ? $fecha_raw : date('Y-m-d');
    $search_radar = trim($_GET['q'] ?? '');

    // KPIs
    $stmt_kpis = $conn->prepare(
        "SELECT
            SUM(CASE WHEN tipo_movimiento = 'INGRESO' THEN 1 ELSE 0 END) as in_today,
            SUM(CASE WHEN tipo_movimiento = 'SALIDA' THEN 1 ELSE 0 END) as out_today
         FROM registros_garita WHERE DATE(fecha_ingreso) = ?"
    );
    $stmt_kpis->bind_param("s", $fecha);
    $stmt_kpis->execute();
    $res_kpis = $stmt_kpis->get_result()->fetch_assoc();

    // Ranking
    $stmt_top = $conn->prepare(
        "SELECT empresa, COUNT(*) as total FROM registros_garita
         WHERE tipo_movimiento = 'SALIDA' AND DATE(fecha_ingreso) = ?
         GROUP BY empresa ORDER BY total DESC LIMIT 5"
    );
    $stmt_top->bind_param("s", $fecha);
    $stmt_top->execute();
    $res_top = $stmt_top->get_result();
    $top_empresas = [];
    while ($row = $res_top->fetch_assoc()) { $top_empresas[] = $row; }

    // Radar — con o sin filtro de búsqueda
    if (!empty($search_radar)) {
        $search_like = '%' . $search_radar . '%';
        $stmt_feed = $conn->prepare(
            "SELECT r.*, v.marca as v_marca, v.tipo_vehiculo as v_tipo, d.nro_licencia as d_licencia
             FROM registros_garita r
             LEFT JOIN vehiculos v ON r.placa_unidad = v.placa
             LEFT JOIN detalles_conductor d ON r.dni_conductor = d.dni
             WHERE DATE(r.fecha_ingreso) = ?
               AND (r.placa_unidad LIKE ? OR r.nombre_conductor LIKE ? OR r.dni_conductor LIKE ?)
             ORDER BY r.fecha_ingreso DESC LIMIT 100"
        );
        $stmt_feed->bind_param("ssss", $fecha, $search_like, $search_like, $search_like);
    } else {
        $stmt_feed = $conn->prepare(
            "SELECT r.*, v.marca as v_marca, v.tipo_vehiculo as v_tipo, d.nro_licencia as d_licencia
             FROM registros_garita r
             LEFT JOIN vehiculos v ON r.placa_unidad = v.placa
             LEFT JOIN detalles_conductor d ON r.dni_conductor = d.dni
             WHERE DATE(r.fecha_ingreso) = ?
             ORDER BY r.fecha_ingreso DESC LIMIT 100"
        );
        $stmt_feed->bind_param("s", $fecha);
    }
    $stmt_feed->execute();
    $res_feed = $stmt_feed->get_result();
    $feed = [];
    while ($row = $res_feed->fetch_assoc()) {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Orbitron:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>
    <style>
        :root { 
            --gold: #c5a059; 
            --gold-dark: #a88746;
            --black: #000000; 
            --black-soft: #111111;
            --gray-dark: #1a1a1a;
            --white: #ffffff;
            --bg: #f2f2f2;
            --danger: #ff4d4d;
            --success: #00e676;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            margin: 0; 
            color: var(--black); 
            overflow-x: hidden; 
        }
        
        /* HEADER ESTILO PREMIUM NEGRO */
        .header-main { 
            background: var(--black); 
            padding: 15px 35px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 5px solid var(--gold); 
            color: var(--white); 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.5); 
        }
        
        .brand { display: flex; align-items: center; gap: 18px; }
        .brand img { height: 40px; }
        .brand-text { font-family: 'Orbitron'; color: var(--gold); font-weight: 700; font-size: 1.3rem; letter-spacing: 3px; }

        /* SECCIÓN DE BÚSQUEDA */
        .search-section {
            background: var(--black-soft);
            padding: 25px 35px;
            display: grid;
            grid-template-columns: 1fr 1fr 320px;
            gap: 25px;
            align-items: end;
            border-bottom: 2px solid var(--gray-dark);
        }

        .input-group { position: relative; }
        .input-group label { 
            display: block; 
            font-size: 10px; 
            color: var(--gold); 
            text-transform: uppercase; 
            font-weight: 800; 
            margin-bottom: 8px; 
            letter-spacing: 1.5px;
        }
        .input-wrapper { position: relative; }
        .input-wrapper input {
            width: 100%;
            box-sizing: border-box;
            background: var(--gray-dark);
            border: 1px solid rgba(197, 160, 89, 0.3);
            padding: 14px 14px 14px 48px;
            border-radius: 6px;
            color: white;
            font-size: 13px;
            outline: none;
            transition: all 0.3s;
        }
        .input-wrapper input:focus { 
            border-color: var(--gold); 
            background: #222;
            box-shadow: 0 0 15px rgba(197, 160, 89, 0.2);
        }
        .input-wrapper i { position: absolute; left: 18px; top: 16px; color: var(--gold); font-size: 16px; }

        .btn-refresh {
            background: var(--gold);
            color: var(--black);
            border: none;
            padding: 14px 25px;
            border-radius: 6px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }
        .btn-refresh:hover { background: var(--white); transform: scale(1.02); }

        .container { padding: 35px; max-width: 1700px; margin: 0 auto; }
        
        /* KPI CARDS */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 35px; }
        .kpi-card { 
            background: var(--white); 
            padding: 25px; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            gap: 25px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: 0.3s;
            border-bottom: 4px solid var(--black);
        }
        .kpi-card:hover { transform: translateY(-8px); border-bottom-color: var(--gold); }
        .kpi-icon-box { 
            width: 65px; 
            height: 65px; 
            background: var(--black); 
            color: var(--gold); 
            border-radius: 5px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 28px; 
            box-shadow: 4px 4px 0 var(--gold);
        }
        .kpi-text h3 { margin: 0; font-size: 11px; color: #777; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; }
        .kpi-text h1 { margin: 5px 0 0; font-size: 2.5rem; font-family: 'Orbitron'; font-weight: 700; color: var(--black); }

        /* LAYOUT PRINCIPAL */
        .main-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        @media (max-width: 1200px) { .main-grid { grid-template-columns: 1fr; } }

        .content-box { 
            background: var(--white); 
            border-radius: 4px; 
            overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
        }
        .box-header { 
            background: var(--black); 
            color: var(--white); 
            padding: 18px 25px; 
            font-family: 'Orbitron'; 
            font-size: 13px; 
            letter-spacing: 2px; 
            border-left: 6px solid var(--gold); 
            display: flex; 
            align-items: center; 
            gap: 12px;
            text-transform: uppercase;
        }

        /* TABLA RADAR */
        .table-container { overflow-x: auto; max-height: 700px; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th { 
            background: #fdfdfd; 
            padding: 18px; 
            font-size: 11px; 
            text-transform: uppercase; 
            text-align: left; 
            color: var(--black); 
            font-weight: 800;
            border-bottom: 2px solid #eee;
            position: sticky; top: 0; z-index: 10;
        }
        td { padding: 18px; font-size: 13px; border-bottom: 1px solid #f5f5f5; vertical-align: middle; }
        tr:hover td { background: #fafafa; }

        .badge-placa { 
            background: var(--black); 
            color: var(--gold); 
            font-family: 'Orbitron'; 
            padding: 5px 10px; 
            border-radius: 3px; 
            font-size: 12px; 
            font-weight: 600;
            border: 1px solid var(--gold);
        }
        .badge-mov { 
            font-weight: 800; 
            font-size: 10px; 
            padding: 6px 12px; 
            border-radius: 2px; 
            text-transform: uppercase; 
            letter-spacing: 1px;
        }
        .bg-in { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .bg-out { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* MODAL FICHA MAESTRA */
        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0, 0, 0, 0.95); 
            display: none; justify-content: center; align-items: center; 
            z-index: 2000; backdrop-filter: blur(10px); 
        }
        .modal-content { 
            background: var(--white); 
            width: 700px; 
            border-radius: 0; 
            border: 1px solid var(--gold); 
            box-shadow: 0 0 50px rgba(197, 160, 89, 0.3);
            overflow: hidden; 
            animation: slideIn 0.4s ease-out; 
        }
        @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header { 
            background: var(--black); color: var(--white); padding: 25px 35px; 
            border-bottom: 5px solid var(--gold); 
            display: flex; justify-content: space-between; align-items: center; 
        }
        .modal-body { padding: 35px; }
        .info-row { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .info-row b { color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .info-row span { font-weight: 700; color: var(--black); font-size: 14px; }
        .tag-title { 
            background: var(--black); 
            padding: 8px 15px; 
            font-size: 11px; 
            font-weight: 800; 
            color: var(--gold); 
            text-transform: uppercase; 
            margin: 25px 0 15px 0; 
            letter-spacing: 2px;
            display: inline-block;
        }
        
        .btn-close { 
            background: var(--gold); 
            border: none; 
            padding: 10px 20px; 
            font-weight: 800; 
            cursor: pointer; 
            font-size: 12px;
            text-transform: uppercase;
            transition: 0.3s;
        }
        .btn-close:hover { background: var(--white); }
        .spin { animation: fa-spin 1s infinite linear; }
    </style>
</head>
<body>

<!-- MODAL: FICHA MAESTRA DE PERSONAL -->
<div class="modal-overlay" id="modal-person">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2 style="margin:0; font-size:20px; font-family:'Orbitron'; letter-spacing:2px;">CONSULTA DE SEGURIDAD</h2>
                <small style="color:var(--gold); font-weight:800; letter-spacing:1px;">INTELIGENCIA DE DATOS HOCHSCHILD</small>
            </div>
            <button class="btn-close" onclick="closeModal()">CERRAR [X]</button>
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
    <div style="display:flex; align-items:center; gap:25px;">
        <div style="text-align:right;">
            <div style="font-size:14px; font-weight:800; color:var(--gold); letter-spacing:1px;"><?php echo strtoupper($_SESSION['usuario']); ?></div>
            <div style="font-size:10px; color:#666; font-weight:700;">COMMAND CENTER</div>
        </div>
        <a href="panel.php" style="color:var(--gold); font-size:24px; transition:0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--gold)'"><i class="fa-solid fa-house-chimney"></i></a>
    </div>
</header>

<div class="search-section">
    <!-- BUSCADOR INDEPENDIENTE DE PERSONAL -->
    <div class="input-group">
        <label>Buscador de Personal (Base de Datos Central)</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-user-shield"></i>
            <input type="text" id="person-search" placeholder="Ingresar DNI o Apellidos para ficha técnica..." onkeypress="if(event.keyCode==13) searchPerson()">
        </div>
    </div>

    <!-- FILTRO DE RADAR -->
    <div class="input-group">
        <label>Filtro de Radar Operativo (Transito Hoy)</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-radar"></i>
            <input type="text" id="radar-search" placeholder="Filtrar placa, conductor o contratista en tiempo real..." onkeyup="handleRadarSearch(event)">
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:8px;">
        <label style="font-size:10px; color:var(--gold); font-weight:800; letter-spacing:1px;">CALENDARIO</label>
        <div style="display:flex; gap:12px;">
            <input type="date" id="filter-date" value="<?= date('Y-m-d') ?>" style="background:var(--gray-dark); border:1px solid var(--gold); color:white; padding:12px; border-radius:4px; font-size:14px; outline:none; min-width:160px; box-sizing:border-box;" onchange="loadDashboard()">
            <button onclick="loadDashboard()" id="btn-refresh" class="btn-refresh">
                <i class="fa-solid fa-sync"></i> ACTUALIZAR
            </button>
        </div>
    </div>
</div>

<div class="container">
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon-box"><i class="fa-solid fa-arrow-right-to-bracket"></i></div>
            <div class="kpi-text"><h3>Ingresos</h3><h1 id="kpi-in">0</h1></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon-box" style="color:var(--danger)"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
            <div class="kpi-text"><h3>Salidas</h3><h1 id="kpi-out">0</h1></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon-box" style="color:var(--gold)"><i class="fa-solid fa-truck-fast"></i></div>
            <div class="kpi-text"><h3>Total Transito</h3><h1 id="kpi-total">0</h1></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon-box" style="color:var(--success)"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="kpi-text"><h3>Sincronía</h3><h1 style="font-size:16px; color:var(--success); font-weight:800; font-family:'Orbitron';">ONLINE</h1></div>
        </div>
    </div>

    <div class="main-grid">
        <aside>
            <div class="content-box">
                <div class="box-header"><i class="fa-solid fa-chart-line"></i> Contratistas con más Salidas</div>
                <div id="ranking-list" style="padding:25px;"></div>
            </div>
            
            <div class="content-box" style="margin-top:30px; background:var(--black); color:white;">
                <div style="padding:30px; text-align:center;">
                    <div style="font-family:'Orbitron'; color:var(--gold); font-size:2rem; font-weight:700;" id="last-update">--:--:--</div>
                    <div style="font-size:10px; letter-spacing:2px; margin-top:8px; opacity:0.6; font-weight:700;">HORA DEL SERVIDOR</div>
                </div>
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
                            <th>Autorización</th>
                            <th>Operador</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr><td colspan="7" style="text-align:center; padding:100px; color:#aaa; font-size:14px; font-weight:600;">SINCRONIZANDO CON ESTACIÓN GARITA SITRAN...</td></tr>
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
                        <span class="tag-title">Información Laboral</span>
                        <div class="info-row"><b>Nombre Completo:</b> <span>${p.nombres} ${p.apellidos}</span></div>
                        <div class="info-row"><b>DNI / ID:</b> <span>${p.dni}</span></div>
                        <div class="info-row"><b>Empresa Asignada:</b> <span>${p.empresa}</span></div>
                        <div class="info-row"><b>Cargo Registrado:</b> <span>${p.cargo}</span></div>
                        
                        <span class="tag-title">Certificaciones de Conducción</span>
                        <div class="info-row"><b>Nro. Licencia MTC:</b> <span>${p.nro_licencia || 'S/L'}</span></div>
                        <div class="info-row"><b>Categoría MTC:</b> <span>${p.categoria_mtc || '-'}</span></div>
                        <div class="info-row"><b>Vencimiento Licencia:</b> <span style="color:var(--danger);">${p.d_vcto || '-'}</span></div>
                        <div class="info-row"><b>Autorización Mina:</b> <span>${p.categoria_mina || '-'}</span></div>
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
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #eee;">
                            <span style="font-size:13px; font-weight:700; color:var(--black);">${e.empresa}</span>
                            <b style="background:var(--gold); color:var(--black); padding:4px 10px; border-radius:2px; font-size:12px; font-family:'Orbitron';">${e.total}</b>
                        </div>`;
                });
                document.getElementById('ranking-list').innerHTML = rankHtml || '<small style="color:#999;">Sin registros hoy.</small>';

                let html = '';
                if(data.feed.length === 0) {
                    html = '<tr><td colspan="7" style="text-align:center; padding:60px; color:#999; font-weight:600;">NO SE DETECTÓ TRÁNSITO BAJO ESTOS CRITERIOS.</td></tr>';
                } else {
                    data.feed.forEach(row => {
                        const badge = row.tipo_movimiento === 'INGRESO' ? 'bg-in' : 'bg-out';
                        html += `
                            <tr>
                                <td><b style="color:var(--black);">${row.fecha_fmt}</b><br><small style="color:#888; font-weight:700;">${row.hora_fmt}</small></td>
                                <td><span class="badge-mov ${badge}">${row.tipo_movimiento}</span></td>
                                <td><span class="badge-placa">${row.placa_unidad}</span><br><small style="color:#666; font-weight:600;">${row.v_marca || '-'}</small></td>
                                <td><b style="font-size:14px;">${row.nombre_conductor}</b><br><small style="color:#888; font-weight:700;">DNI: ${row.dni_conductor}</small></td>
                                <td><b>${row.empresa}</b><br><small style="font-weight:700; color:var(--gold-dark);"><i class="fa-solid fa-location-dot"></i> ${row.destino || '-'}</small></td>
                                <td><div style="font-size:11px; color:#555; font-weight:600;">${row.autorizado_por || '-'}</div></td>
                                <td><span style="font-weight:800; color:var(--black);"><i class="fa-solid fa-user-check"></i> ${row.operador_garita}</span></td>
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