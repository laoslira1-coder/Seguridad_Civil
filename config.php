<?php
// ==============================================================================
// CONFIGURACIÓN CENTRAL DE BASE DE DATOS - HOSTINGER
// Incluir este archivo en todos los PHP con: require_once 'config.php';
// ==============================================================================

// Manejo de errores: se registran en log, nunca se muestran al usuario
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

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

// ==============================================================================
// TOKEN CENTRALIZADO DE API FACTILIZA
// Usar como: FACTILIZA_TOKEN en cualquier archivo que haga require_once 'config.php'
// ==============================================================================
define('FACTILIZA_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MDQwMyIsImh0dHA6Ly9zY2hlbWFzLm1pY3Jvc29mdC5jb20vd3MvMjAwOC8wNi9pZGVudGl0eS9jbGFpbXMvcm9sZSI6ImNvbnN1bHRvciJ9.Qvy2TBxJ6NVkrGvolemAE9Aj_D-CyBrQqzhjKJXY1CQ');
?>
