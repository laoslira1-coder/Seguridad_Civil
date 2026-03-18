<?php
// ==============================================================================
// ARCHIVO DE PRUEBA SOAT - PROTEGIDO CON SESIÓN
// ==============================================================================
session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Acceso denegado. Inicie sesión primero.']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

// Obtenemos la placa de la URL, por defecto probaremos con M2Q834
$placa = isset($_GET['placa']) ? $_GET['placa'] : 'M2Q834';
$token = FACTILIZA_TOKEN;

$url = 'https://api.factiliza.com/v1/placa/soat/' . $placa;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>