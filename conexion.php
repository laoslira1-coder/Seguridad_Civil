<?php
$servidor = "localhost";
$usuario = "u480700204_hocsegcivil";
$password = "Mina_2026";
$base_datos = "u480700204_hocsegcivil";

// Creamos la conexión
$conexion = mysqli_connect($servidor, $usuario, $password, $base_datos);

// Verificamos la conexión
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>