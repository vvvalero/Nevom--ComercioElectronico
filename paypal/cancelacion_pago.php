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
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'main', 'basePath' => '../']); ?>

    <!-- Header -->
    <header class="page-header warning wave-light">
        <div class="container">
            <h1>⚠️ Pago Cancelado</h1>
            <p>Tu proceso de pago no se completó</p>
        </div>
    </header>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Mensaje de cancelación -->
                <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                    <span class="me-3" style="font-size: 1.5rem;">⚠️</span>
                    <div>
                        <strong>Pago cancelado</strong>
                        <p class="mb-0 small">Tu carrito se ha mantenido intacto. Puedes intentarlo de nuevo cuando quieras.</p>
                    </div>
                </div>

                <!-- Opciones disponibles -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">💡 ¿Qué puedes hacer?</h5>
                        <ul class="mb-0">
                            <li>🔄 Volver al carrito y reintentar el pago</li>
                            <li>📋 Revisar los artículos de tu compra</li>
                            <li>💳 Usar otro método de pago</li>
                            <li>🛒 Seguir comprando y añadir más productos</li>
                        </ul>
                    </div>
                </div>

                <!-- Nota informativa -->
                <div class="alert alert-info d-flex align-items-start mb-4">
                    <span class="me-3" style="font-size: 1.25rem;">ℹ️</span>
                    <div>
                        <p class="mb-0 small">Tu carrito no ha sido eliminado. Todos tus productos siguen disponibles.</p>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="../carrito/carrito.php" class="btn btn-primary btn-lg">
                        🛒 Volver al Carrito
                    </a>
                    <a href="../index.php" class="btn btn-outline-secondary btn-lg">
                        🏠 Seguir Comprando
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer mt-auto">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
