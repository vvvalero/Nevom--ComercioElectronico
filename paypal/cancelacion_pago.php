<?php
require '../config/conexion.php';
require '../config/paypal_config.php';
require '../config/procesador_paypal.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Verificar autenticación
$userRole = $_SESSION['user_role'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

if ($userRole !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Registrar cancelación
registrarLogPayPal("Pago cancelado por usuario - Cliente: $clienteId", 'WARNING');

// Mantener el carrito para que el usuario pueda reintentar
// No eliminar $_SESSION['carrito']
// No eliminar $_SESSION['carrito_paypal']

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Cancelado - Nevom</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .cancelacion-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        
        .cancelacion-icon {
            font-size: 60px;
            color: #ffc107;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .cancelacion-message {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .mensaje-alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .informacion-cancelacion {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
        }
        
        .informacion-cancelacion h5 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .lista-opciones {
            list-style: none;
            padding: 0;
        }
        
        .lista-opciones li {
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .lista-opciones li:last-child {
            border-bottom: none;
        }
        
        .lista-opciones li:before {
            content: "→ ";
            color: #ffc107;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .botones-accion {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-primario {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primario:hover {
            background-color: #0056b3;
            color: white;
            text-decoration: none;
        }
        
        .btn-secundario {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-secundario:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">Nevom</a>
            <span class="navbar-text text-white">Pago Cancelado</span>
        </div>
    </nav>

    <div class="cancelacion-container">
        
        <div class="cancelacion-icon">⚠</div>
        
        <div class="cancelacion-message">
            <h1 class="text-center mb-4">Pago Cancelado</h1>
            
            <div class="mensaje-alert">
                Tu proceso de pago ha sido cancelado. Tu carrito se ha mantenido intacto y puedes intentar nuevamente cuando lo desees.
            </div>

            <div class="informacion-cancelacion">
                <h5>¿Qué sucedió?</h5>
                <p>
                    Hemos registrado que cancelaste el proceso de pago en PayPal. Esto puede suceder por varias razones:
                </p>
                <ul class="lista-opciones">
                    <li>Decidiste no completar la compra en este momento</li>
                    <li>Hubo un problema técnico durante el pago</li>
                    <li>Deseas revisar tu carrito antes de pagar</li>
                    <li>Deseas utilizar otro método de pago</li>
                </ul>
            </div>

            <div class="informacion-cancelacion">
                <h5>¿Qué puedes hacer ahora?</h5>
                <ul class="lista-opciones">
                    <li>Volver a tu carrito y reintentar el pago</li>
                    <li>Revisar y modificar los artículos de tu compra</li>
                    <li>Contactar con nuestro equipo de soporte si hay algún problema</li>
                    <li>Seguir comprando en nuestra tienda</li>
                </ul>
            </div>

            <div class="alert alert-info">
                <strong>ℹ️ Importante:</strong> Tu carrito no ha sido eliminado. Todos tus artículos siguen disponibles para que completes la compra cuando desees.
            </div>

            <div class="botones-accion">
                <a href="../carrito/carrito.php" class="btn-primario">Volver al carrito</a>
                <a href="../index.php" class="btn-secundario">Seguir comprando</a>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
