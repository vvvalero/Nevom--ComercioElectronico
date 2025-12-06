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
<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'simple', 'simpleText' => 'Pago Cancelado', 'basePath' => '../']); ?>

    <div class="header-confirmacion warning">
        <div class="container">
            <h1>⚠ Pago Cancelado</h1>
            <p>Tu proceso de pago no se completó</p>
        </div>
    </div>

    <div class="confirmacion-wrapper">
        <div class="confirmacion-card">
            <div class="confirmacion-card-body">
                <div class="mensaje-alert warning">
                    <span style="font-size:1.5rem">⚠</span>
                    <div><strong>Pago cancelado</strong><p style="margin:4px 0 0;font-size:0.9rem">Tu carrito se ha mantenido intacto.</p></div>
                </div>

                <div class="info-box">
                    <strong>¿Qué puedes hacer?</strong>
                    <ul style="margin:8px 0 0;padding-left:20px">
                        <li>Volver al carrito y reintentar</li>
                        <li>Revisar los artículos de tu compra</li>
                        <li>Usar otro método de pago</li>
                        <li>Seguir comprando</li>
                    </ul>
                </div>

                <div class="alert alert-info mt-3">
                    <strong>ℹ️</strong> Tu carrito no ha sido eliminado.
                </div>

                <div class="confirmacion-buttons">
                    <a href="../carrito/carrito.php" class="btn-confirmacion">🛒 Volver al carrito</a>
                    <a href="../index.php" class="btn-confirmacion-secondary">🏠 Seguir comprando</a>
                </div>
            </div>
        </div>
    </div>

    <footer><div class="container text-center text-muted py-3"><p class="mb-0">&copy; <?= date('Y') ?> Nevom</p></div></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
