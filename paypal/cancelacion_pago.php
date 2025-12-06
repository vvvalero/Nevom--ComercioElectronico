<?php
require '../config/procesador_paypal.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Verificar autenticación
$clienteId = $_SESSION['cliente_id'] ?? null;
if ($_SESSION['user_role'] !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

registrarLogPayPal("Pago cancelado - Cliente: $clienteId", 'WARNING');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Cancelado - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="paypal-page">
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'simple', 'simpleText' => 'Pago Cancelado', 'basePath' => '../']); ?>

    <div class="paypal-confirmation-wrapper">
        <!-- Header con estilo warning -->
        <div class="paypal-header warning">
            <div class="paypal-header-content">
                <h1>⚠️ Pago Cancelado</h1>
                <p>Tu proceso de pago no se completó</p>
            </div>
            <svg class="paypal-wave" viewBox="0 0 1440 120" preserveAspectRatio="none">
                <path d="M0,64 C288,120 576,0 864,64 C1152,128 1296,32 1440,64 L1440,120 L0,120 Z"></path>
            </svg>
        </div>

        <!-- Tarjeta principal -->
        <div class="paypal-card">
            <div class="paypal-card-body">
                <!-- Mensaje de cancelación -->
                <div class="warning-message">
                    <div class="warning-icon">⚠️</div>
                    <h3>Pago cancelado</h3>
                    <p>Tu carrito se ha mantenido intacto. Puedes intentarlo de nuevo cuando quieras.</p>
                </div>

                <!-- Opciones disponibles -->
                <div class="info-box">
                    <h4>¿Qué puedes hacer?</h4>
                    <ul class="options-list">
                        <li>🔄 Volver al carrito y reintentar el pago</li>
                        <li>📋 Revisar los artículos de tu compra</li>
                        <li>💳 Usar otro método de pago</li>
                        <li>🛒 Seguir comprando y añadir más productos</li>
                    </ul>
                </div>

                <!-- Nota informativa -->
                <div class="info-note">
                    <span class="info-icon">ℹ️</span>
                    <p>Tu carrito no ha sido eliminado. Todos tus productos siguen disponibles.</p>
                </div>

                <!-- Botones de acción -->
                <div class="paypal-actions">
                    <a href="../carrito/carrito.php" class="btn-paypal-primary">
                        🛒 Volver al Carrito
                    </a>
                    <a href="../index.php" class="btn-paypal-secondary">
                        🏠 Seguir Comprando
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
