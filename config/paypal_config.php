<?php
/**
 * Configuración de PayPal Sandbox
 * 
 * Este archivo contiene las credenciales y configuración para 
 * la integración con PayPal Sandbox (entorno de pruebas)
 */

// ====================================
// CONFIGURACIÓN DE PAYPAL SANDBOX
// ====================================

// URL del entorno de pruebas (Sandbox)
define('PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com/cgi-bin/webscr');

// URL del entorno de producción (comentada, para usar después)
// define('PAYPAL_LIVE_URL', 'https://www.paypal.com/cgi-bin/webscr');

// Usar Sandbox por defecto
define('PAYPAL_URL', PAYPAL_SANDBOX_URL);

// ====================================
// CREDENCIALES DE LA CUENTA VENDEDOR
// ====================================

/**
 * Email de la cuenta vendedor de PayPal Sandbox
 * 
 * Obtén este email desde:
 * https://developer.paypal.com/developer/accounts/
 * 
 * Busca la cuenta con rol "Merchant" (Vendedor)
 * Su email seguirá el patrón: usuario-facilitator@...
 * 
 * IMPORTANTE: Reemplaza este valor con tu email real
 */
define('PAYPAL_MERCHANT_EMAIL', 'nevom@business.example.com');

// ====================================
// CONFIGURACIÓN DE LA TIENDA
// ====================================

// URL base de tu sitio (sin slash final)
define('STORE_URL', 'http://localhost/nevom');

// Ruta de confirmación de pago exitoso
define('PAYPAL_RETURN_URL', STORE_URL . '/paypal/confirmacion_pago.php');

// Ruta de cancelación de pago
define('PAYPAL_CANCEL_URL', STORE_URL . '/paypal/cancelacion_pago.php');

// ====================================
// CONFIGURACIÓN DE PAGOS
// ====================================

// Moneda por defecto
define('PAYPAL_CURRENCY', 'EUR');

// Idioma de la página de pago
// 'es' para español, 'en' para inglés
define('PAYPAL_LANGUAGE', 'es');

// ====================================
// CONFIGURACIÓN DE OPCIONES
// ====================================

// No solicitar dirección de envío (si los productos son virtuales)
// Cambiar a "1" si no necesitas dirección de envío
define('PAYPAL_NO_SHIPPING', '0');

// ====================================
// CONFIGURACIÓN AVANZADA
// ====================================

// Formato de los logs
define('PAYPAL_LOG_FORMAT', 'Y-m-d H:i:s');

// Directorio para almacenar logs de PayPal
define('PAYPAL_LOG_DIR', dirname(__DIR__) . '/logs');

// Crear directorio de logs si no existe
if (!is_dir(PAYPAL_LOG_DIR)) {
    @mkdir(PAYPAL_LOG_DIR, 0755, true);
}

/**
 * Función para registrar eventos de PayPal
 * 
 * @param string $mensaje Mensaje a registrar
 * @param string $tipo Tipo de evento: 'INFO', 'ERROR', 'SUCCESS'
 */
function registrarLogPayPal($mensaje, $tipo = 'INFO') {
    $timestamp = date(PAYPAL_LOG_FORMAT);
    $logFile = PAYPAL_LOG_DIR . '/paypal_' . date('Y-m-d') . '.log';
    
    $contenido = "[$timestamp] [$tipo] $mensaje\n";
    
    error_log($contenido, 3, $logFile);
}

?>
