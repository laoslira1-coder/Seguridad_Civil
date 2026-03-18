<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

require_once 'config.php';

// Si no se enviaron fechas, mostrar formulario de selección
if (empty($_GET['fecha_desde']) || empty($_GET['fecha_hasta'])) {
    $hoy = date('Y-m-d');
    $primer_dia_mes = date('Y-m-01');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Exportar Reporte Excel - SITRAN</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1a1c1e; color: #e0e0e0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #2a2c2e; border: 1px solid #c5a059; border-radius: 8px; padding: 32px 40px; min-width: 360px; text-align: center; }
        h2 { color: #c5a059; margin-bottom: 8px; }
        p { color: #aaa; font-size: 13px; margin-bottom: 24px; }
        label { display: block; text-align: left; font-size: 13px; color: #ccc; margin-bottom: 4px; margin-top: 14px; }
        input[type=date] { width: 100%; padding: 8px 10px; border-radius: 4px; border: 1px solid #555; background: #1a1c1e; color: #fff; font-size: 14px; box-sizing: border-box; }
        button { margin-top: 24px; width: 100%; padding: 10px; background: #c5a059; color: #1a1c1e; font-weight: bold; font-size: 15px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #d4b06a; }
        .back { display: block; margin-top: 14px; font-size: 12px; color: #888; text-decoration: none; }
        .back:hover { color: #c5a059; }
    </style>
</head>
<body>
<div class="card">
    <h2>Exportar a Excel</h2>
    <p>Selecciona el rango de fechas para el reporte.</p>
    <form method="GET">
        <label>Fecha desde</label>
        <input type="date" name="fecha_desde" value="<?php echo $primer_dia_mes; ?>" required>
        <label>Fecha hasta</label>
        <input type="date" name="fecha_hasta" value="<?php echo $hoy; ?>" required>
        <button type="submit">Descargar Excel</button>
    </form>
    <a class="back" href="panel.php">← Volver al panel</a>
</div>
</body>
</html>
<?php
    exit();
}

// Validar y sanitizar fechas
$fecha_desde = date('Y-m-d', strtotime($_GET['fecha_desde']));
$fecha_hasta = date('Y-m-d', strtotime($_GET['fecha_hasta']));

// HEADERS PARA DESCARGA EXCEL
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Reporte_SITRAN_{$fecha_desde}_{$fecha_hasta}.xls");
header("Pragma: no-cache");
header("Expires: 0");

// CONSULTA SQL CON FILTRO DE FECHAS
$stmt = mysqli_prepare($conn, "SELECT
            r.id,
            r.fecha_ingreso,
            r.tipo_movimiento,
            r.autorizado_por,
            r.placa_unidad,
            r.empresa,
            r.dni_conductor,
            r.nombre_conductor,
            r.acompanante_1,
            r.acompanante_2,
            r.acompanante_3,
            r.acompanante_4,
            r.destino,
            r.anfitrion,
            r.motivo,
            r.observaciones,
            r.operador_garita,
            v.tipo_vehiculo,
            v.marca,
            v.modelo,
            v.anio,
            v.soat_vcto,
            d.nro_licencia,
            d.categoria_mtc,
            d.f_revalidacion,
            d.categoria_mina
        FROM registros_garita r
        LEFT JOIN vehiculos v ON r.placa_unidad = v.placa
        LEFT JOIN detalles_conductor d ON r.dni_conductor = d.dni
        WHERE DATE(r.fecha_ingreso) BETWEEN ? AND ?
        ORDER BY r.fecha_ingreso DESC");
mysqli_stmt_bind_param($stmt, 'ss', $fecha_desde, $fecha_hasta);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
        th { background-color: #1a1c1e; color: #c5a059; border: 1px solid #000; padding: 8px; text-align: center; text-transform: uppercase; }
        td { border: 1px solid #ccc; padding: 5px; vertical-align: middle; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .bg-green { background-color: #dcfce7; color: #166534; }
        .bg-red { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<table>
    <thead>
        <tr>
            <th colspan="24" style="background-color: #fff; color: #000; border: none; font-size: 16px;">
                REPORTE GENERAL DE CONTROL DE ACCESOS - SITRAN
            </th>
        </tr>
        <tr>
            <!-- ORDEN SOLICITADO -->
            <th>N°</th>
            <th>FECHA</th>
            <th>HORA</th>
            <th>INGRESO O SALIDA</th>
            <th>JEFE DE SEGURIDAD</th>
            <th>AUTORIZADO POR</th>
            
            <th>ORIGEN</th>
            <th>DESTINO</th>
            
            <th>TIPO VEHICULO</th> <!-- VEHICULO/TIPO -->
            <th>MARCA</th>
            <th>MODELO</th>
            <th>AÑO</th>
            <th>PLACA</th>
            <th>SOAT</th>
            <th>EMPRESA</th>
            
            <th>DNI</th>
            <th>CONDUCTOR</th>
            <th>LICENCIA MTC</th>
            <th>CAT MTC</th>
            <th>VENC. LICENCIA</th>
            <th>CAT MINA</th>
            
            <th>ACOMPAÑANTES</th>
            <th>OBSERVACIONES</th>
            <th>OPERADOR</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $i = 1;
        while ($row = mysqli_fetch_assoc($resultado)) { 
            // 1. FECHA Y HORA
            $fecha_solo = date('d/m/Y', strtotime($row['fecha_ingreso']));
            $hora_solo  = date('H:i:s', strtotime($row['fecha_ingreso']));

            // 2. ESTILO MOVIMIENTO
            $estilo_mov = ($row['tipo_movimiento'] == 'INGRESO') ? 'bg-green' : 'bg-red';
            
            // 3. SEPARAR JEFE Y SOLICITANTE
            $auth_full = $row['autorizado_por'];
            $jefe_turno = "";
            $solicitante = "";

            if (strpos($auth_full, '(Solic:') !== false) {
                $parts = explode('(Solic:', $auth_full);
                $jefe_turno = trim($parts[0]);
                $solicitante = trim(str_replace(')', '', $parts[1]));
            } else {
                $jefe_turno = $auth_full; 
                $solicitante = "-";
            }

            // 4. LÓGICA ORIGEN (Extraer de observaciones)
            $origen_final = "";
            $obs_limpia = $row['observaciones'];
            
            // Buscamos si existe "ORIGEN: ... ." al inicio de la observación
            if (preg_match('/^ORIGEN:\s*(.*?)\.\s*/', $row['observaciones'], $matches)) {
                $origen_final = $matches[1]; // Lo que capturó el paréntesis
                // Quitamos el texto de origen de la observación para no repetirlo
                $obs_limpia = str_replace($matches[0], '', $row['observaciones']);
            } else {
                // Si no se encuentra patrón, asumimos lógica por defecto
                if ($row['tipo_movimiento'] == 'SALIDA') {
                    $origen_final = 'UM INMACULADA';
                } else {
                    $origen_final = 'EXTERNO / NO ESPECIFICADO';
                }
            }

            // 5. ACOMPAÑANTES
            $acompanantes = [];
            if($row['acompanante_1'] && $row['acompanante_1'] != 'NINGUNO') $acompanantes[] = $row['acompanante_1'];
            if($row['acompanante_2'] && $row['acompanante_2'] != 'NINGUNO') $acompanantes[] = $row['acompanante_2'];
            if($row['acompanante_3'] && $row['acompanante_3'] != 'NINGUNO') $acompanantes[] = $row['acompanante_3'];
            if($row['acompanante_4'] && $row['acompanante_4'] != 'NINGUNO') $acompanantes[] = $row['acompanante_4'];
            $lista_ac = implode(" // ", $acompanantes);
        ?>
            <tr>
                <td class="text-center"><?php echo $i++; ?></td>
                
                <td class="text-center"><?php echo $fecha_solo; ?></td>
                <td class="text-center"><?php echo $hora_solo; ?></td>
                <td class="text-center text-bold <?php echo $estilo_mov; ?>"><?php echo $row['tipo_movimiento']; ?></td>
                
                <td><?php echo mb_convert_encoding($jefe_turno, 'ISO-8859-1', 'UTF-8'); ?></td>
                <td><?php echo mb_convert_encoding($solicitante, 'ISO-8859-1', 'UTF-8'); ?></td>

                <td class="text-center"><?php echo mb_convert_encoding($origen_final, 'ISO-8859-1', 'UTF-8'); ?></td>
                <td class="text-center"><?php echo mb_convert_encoding($row['destino'], 'ISO-8859-1', 'UTF-8'); ?></td>

                <td class="text-center"><?php echo $row['tipo_vehiculo']; ?></td>
                <td class="text-center"><?php echo $row['marca']; ?></td>
                <td class="text-center"><?php echo $row['modelo']; ?></td>
                <td class="text-center"><?php echo $row['anio']; ?></td>
                <td class="text-center text-bold"><?php echo $row['placa_unidad']; ?></td>
                <td class="text-center"><?php echo $row['soat_vcto']; ?></td>
                <td><?php echo mb_convert_encoding($row['empresa'], 'ISO-8859-1', 'UTF-8'); ?></td>

                <td class="text-center" style="mso-number-format:'\@';"><?php echo $row['dni_conductor']; ?></td>
                <td><?php echo mb_convert_encoding($row['nombre_conductor'], 'ISO-8859-1', 'UTF-8'); ?></td>

                <td class="text-center"><?php echo $row['nro_licencia']; ?></td>
                <td class="text-center"><?php echo $row['categoria_mtc']; ?></td>
                <td class="text-center"><?php echo $row['f_revalidacion']; ?></td>
                <td class="text-center"><?php echo $row['categoria_mina']; ?></td>

                <td style="font-size: 10px;"><?php echo mb_convert_encoding($lista_ac, 'ISO-8859-1', 'UTF-8'); ?></td>
                <td><?php echo mb_convert_encoding($obs_limpia, 'ISO-8859-1', 'UTF-8'); ?></td>
                <td><?php echo $row['operador_garita']; ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

</body>
</html>