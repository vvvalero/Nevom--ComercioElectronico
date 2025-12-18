<?php
/**
 * Configuración de PayPal Sandbox
 */

// === ENTORNO ===
define('PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com/cgi-bin/webscr');
define('PAYPAL_LIVE_URL', 'https://www.paypal.com/cgi-bin/webscr');
define('PAYPAL_URL', PAYPAL_SANDBOX_URL); 

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

// Logging eliminado

?>
