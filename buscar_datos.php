<?php
// ==============================================================================
// BUSCAR DATOS (VEHÍCULOS Y SOAT) - PROTECCIÓN ESTRICTA DE TOKENS
// ==============================================================================
error_reporting(0); // Evitar que errores PHP rompan la respuesta JSON
header('Content-Type: application/json; charset=utf-8');

// 1. CONEXIÓN DIRECTA
require_once 'config.php';
// $conn ya está disponible desde config.php con charset utf8
date_default_timezone_set('America/Lima');

if (isset($_POST['placa']) && $_POST['tipo'] == 'vehiculo') {
    
    // Limpieza estricta de la placa (sin guiones ni espacios)
    $placa_input = strtoupper(trim($_POST['placa']));
    $placa_limpia = preg_replace('/[^A-Z0-9]/', '', $placa_input); 
    
    // =========================================================
    // FASE 1: BÚSQUEDA EN BASE DE DATOS LOCAL (COSTO: 0 TOKENS)
    // =========================================================
    $stmt = $conn->prepare("SELECT * FROM vehiculos WHERE REPLACE(REPLACE(placa, '-', ''), ' ', '') = ? LIMIT 1");
    $stmt->bind_param("s", $placa_limpia);
    $stmt->execute();
    $res = $stmt->get_result();
    $fila = ($res->num_rows > 0) ? $res->fetch_assoc() : null;

    // Evaluamos si tenemos la info completa localmente para NO llamar a la API
    $tiene_info_basica = ($fila && !empty($fila['marca']) && $fila['marca'] !== '-' && $fila['marca'] !== 'POR DEFINIR');
    
    // IGNORAMOS EL 1999-01-01 PARA OBLIGAR AL SISTEMA A CORREGIR LOS ERRORES PASADOS
    $tiene_soat = ($fila && !empty($fila['soat_vcto']) && $fila['soat_vcto'] !== '0000-00-00' && $fila['soat_vcto'] !== '1999-01-01' && $fila['soat_vcto'] !== '1998-01-01');

    if ($tiene_info_basica && $tiene_soat) {
        // ¡Tenemos todo localmente! Formatear fecha y devolver gratis.
        $soat_formateado = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fila['soat_vcto'])) 
                            ? date("d/m/Y", strtotime($fila['soat_vcto'])) 
                            : $fila['soat_vcto'];

        echo json_encode([
            'encontrado' => true,
            'origen' => 'LOCAL', // Cero gasto
            'placa_unidad' => $fila['placa'],
            'placa_remolque' => !empty($fila['placa_remolque']) ? $fila['placa_remolque'] : '-',
            'tipo_vehiculo' => $fila['tipo_vehiculo'],
            'marca' => $fila['marca'],
            'modelo' => $fila['modelo'],
            'color' => $fila['color'],
            'anio' => isset($fila['anio']) ? $fila['anio'] : '', 
            'empresa_transporte' => $fila['empresa_transporte'],
            'soat_vcto' => $soat_formateado
        ]);
        exit; // DETENEMOS EL SCRIPT PARA PROTEGER TOKENS
    }
    
    // =========================================================
    // FASE 2: SI FALTA INFO, CONSULTAR API FACTILIZA (PAGA)
    // =========================================================
    
    // --> TU TOKEN REAL <--
    $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MDQwMyIsImh0dHA6Ly9zY2hlbWFzLm1pY3Jvc29mdC5jb20vd3MvMjAwOC8wNi9pZGVudGl0eS9jbGFpbXMvcm9sZSI6ImNvbnN1bHRvciJ9.Qvy2TBxJ6NVkrGvolemAE9Aj_D-CyBrQqzhjKJXY1CQ'; 
    
    $marca = $fila['marca'] ?? 'POR DEFINIR';
    $modelo = $fila['modelo'] ?? '-';
    $color = $fila['color'] ?? '-';
    $tipo_v = $fila['tipo_vehiculo'] ?? 'POR DEFINIR';
    $emp_t = $fila['empresa_transporte'] ?? 'POR DEFINIR';
    
    // NUEVO ESCUDO: Usamos 1998 para diferenciar de los errores antiguos (1999)
    $soat_vcto_db = '1998-01-01'; 
    $soat_vcto_front = '';

    // 2.A: BUSCAR INFO DE PLACA (Si no la tenemos)
    if (!$tiene_info_basica) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.factiliza.com/v1/placa/info/' . $placa_limpia);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // PROTECCIÓN XAMPP AÑADIDA
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);     // PROTECCIÓN XAMPP AÑADIDA
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        $response_info = curl_exec($ch);
        $httpCode_info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode_info == 200 && $response_info) {
            $api_info = json_decode($response_info, true);
            if (isset($api_info['success']) && $api_info['success'] == true) {
                $veh = $api_info['data'];
                $marca = strtoupper($veh['marca'] ?? 'POR DEFINIR');
                $modelo = strtoupper($veh['modelo'] ?? '-');
                $color = strtoupper($veh['color'] ?? '-');
            }
        }
    }

    // 2.B: BUSCAR SOAT (Si no lo tenemos o si tenía fecha mala)
    if (!$tiene_soat) {
        $ch_s = curl_init();
        curl_setopt($ch_s, CURLOPT_URL, 'https://api.factiliza.com/v1/placa/soat/' . $placa_limpia);
        curl_setopt($ch_s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_s, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch_s, CURLOPT_SSL_VERIFYPEER, false); // PROTECCIÓN XAMPP AÑADIDA
        curl_setopt($ch_s, CURLOPT_SSL_VERIFYHOST, 0);     // PROTECCIÓN XAMPP AÑADIDA
        curl_setopt($ch_s, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        $res_soat_api = curl_exec($ch_s);
        $httpCode_soat = curl_getinfo($ch_s, CURLINFO_HTTP_CODE);
        curl_close($ch_s);

        if ($httpCode_soat == 200 && $res_soat_api) {
            $data_soat = json_decode($res_soat_api, true);
            
            // "MODO CAZADOR DE FECHAS" - Buscamos la fecha en TODAS las variantes posibles
            $venc = null;
            
            if (!$venc && isset($data_soat['data']) && is_array($data_soat['data'])) {
                $venc = $data_soat['data']['fecha_fin'] ?? $data_soat['data']['fecha_vencimiento'] ?? $data_soat['data']['vencimiento'] ?? null;
            }
            if (!$venc && isset($data_soat['data'][0]) && is_array($data_soat['data'][0])) {
                $venc = $data_soat['data'][0]['fecha_fin'] ?? $data_soat['data'][0]['fecha_vencimiento'] ?? null;
            }
            if (!$venc && isset($data_soat['soat']) && is_array($data_soat['soat'])) {
                $venc = $data_soat['soat']['fecha_fin'] ?? $data_soat['soat']['fecha_vencimiento'] ?? $data_soat['soat']['vencimiento'] ?? null;
            }
            if (!$venc && is_array($data_soat)) {
                $venc = $data_soat['fecha_fin'] ?? $data_soat['fecha_vencimiento'] ?? null;
            }
            
            if ($venc) {
                $soat_vcto_front = $venc;
                
                // Normalizador Inteligente de Fechas
                $venc_limpio = str_replace('/', '-', $venc);
                $p = explode('-', $venc_limpio);
                
                if (count($p) == 3) {
                    if (strlen($p[2]) == 4) { // Formato DD-MM-YYYY
                        $soat_vcto_db = $p[2] . "-" . $p[1] . "-" . $p[0];
                    } else { // Formato YYYY-MM-DD
                        $soat_vcto_db = $p[0] . "-" . $p[1] . "-" . $p[2];
                    }
                } else {
                    $soat_vcto_db = '1998-01-01'; // Falla de fecha corrupta
                }
            } else {
                // ESCUDO ANTI-FUGAS: API respondió éxito, pero sin SOAT
                $soat_vcto_db = '1998-01-01'; 
            }
        } else {
            $soat_vcto_db = '1998-01-01'; // Falla de conexión a API de SOAT
        }
    }

    // =========================================================
    // FASE 3: GUARDAR / ACTUALIZAR EN BASE DE DATOS (CACHÉ)
    // =========================================================
    if ($fila) {
        // Actualizamos el vehículo existente
        $stmt = $conn->prepare("UPDATE vehiculos SET marca=?, modelo=?, color=?, soat_vcto=? WHERE placa=?");
        $stmt->bind_param("sssss", $marca, $modelo, $color, $soat_vcto_db, $fila['placa']);
        $stmt->execute();
    } else {
        // Insertamos el vehículo nuevo
        $stmt = $conn->prepare("INSERT INTO vehiculos (placa, marca, modelo, color, tipo_vehiculo, empresa_transporte, soat_vcto) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $placa_input, $marca, $modelo, $color, $tipo_v, $emp_t, $soat_vcto_db);
        $stmt->execute();
    }

    // Preparar fecha para el Frontend
    if (empty($soat_vcto_front) && $soat_vcto_db !== '1998-01-01' && $soat_vcto_db !== '1999-01-01') {
        $soat_vcto_front = date("d/m/Y", strtotime($soat_vcto_db));
    } elseif ($soat_vcto_db === '1998-01-01' || $soat_vcto_db === '1999-01-01') {
        $soat_vcto_front = '01/01/1999'; // Esto activará el recuadro ROJO en tu frontend
    }

    // Devolver al Frontend
    echo json_encode([
        'encontrado' => true, 
        'origen' => 'API_GUARDADO', 
        'placa_unidad' => $fila['placa'] ?? $placa_input, 
        'placa_remolque' => $fila['placa_remolque'] ?? '-',
        'tipo_vehiculo' => $tipo_v, 
        'marca' => $marca,
        'modelo' => $modelo,
        'color' => $color,
        'anio' => $fila['anio'] ?? '', 
        'empresa_transporte' => $emp_t, 
        'soat_vcto' => $soat_vcto_front,
        '_debug_soat_api' => isset($res_soat_api) ? json_decode($res_soat_api, true) : 'No consultada' // Diagnóstico secreto
    ]);
    exit;

} else {
    echo json_encode(['encontrado' => false, 'error' => 'Petición inválida']);
}
?>