<?php
function obtenerDatosVehiculo($placa, $conn) {
    // 1. Limpieza estricta de input (quitamos guiones y espacios)
    $placa_input = strtoupper(trim($placa));
    $placa_limpia = preg_replace('/[^A-Z0-9]/', '', $placa_input);

    // 2. Intentar buscar en TU base de datos primero (Caché local)
    // Comparamos la placa limpia con la placa de la BD (ignorando también sus guiones y espacios)
    $sql = "SELECT marca, modelo, color FROM vehiculos WHERE REPLACE(REPLACE(placa, '-', ''), ' ', '') = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $placa_limpia);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Retorna datos locales si ya existen
        return json_encode($result->fetch_assoc());
    }

    // 3. Si no existe en tu BD local, consultar API Externa
    // ===> 1. TU TOKEN DE FACTILIZA <===
    $token = FACTILIZA_TOKEN;
    
    // ===> 2. URL DE LA API DE FACTILIZA <===
    $url = 'https://api.factiliza.com/v1/placa/info/' . $placa_limpia; 

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $api_response = json_decode($response, true);
        
        // ===> 3. LECTURA EXACTA DE FACTILIZA <===
        // Factiliza envuelve la info en "success" => true y dentro del objeto "data"
        if (isset($api_response['success']) && $api_response['success'] === true && isset($api_response['data'])) {
            
            $vehiculo = $api_response['data'];
            
            // Guardar en tu DB para futuras consultas (Optimización)
            $insert = "INSERT INTO vehiculos (placa, marca, modelo, color) VALUES (?, ?, ?, ?)";
            $stmt_ins = $conn->prepare($insert);
            
            // Extraemos los datos del bloque "data" de la respuesta
            $marca = isset($vehiculo['marca']) ? strtoupper($vehiculo['marca']) : '-';
            $modelo = isset($vehiculo['modelo']) ? strtoupper($vehiculo['modelo']) : '-';
            $color = isset($vehiculo['color']) ? strtoupper($vehiculo['color']) : '-';
            
            $stmt_ins->bind_param("ssss", 
                $placa_input, 
                $marca, 
                $modelo, 
                $color
            );
            $stmt_ins->execute();
        }

        return $response; // Devolvemos el JSON de la API al frontend
    } else {
        return json_encode(['error' => 'Vehículo no encontrado o error de API', 'http_code' => $httpCode, 'response' => $response]);
    }
}

// =================================================================
// ⬇️ BLOQUE DE PRUEBA DIRECTA ⬇️
// =================================================================
// Este bloque te permite probar el archivo directamente desde tu navegador
// Escribiendo en la URL: http://localhost/tu_proyecto/consulta_placa.php?placa=ABC123

if (isset($_GET['placa'])) {
    // 1. Conexión a base de datos
    require_once 'config.php';
    $conn_test = $conn;
    mysqli_set_charset($conn_test, "utf8");
    
    // 2. Forzamos la respuesta como texto JSON para que sea legible
    header('Content-Type: application/json; charset=utf-8');
    
    // 3. Ejecutamos la función con la placa que escribas en la URL
    echo obtenerDatosVehiculo($_GET['placa'], $conn_test);
    
    // 4. Cerramos la conexión
    mysqli_close($conn_test);
}
?>