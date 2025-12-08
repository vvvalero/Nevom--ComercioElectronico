<?php
/**
 * Procesador de PayPal - Funciones para integración con PayPal
 */
require_once 'paypal_config.php';

class ProcesadorPayPal {
    
    /**
     * Genera los parámetros para el formulario de PayPal
     */
    public static function generarParametrosPago($datosCompra) {
        $params = [
            'cmd' => '_xclick',
            'business' => PAYPAL_MERCHANT_EMAIL,
            'currency_code' => PAYPAL_CURRENCY,
            'lc' => PAYPAL_LANGUAGE,
            'return' => PAYPAL_RETURN_URL,
            'cancel_return' => PAYPAL_CANCEL_URL,
            'item_name' => $datosCompra['descripcion'] ?? 'Compra Nevom',
            'item_number' => $datosCompra['numero_pedido'] ?? '',
            'amount' => number_format($datosCompra['total'], 2, '.', '')
        ];
        
        // Datos del cliente (opcionales)
        if (!empty($datosCompra['cliente_nombre'])) {
            $nombres = explode(' ', $datosCompra['cliente_nombre'], 2);
            $params['first_name'] = $nombres[0];
            $params['last_name'] = $datosCompra['cliente_apellido'] ?? ($nombres[1] ?? '');
        }
        if (!empty($datosCompra['cliente_email'])) $params['email'] = $datosCompra['cliente_email'];
        if (!empty($datosCompra['cliente_telefono'])) $params['night_phone_b'] = $datosCompra['cliente_telefono'];
        if (!empty($datosCompra['cliente_direccion'])) $params['address1'] = $datosCompra['cliente_direccion'];
        if (!empty($datosCompra['cliente_ciudad'])) $params['city'] = $datosCompra['cliente_ciudad'];
        if (!empty($datosCompra['cliente_codigo_postal'])) $params['zip'] = $datosCompra['cliente_codigo_postal'];
        if (!empty($datosCompra['cliente_pais'])) $params['country'] = $datosCompra['cliente_pais'];
        // No enviar 'quantity' porque el total ya está multiplicado por la cantidad
        if (PAYPAL_NO_SHIPPING == '1') $params['no_shipping'] = '1';
        
        return $params;
    }
    
    /**
     * Genera el HTML del formulario oculto para enviar a PayPal
     */
    public static function generarFormularioOculto($parametros) {
        $html = '<form id="paypal-form" action="' . PAYPAL_URL . '" method="post" style="display:none;">';
        foreach ($parametros as $nombre => $valor) {
            $html .= '<input type="hidden" name="' . htmlspecialchars($nombre) . '" value="' . htmlspecialchars($valor) . '">';
        }
        $html .= '</form>';
        return $html;
    }
    
    /**
     * Valida los datos obligatorios de la compra
     */
    public static function validarDatos($datosCompra) {
        $errores = [];
        if (empty($datosCompra['numero_pedido'])) $errores[] = 'Número de pedido requerido';
        if (empty($datosCompra['total']) || !is_numeric($datosCompra['total']) || $datosCompra['total'] <= 0) $errores[] = 'Monto total inválido';
        if (empty($datosCompra['descripcion'])) $errores[] = 'Descripción del pedido requerida';
        if (!empty($datosCompra['cliente_email']) && !filter_var($datosCompra['cliente_email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Email del cliente inválido';
        return $errores;
    }
}

