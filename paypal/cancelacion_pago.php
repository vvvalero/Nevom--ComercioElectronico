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

// Logging eliminado

// Limpiar flag de procesamiento
unset($_SESSION['pago_procesado']);

// Redirigir al carrito con mensaje
$_SESSION['mensaje'] = 'Pago cancelado. Tu carrito se ha mantenido intacto.';
$_SESSION['mensaje_tipo'] = 'warning';
header('Location: ../carrito/carrito.php');
exit;