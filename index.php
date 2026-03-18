<?php
// 1. LÓGICA DE CONEXIÓN Y SESIÓN
session_start();
require_once 'config.php';
require_once 'security.php';
$conexion = $conn;

$mensaje = "";
if (isset($_POST['ingresar'])) {
    csrf_validate();

    $usuario_ingresado = trim($_POST['usuario']);
    $password_ingresada = $_POST['password'];

    $stmt = $conexion->prepare("SELECT password FROM usuarios WHERE nombre_usuario = ? LIMIT 1");
    $stmt->bind_param("s", $usuario_ingresado);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        if (password_verify($password_ingresada, $fila['password'])) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = $usuario_ingresado;
            header("Location: panel.php");
            exit();
        }
    }
    $mensaje = "<div class='alert error'>❌ Credenciales incorrectas</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | Seguridad Civil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>
    
    <style>
        /* --- DISEÑO CORPORATIVO RECTANGULAR --- */
        :root {
            --h-gold: #c5a059;
            --h-gold-dark: #9e7d3e;
            --h-dark: #121212;
            --h-gray: #1e1e1e;
            --white: #ffffff;
            --text-muted: #64748b;
            /* Radio pequeño para estética rectangular moderna */
            --radius-rect: 8px; 
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--h-dark);
            
            /* Fondo HD Claro */
            background-image: linear-gradient(rgba(18, 18, 18, 0.1), rgba(18, 18, 18, 0.2)), url('index/inmaculada.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        /* RELOJ SUPERIOR (Rectangular) */
        .top-clock {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            background: rgba(0,0,0,0.5);
            padding: 8px 16px;
            border-radius: 4px; /* Rectangular */
            border-left: 3px solid var(--h-gold);
            letter-spacing: 1px;
            backdrop-filter: blur(4px);
        }

        /* TARJETA DE ACCESO (Estructura sólida) */
        .login-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(10px);
            
            /* Bordes rectangulares con suavizado mínimo */
            border-radius: 12px; 
            border-top: 5px solid var(--h-gold);
            
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
            width: 90%;
            max-width: 420px;
            text-align: center;
            padding: 45px 35px;
            animation: fadeIn 0.5s ease-out;
            position: relative;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* LOGOS */
        .logos-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo-img {
            height: 80px; 
            width: auto;
            object-fit: contain;
        }

        .divider {
            width: 1px;
            height: 50px;
            background-color: #cbd5e1;
        }

        h2 { 
            color: var(--h-dark);
            font-size: 14px;
            margin-bottom: 25px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* INPUTS RECTANGULARES */
        .input-group { width: 100%; margin-bottom: 18px; text-align: left; position: relative; }
        
        .icon-left {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--h-gold);
            font-size: 16px;
            z-index: 10;
        }

        .icon-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            z-index: 10;
            transition: 0.2s;
        }
        .icon-toggle:hover { color: var(--h-dark); }

        input {
            width: 100%;
            padding: 14px 40px 14px 45px;
            border: 1px solid #d1d5db;
            
            /* Rectangular con radio suave */
            border-radius: var(--radius-rect); 
            
            box-sizing: border-box;
            background-color: #ffffff;
            font-size: 14px;
            color: var(--h-dark);
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            transition: all 0.2s;
        }

        input:focus {
            border-color: var(--h-gold);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.15);
            outline: none;
        }

        /* FILA DE OPCIONES */
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 13px;
            color: #475569;
        }

        .remember-box {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .remember-box input {
            width: 15px; height: 15px; margin: 0;
            accent-color: var(--h-gold);
            border-radius: 3px; /* Checkbox cuadrado */
        }

        /* Enlace de Registro (Rectangular Style) */
        .register-link {
            color: var(--h-gold-dark);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            transition: 0.2s;
            border-bottom: 1px solid transparent;
        }
        .register-link:hover { 
            color: var(--h-dark); 
            border-bottom: 1px solid var(--h-dark);
        }

        /* BOTÓN RECTANGULAR */
        button {
            width: 100%;
            padding: 15px;
            background-color: var(--h-dark);
            color: var(--white);
            border: none;
            
            /* Rectangular */
            border-radius: var(--radius-rect);
            
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        button:hover {
            background-color: #2a2a2a;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
            border-bottom: 3px solid var(--h-gold);
        }

        /* MENSAJES */
        .alert {
            width: 100%; box-sizing: border-box; margin: 0 0 20px 0; padding: 12px;
            border-radius: 6px; font-size: 13px; font-weight: 600; text-align: center;
            background-color: #fee2e2; color: #b91c1c; border-left: 4px solid #ef4444;
        }

        .footer {
            margin-top: 30px;
            font-size: 11px;
            color: #ffffff;
            letter-spacing: 1px;
            font-weight: 500;
            opacity: 0.8;
            text-transform: uppercase;
        }

        /* SOPORTE (Cuadrado con bordes suaves) */
        .support-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: #25D366;
            color: white;
            width: 50px; height: 50px;
            border-radius: 12px; /* Cuadrado suavizado */
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            text-decoration: none;
            transition: 0.2s;
        }
        .support-btn:hover { transform: scale(1.05); background: #20bd5a; }
    </style>
</head>
<body>

<div class="top-clock" id="clock">00:00:00</div>

<div class="login-card">
    <div class="logos-container">
        <img src="Assets Index/Hochscild_logo3.png" alt="Hochschild" class="logo-img">
        <div class="divider"></div>
        <img src="Assets Index/seguridadcivil.png" alt="Seguridad Civil" class="logo-img">
    </div>
    
    <h2>Control de Acceso</h2>
    
    <?php echo $mensaje; ?>

    <form method="POST" action="">
        <?php echo csrf_field(); ?>
        <div class="input-group">
            <i class="fa-solid fa-user icon-left"></i>
            <input type="text" name="usuario" placeholder="Usuario Corporativo" required autocomplete="username">
        </div>
        
        <div class="input-group">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" name="password" id="passwordField" placeholder="Contraseña" required autocomplete="current-password">
            <i class="fa-solid fa-eye icon-toggle" onclick="togglePassword()" id="toggleIcon"></i>
        </div>

        <div class="options-row">
            <label class="remember-box">
                <input type="checkbox" name="remember">
                <span>Recordarme</span>
            </label>
            <a href="registro.php" class="register-link">Registrarse</a>
        </div>
        
        <button type="submit" name="ingresar">Ingresar</button>
    </form>
</div>

<div class="footer">
    © 2026 Seguridad Civil • Hochschild Mining
</div>

<a href="#" class="support-btn" title="Soporte IT"><i class="fab fa-whatsapp"></i></a>

<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('clock').innerText = now.toLocaleTimeString('es-PE');
    }
    setInterval(updateClock, 1000);
    updateClock();

    function togglePassword() {
        const pass = document.getElementById('passwordField');
        const icon = document.getElementById('toggleIcon');
        if (pass.type === 'password') {
            pass.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pass.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>

</body>
</html>