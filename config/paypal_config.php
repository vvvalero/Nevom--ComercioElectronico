<?php
/**
 * Configuración de PayPal Sandbox
 */

// === ENTORNO ===
define('PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com/cgi-bin/webscr');
define('PAYPAL_LIVE_URL', 'https://www.paypal.com/cgi-bin/webscr');
define('PAYPAL_URL', PAYPAL_SANDBOX_URL); // Cambiar a PAYPAL_LIVE_URL en producción

// === CREDENCIALES (Obtener desde https://developer.paypal.com/developer/accounts/) ===
define('PAYPAL_MERCHANT_EMAIL', 'nevom-shop@business.example.com');

// === URLS ===
define('STORE_URL', 'http://localhost/nevom');
define('PAYPAL_RETURN_URL', STORE_URL . '/paypal/confirmacion_pago.php');
define('PAYPAL_CANCEL_URL', STORE_URL . '/paypal/cancelacion_pago.php');

// === OPCIONES ===
define('PAYPAL_CURRENCY', 'EUR');
define('PAYPAL_LANGUAGE', 'es');
define('PAYPAL_NO_SHIPPING', '0'); // '1' para productos virtuales

// === LOGS ===
define('PAYPAL_LOG_DIR', dirname(__DIR__) . '/logs');
if (!is_dir(PAYPAL_LOG_DIR)) @mkdir(PAYPAL_LOG_DIR, 0755, true);

function registrarLogPayPal($mensaje, $tipo = 'INFO') {
    $logFile = PAYPAL_LOG_DIR . '/paypal_' . date('Y-m-d') . '.log';
    error_log('[' . date('Y-m-d H:i:s') . "] [$tipo] $mensaje\n", 3, $logFile);
}

?>
