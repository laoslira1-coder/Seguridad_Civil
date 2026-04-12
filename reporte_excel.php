<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

// 1. CONFIGURACIÓN DE HEADERS PARA EXCEL
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Reporte_SITRAN_" . date('Y-m-d_H-i') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// 2. CONEXIÓN A BASE DE DATOS
require_once 'config.php';
// $conn disponible desde config.php (Hostinger)
// 3. CONSULTA SQL
$sql = "SELECT 
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
        ORDER BY r.fecha_ingreso DESC"; 

$resultado = mysqli_query($conn, $sql);
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
                
                <td><?php echo utf8_decode($jefe_turno); ?></td>
                <td><?php echo utf8_decode($solicitante); ?></td>
                
                <td class="text-center"><?php echo utf8_decode($origen_final); ?></td>
                <td class="text-center"><?php echo utf8_decode($row['destino']); ?></td>
                
                <td class="text-center"><?php echo $row['tipo_vehiculo']; ?></td>
                <td class="text-center"><?php echo $row['marca']; ?></td>
                <td class="text-center"><?php echo $row['modelo']; ?></td>
                <td class="text-center"><?php echo $row['anio']; ?></td>
                <td class="text-center text-bold"><?php echo $row['placa_unidad']; ?></td>
                <td class="text-center"><?php echo $row['soat_vcto']; ?></td>
                <td><?php echo utf8_decode($row['empresa']); ?></td>
                
                <td class="text-center" style="mso-number-format:'\@';"><?php echo $row['dni_conductor']; ?></td>
                <td><?php echo utf8_decode($row['nombre_conductor']); ?></td>
                
                <td class="text-center"><?php echo $row['nro_licencia']; ?></td>
                <td class="text-center"><?php echo $row['categoria_mtc']; ?></td>
                <td class="text-center"><?php echo $row['f_revalidacion']; ?></td>
                <td class="text-center"><?php echo $row['categoria_mina']; ?></td>
                
                <td style="font-size: 10px;"><?php echo utf8_decode($lista_ac); ?></td>
                <td><?php echo utf8_decode($obs_limpia); ?></td>
                <td><?php echo $row['operador_garita']; ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

</body>
</html>