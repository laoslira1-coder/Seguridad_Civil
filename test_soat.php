<?php
// Archivo temporal para ver qué devuelve Factiliza
header('Content-Type: application/json; charset=utf-8');

// Obtenemos la placa de la URL, por defecto probaremos con M2Q834
$placa = isset($_GET['placa']) ? $_GET['placa'] : 'M2Q834';
$token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MDQwMyIsImh0dHA6Ly9zY2hlbWFzLm1pY3Jvc29mdC5jb20vd3MvMjAwOC8wNi9pZGVudGl0eS9jbGFpbXMvcm9sZSI6ImNvbnN1bHRvciJ9.Qvy2TBxJ6NVkrGvolemAE9Aj_D-CyBrQqzhjKJXY1CQ';

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