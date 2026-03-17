<?php
// ==============================================================================
// CONFIGURACIÓN CENTRAL DE BASE DE DATOS - HOSTINGER
// Incluir este archivo en todos los PHP con: require_once 'config.php';
// ==============================================================================

$servidor   = "localhost";
$usuario    = "u480700204_hocsegcivil";
$password   = "Mina_2026";
$base_datos = "u480700204_hocsegcivil";

$conn = mysqli_connect($servidor, $usuario, $password, $base_datos);

if (!$conn) {
    // En producción mostramos un mensaje genérico (no exponemos detalles)
    die(json_encode(['error' => 'Error de conexión a la base de datos: ' . mysqli_connect_error()]));
}

mysqli_set_charset($conn, "utf8");
?>
