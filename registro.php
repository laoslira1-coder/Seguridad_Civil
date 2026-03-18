<?php
// 1. CONEXIÓN A BASE DE DATOS
session_start();
require_once 'config.php';
require_once 'security.php';
$conexion = $conn;

$mensaje = "";

// 2. LÓGICA DE REGISTRO BLINDADA
if (isset($_POST['registrar'])) {
    csrf_validate();
    // Captura de datos (Ya no necesitamos escape manual aquí)
    $nombre_real    = $_POST['nombre_real'];
    $usuario_nuevo  = $_POST['usuario'];
    $cargo          = $_POST['cargo'];
    $password_new   = $_POST['password'];
    $password_conf  = $_POST['confirm_password'];

    // 1. VALIDACIÓN DE INTEGRIDAD (Lo que pondremos en la Diapo)
    if (empty($nombre_real) || empty($usuario_nuevo)) {
        $mensaje = "<div class='alert error'>⚠️ Campos obligatorios vacíos</div>";
    } elseif ($password_new !== $password_conf) {
        $mensaje = "<div class='alert error'>❌ Las contraseñas no coinciden</div>";
    } else {
        // 2. PREVENCIÓN: Sentencias Preparadas para verificar usuario
        $stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
        $stmt_check->bind_param("s", $usuario_nuevo);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $mensaje = "<div class='alert error'>⚠️ El usuario ya existe</div>";
        } else {
            // 3. CRIPTOGRAFÍA: Hash BCRYPT (Lo que dice tu diapositiva)
            $password_hashed = password_hash($password_new, PASSWORD_BCRYPT);

            // 4. INSERCIÓN SEGURA con Prepared Statements
            $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, nombre_usuario, password, cargo_real, rol) VALUES (?, ?, ?, ?, 'colaborador')");
            $stmt_insert->bind_param("ssss", $nombre_real, $usuario_nuevo, $password_hashed, $cargo);
            
            if ($stmt_insert->execute()) {
                $mensaje = "<div class='alert success'>✅ Registro exitoso. <a href='index.php'>Iniciar Sesión</a></div>";
            } else {
                $mensaje = "<div class='alert error'>❌ Error en el servidor. Inténtelo más tarde.</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | Seguridad Civil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* --- ESTÉTICA RECTANGULAR CORPORATIVA (MISMOS ESTILOS DEL LOGIN) --- */
        :root {
            --h-gold: #c5a059;
            --h-gold-dark: #9e7d3e;
            --h-dark: #121212;
            --white: #ffffff;
            --radius-rect: 8px; 
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--h-dark);
            /* Fondo HD Claro (Mismo que login) */
            background-image: linear-gradient(rgba(18, 18, 18, 0.1), rgba(18, 18, 18, 0.2)), url('index/inmaculada.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(10px);
            border-radius: 12px; /* Rectangular suavizado */
            border-top: 5px solid var(--h-gold);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
            width: 90%;
            max-width: 480px; /* Un poco más ancho para el formulario */
            text-align: center;
            padding: 40px 35px;
            animation: fadeIn 0.5s ease-out;
            margin: 20px 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* LOGOS PEQUEÑOS (Header) */
        .logos-mini {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        .logos-mini img { height: 40px; width: auto; }

        h2 { 
            color: var(--h-dark);
            font-size: 18px;
            margin-bottom: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        p.subtitle {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 25px;
        }

        /* INPUTS RECTANGULARES */
        .input-group { width: 100%; margin-bottom: 15px; text-align: left; position: relative; }
        
        .icon-left {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--h-gold);
            font-size: 14px;
            z-index: 10;
        }

        input, select {
            width: 100%;
            padding: 14px 15px 14px 40px; /* Espacio para icono */
            border: 1px solid #d1d5db;
            border-radius: var(--radius-rect); 
            box-sizing: border-box;
            background-color: #ffffff;
            font-size: 13px;
            color: var(--h-dark);
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            transition: all 0.2s;
        }

        input:focus, select:focus {
            border-color: var(--h-gold);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.15);
            outline: none;
        }

        /* BOTÓN RECTANGULAR */
        button {
            width: 100%;
            padding: 15px;
            background-color: var(--h-dark);
            color: var(--white);
            border: none;
            border-radius: var(--radius-rect);
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-top: 10px;
        }

        button:hover {
            background-color: #2a2a2a;
            transform: translateY(-1px);
            border-bottom: 3px solid var(--h-gold);
        }

        .back-link {
            display: block;
            margin-top: 20px;
            color: #64748b;
            font-size: 12px;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover { color: var(--h-gold); }

        /* MENSAJES */
        .alert {
            width: 100%; box-sizing: border-box; margin: 0 0 20px 0; padding: 12px;
            border-radius: 6px; font-size: 13px; font-weight: 600; text-align: center;
        }
        .error { background-color: #fee2e2; color: #b91c1c; border-left: 4px solid #ef4444; }
        .success { background-color: #dcfce7; color: #15803d; border-left: 4px solid #22c55e; }
        .success a { color: #15803d; text-decoration: underline; font-weight: 800; }

        .footer {
            margin-top: 20px;
            font-size: 10px;
            color: #ffffff;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="logos-mini">
        <img src="Assets Index/Hochscild_logo3.png" alt="Hochschild">
        <img src="Assets Index/seguridadcivil.png" alt="Seguridad Civil">
    </div>

    <h2>Crear Cuenta</h2>
    <p class="subtitle">Complete sus datos para solicitar acceso</p>

    <?php echo $mensaje; ?>

    <form method="POST" action="">
        <?php echo csrf_field(); ?>
        <div class="input-group">
            <i class="fa-solid fa-id-card icon-left"></i>
            <input type="text" name="nombre_real" placeholder="Nombre Completo (Ej: Juan Perez)" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-user icon-left"></i>
            <input type="text" name="usuario" placeholder="Usuario / Correo Corporativo" required autocomplete="off">
        </div>

        <div class="input-group">
            <i class="fa-solid fa-briefcase icon-left"></i>
            <input type="text" name="cargo" placeholder="Cargo " required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" name="password" placeholder="Contraseña" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" name="confirm_password" placeholder="Confirmar Contraseña" required>
        </div>

        <button type="submit" name="registrar">Registrar Usuario</button>
    </form>

    <a href="index.php" class="back-link">← Volver al inicio de sesión</a>
</div>

<div class="footer">
    © 2026 Seguridad Patrimonial • Hochschild Mining
</div>

</body>
</html>