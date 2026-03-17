<?php
session_start();
session_destroy(); // Borra la memoria del usuario
header("Location: index.php"); // Regresa al login
exit();
?>