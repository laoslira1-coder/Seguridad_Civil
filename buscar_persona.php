<?php
// ==============================================================================
// BUSCAR PERSONA Y LICENCIA - MOTOR TURBO OPTIMIZADO (SITRAN)
// ==============================================================================
error_reporting(0); // Evita que errores PHP rompan el formato JSON
header('Content-Type: application/json; charset=utf-8');

// 1. CONEXIÓN A BASE DE DATOS
require_once 'config.php';
// $conn ya está disponible desde config.php con charset utf8
date_default_timezone_set('America/Lima');

if (isset($_GET['dni'])) {
    
    // Limpieza estricta: Solo dejamos los números
    $dni = preg_replace('/[^0-9]/', '', $_GET['dni']); 
    
    if (strlen($dni) !== 8) {
        echo json_encode(['success' => false, 'msg' => 'DNI inválido']);
        exit;
    }

    // =========================================================
    // FASE 1: BÚSQUEDA LOCAL (TIEMPO DE RESPUESTA: < 0.1s | COSTO: 0)
    // =========================================================
    $sql_f = "SELECT * FROM fuerza_laboral WHERE dni = '$dni' LIMIT 1";
    $res_f = mysqli_query($conn, $sql_f);
    $persona = ($res_f && mysqli_num_rows($res_f) > 0) ? mysqli_fetch_assoc($res_f) : null;

    $sql_l = "SELECT * FROM detalles_conductor WHERE dni = '$dni' LIMIT 1";
    $res_l = mysqli_query($conn, $sql_l);
    $licencia = ($res_l && mysqli_num_rows($res_l) > 0) ? mysqli_fetch_assoc($res_l) : null;

    // Evaluamos qué información ya poseemos en nuestro disco duro
    $tiene_datos_basicos = ($persona && !empty($persona['nombres']) && $persona['nombres'] !== 'NO ENCONTRADO');
    
    $tiene_licencia = false;
    if ($licencia) {
        // MEJORA DE VELOCIDAD: Si sabemos que "NO TIENE", ya no consultamos la API.
        if ($licencia['nro_licencia'] === 'NO TIENE') {
            $tiene_licencia = true; // Damos el dato por validado localmente
        } elseif (!empty($licencia['nro_licencia']) && !in_array($licencia['f_revalidacion'], ['0000-00-00', '1998-01-01', '1999-01-01'])) {
            $tiene_licencia = true; // Tiene licencia válida guardada
        }
    }

    // Si ya tenemos todo (o sabemos que no tiene licencia), respondemos inmediatamente
    if ($tiene_datos_basicos && $tiene_licencia) {
        $licencia_out = null;
        if ($licencia['nro_licencia'] !== 'NO TIENE') {
            $licencia_out = [
                'dni' => $dni,
                'nro_licencia' => $licencia['nro_licencia'],
                'categoria_mtc' => $licencia['categoria_mtc'],
                'f_expedicion' => $licencia['f_expedicion'],     // YYYY-MM-DD
                'f_revalidacion' => $licencia['f_revalidacion'], // YYYY-MM-DD
                'restricciones' => $licencia['restricciones']
            ];
        }

        echo json_encode([
            'success' => true,
            'origen'  => 'LOCAL', // Confirmación visual de que no gastó tokens
            'nombre'  => trim($persona['nombres'] . " " . ($persona['apellidos'] ?? '')),
            'empresa' => $persona['empresa'] ?? "POR DEFINIR",
            'area'    => $persona['area'] ?? "-",
            'cargo'   => $persona['cargo'] ?? "VISITA",
            'licencia' => $licencia_out
        ]);
        exit;
    }
    
    // =========================================================
    // FASE 2: CONEXIÓN A FACTILIZA (MODO PARALELO OPTIMIZADO)
    // =========================================================
    $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MDQwMyIsImh0dHA6Ly9zY2hlbWFzLm1pY3Jvc29mdC5jb20vd3MvMjAwOC8wNi9pZGVudGl0eS9jbGFpbXMvcm9sZSI6ImNvbnN1bHRvciJ9.Qvy2TBxJ6NVkrGvolemAE9Aj_D-CyBrQqzhjKJXY1CQ'; 
    
    $nombres_db = $persona['nombres'] ?? '';
    $apellidos_db = $persona['apellidos'] ?? '-';
    $origen_respuesta = 'API_CONSULTADA';

    $httpCode_dni = null; $res_dni = null;
    $httpCode_lic = null; $res_lic = null;

    $mh = curl_multi_init();
    $ch_dni = null;
    $ch_lic = null;

    // A: Preparar Consulta RENIEC (DNI Info) con configuración profesional
    if (!$tiene_datos_basicos) {
        $ch_dni = curl_init();
        curl_setopt_array($ch_dni, [
            CURLOPT_URL => 'https://api.factiliza.com/v1/dni/info/' . $dni,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "", // ¡CLAVE PARA DESCOMPRIMIR!
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $token,
                "Accept: application/json"
            ],
        ]);
        curl_multi_add_handle($mh, $ch_dni);
    }

    // B: Preparar Consulta MTC (Licencia) con configuración profesional
    if (!$tiene_licencia) {
        $ch_lic = curl_init();
        curl_setopt_array($ch_lic, [
            CURLOPT_URL => 'https://api.factiliza.com/v1/licencia/info/' . $dni,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "", // ¡CLAVE PARA DESCOMPRIMIR!
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30, // Aumentado a 30s por la lentitud del MTC
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $token,
                "Accept: application/json"
            ],
        ]);
        curl_multi_add_handle($mh, $ch_lic);
    }

    // Ejecución Multihilo con control de CPU (Evita saturar el servidor local)
    if ($ch_dni || $ch_lic) {
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            } else {
                usleep(250); // Pausa de microsegundos para proteger el procesador
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
    }

    // --- Procesamiento de RENIEC ---
    if ($ch_dni) {
        $res_dni = curl_multi_getcontent($ch_dni);
        $httpCode_dni = curl_getinfo($ch_dni, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch_dni);

        if ($httpCode_dni == 200 && $res_dni) {
            $data_dni = json_decode($res_dni, true);
            $n = ''; $ap = '';
            
            // Extracción inteligente
            $origen_datos = $data_dni['data'] ?? $data_dni;
            
            if (isset($origen_datos['nombres'])) {
                $n = $origen_datos['nombres'];
                $ap = trim(($origen_datos['apellido_paterno'] ?? '') . ' ' . ($origen_datos['apellido_materno'] ?? ''));
            } elseif (isset($origen_datos['nombre_completo'])) {
                $n = $origen_datos['nombre_completo']; 
                $ap = '-';
            }
            
            if (!empty($n)) {
                $nombres_db = $n;
                $apellidos_db = $ap;
                $origen_respuesta = 'API_NOMBRES_OK';
            }
        }
        if (empty($nombres_db)) { $nombres_db = "NO ENCONTRADO"; }
    }

    // --- Procesamiento de MTC ---
    $nro_lic_db = $licencia['nro_licencia'] ?? 'NO TIENE';
    $cat_db = $licencia['categoria_mtc'] ?? '-';
    $f_exp_db = $licencia['f_expedicion'] ?? '1998-01-01';
    $f_vcto_db = $licencia['f_revalidacion'] ?? '1998-01-01';
    $res_db = $licencia['restricciones'] ?? '-';

    if ($ch_lic) {
        $res_lic = curl_multi_getcontent($ch_lic);
        $httpCode_lic = curl_getinfo($ch_lic, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch_lic);

        if ($httpCode_lic == 200 && $res_lic) {
            $data_lic = json_decode($res_lic, true);
            
            // Rescate de nombre desde Licencia (Si Reniec falla)
            if ($nombres_db === "NO ENCONTRADO" || empty($nombres_db)) {
                $origen_datos = $data_lic['data'] ?? $data_lic;
                if (!empty($origen_datos['nombre_completo'])) {
                    $nombres_db = $origen_datos['nombre_completo'];
                    $apellidos_db = '-';
                    $origen_respuesta = 'API_RESCATE_NOMBRE';
                }
            }

            // Búsqueda profunda de datos de Licencia
            $lic_info = $data_lic['licencia'] ?? $data_lic['data']['licencia'] ?? null;
            if (!$lic_info && isset($data_lic['data']['numero'])) $lic_info = $data_lic['data'];
            if (!$lic_info && isset($data_lic['numero'])) $lic_info = $data_lic;

            if ($lic_info && (!empty($lic_info['numero']) || !empty($lic_info['numero_licencia']))) {
                
                $nro_lic_db = $lic_info['numero'] ?? $lic_info['numero_licencia'] ?? $dni;
                $cat_db = $lic_info['categoria'] ?? $lic_info['clase_categoria'] ?? '-';
                $res_db = $lic_info['restricciones'] ?? '-';
                
                // Formateo de Fechas (DD/MM/YYYY -> YYYY-MM-DD)
                $venc = trim($lic_info['fecha_vencimiento'] ?? $lic_info['fecha_revalidacion'] ?? '');
                if (!empty($venc) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $venc, $m)) {
                    $f_vcto_db = "{$m[3]}-{$m[2]}-{$m[1]}";
                }

                $exp = trim($lic_info['fecha_expedicion'] ?? '');
                if (!empty($exp) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $exp, $m)) {
                    $f_exp_db = "{$m[3]}-{$m[2]}-{$m[1]}";
                }
                
                $origen_respuesta = 'API_LICENCIA_OK';
            } else {
                $nro_lic_db = 'NO TIENE';
                if ($origen_respuesta !== 'API_NOMBRES_OK') $origen_respuesta = 'API_SIN_LICENCIA';
            }
        } else {
            $nro_lic_db = 'NO TIENE';
            if ($origen_respuesta !== 'API_NOMBRES_OK') $origen_respuesta = 'API_FALLO_O_SIN_DATOS';
        }
    }

    curl_multi_close($mh);

    // =========================================================
    // FASE 3: ALMACENAMIENTO PERMANENTE (ACTUALIZA BD)
    // =========================================================

    // Si ni Reniec ni MTC devolvieron el nombre, desbloqueamos la UI manual.
    if ($nombres_db === "NO ENCONTRADO" || empty(trim($nombres_db))) {
        mysqli_query($conn, "DELETE FROM fuerza_laboral WHERE dni='$dni'");
        mysqli_query($conn, "DELETE FROM detalles_conductor WHERE dni='$dni'");
        
        echo json_encode([
            'success' => false, 
            'msg' => 'La API de Factiliza no tiene a esta persona. Por favor, registre los datos manualmente.'
        ]);
        exit;
    }
    
    // Guardar / Actualizar Fuerza Laboral
    if ($persona) {
        $stmt = $conn->prepare("UPDATE fuerza_laboral SET nombres=?, apellidos=? WHERE dni=?");
        $stmt->bind_param("sss", $nombres_db, $apellidos_db, $dni);
        $stmt->execute();
    } else {
        $emp = "POR DEFINIR";
        $cargo = "VISITA";
        $stmt = $conn->prepare("INSERT INTO fuerza_laboral (dni, nombres, apellidos, empresa, tipo_personal, area, cargo, estado_validacion) VALUES (?, ?, ?, ?, 'VISITA', '-', ?, 'ACTIVO')");
        $stmt->bind_param("sssss", $dni, $nombres_db, $apellidos_db, $emp, $cargo);
        $stmt->execute();
    }

    // Guardar / Actualizar Licencia
    if ($licencia) {
        $stmt = $conn->prepare("UPDATE detalles_conductor SET nro_licencia=?, categoria_mtc=?, f_expedicion=?, f_revalidacion=?, restricciones=? WHERE dni=?");
        $stmt->bind_param("ssssss", $nro_lic_db, $cat_db, $f_exp_db, $f_vcto_db, $res_db, $dni);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO detalles_conductor (dni, nro_licencia, categoria_mtc, f_expedicion, f_revalidacion, restricciones) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $dni, $nro_lic_db, $cat_db, $f_exp_db, $f_vcto_db, $res_db);
        $stmt->execute();
    }

    // Preparar respuesta limpia final
    $licencia_out = null;
    if ($nro_lic_db !== 'NO TIENE' && !in_array($f_vcto_db, ['1998-01-01', '1999-01-01'])) {
        $licencia_out = [
            'dni' => $dni,
            'nro_licencia' => $nro_lic_db,
            'categoria_mtc' => $cat_db,
            'f_expedicion' => $f_exp_db,
            'f_revalidacion' => $f_vcto_db,
            'restricciones' => $res_db
        ];
    }

    echo json_encode([
        'success' => true,
        'origen'  => $origen_respuesta,
        'nombre'  => trim($nombres_db . " " . ($apellidos_db !== '-' ? $apellidos_db : '')),
        'empresa' => $persona['empresa'] ?? "POR DEFINIR",
        'area'    => $persona['area'] ?? "-",
        'cargo'   => $persona['cargo'] ?? "VISITA",
        'licencia' => $licencia_out
    ]);
    exit;

} else {
    echo json_encode(['success' => false, 'msg' => 'Petición inválida']);
}
?>