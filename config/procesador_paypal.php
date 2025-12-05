<?php
/**
 * Utilidades para la integración con PayPal
 * 
 * Este archivo contiene funciones auxiliares para:
 * - Crear formularios de pago
 * - Validar respuestas de PayPal
 * - Manejar transacciones
 */

require_once 'paypal_config.php';

class ProcesadorPayPal {
    
    /**
     * Genera los parámetros para el formulario de PayPal
     * 
     * @param array $datosCompra Array con los datos de la compra
     * @return array Array con los parámetros para PayPal
     */
    public static function generarParametrosPago($datosCompra) {
        $parametros = array();
        
        // Parámetros obligatorios
        $parametros['cmd'] = '_xclick';  // Tipo de transacción: compra de un artículo
        $parametros['business'] = PAYPAL_MERCHANT_EMAIL;  // Email del vendedor
        $parametros['currency_code'] = PAYPAL_CURRENCY;  // Moneda
        $parametros['lc'] = PAYPAL_LANGUAGE;  // Idioma de la página
        
        // URLs de retorno
        $parametros['return'] = PAYPAL_RETURN_URL;  // Retorno después de pago exitoso
        $parametros['cancel_return'] = PAYPAL_CANCEL_URL;  // Retorno si el usuario cancela
        
        // Información del artículo
        $parametros['item_name'] = $datosCompra['descripcion'] ?? 'Compra Nevom';
        $parametros['item_number'] = $datosCompra['numero_pedido'] ?? '';
        $parametros['amount'] = number_format($datosCompra['total'], 2, '.', '');
        
        // Información del cliente (opcional pero recomendado)
        if (!empty($datosCompra['cliente_nombre'])) {
            $parametros['first_name'] = explode(' ', $datosCompra['cliente_nombre'])[0];
            $parametros['last_name'] = !empty($datosCompra['cliente_apellido']) 
                ? $datosCompra['cliente_apellido'] 
                : explode(' ', $datosCompra['cliente_nombre'], 2)[1] ?? '';
        }
        
        if (!empty($datosCompra['cliente_email'])) {
            $parametros['email'] = $datosCompra['cliente_email'];
        }
        
        if (!empty($datosCompra['cliente_telefono'])) {
            $parametros['night_phone_b'] = $datosCompra['cliente_telefono'];
        }
        
        // Información de envío
        if (!empty($datosCompra['cliente_direccion'])) {
            $parametros['address1'] = $datosCompra['cliente_direccion'];
        }
        
        if (!empty($datosCompra['cliente_ciudad'])) {
            $parametros['city'] = $datosCompra['cliente_ciudad'];
        }
        
        if (!empty($datosCompra['cliente_codigo_postal'])) {
            $parametros['zip'] = $datosCompra['cliente_codigo_postal'];
        }
        
        if (!empty($datosCompra['cliente_pais'])) {
            $parametros['country'] = $datosCompra['cliente_pais'];
        }
        
        // Cantidad de artículos (si aplica)
        if (!empty($datosCompra['cantidad'])) {
            $parametros['quantity'] = $datosCompra['cantidad'];
        }
        
        // No solicitar dirección de envío si es producto virtual
        if (PAYPAL_NO_SHIPPING == '1') {
            $parametros['no_shipping'] = '1';
        }
        
        return $parametros;
    }
    
    /**
     * Construye la URL del formulario de PayPal con los parámetros
     * 
     * @param array $parametros Array con los parámetros
     * @return string URL completa con parámetros
     */
    public static function construirUrlFormulario($parametros) {
        return PAYPAL_URL . '?' . http_build_query($parametros);
    }
    
    /**
     * Genera el HTML del formulario oculto para PayPal
     * 
     * @param array $parametros Array con los parámetros
     * @return string HTML del formulario
     */
    public static function generarFormularioOculto($parametros) {
        $html = '<form id="paypal-form" action="' . PAYPAL_URL . '" method="post" style="display:none;">' . "\n";
        
        foreach ($parametros as $nombre => $valor) {
            $html .= '  <input type="hidden" name="' . htmlspecialchars($nombre) . '" value="' . htmlspecialchars($valor) . '">' . "\n";
        }
        
        $html .= '  <input type="submit" value="Procesar pago con PayPal">' . "\n";
        $html .= '</form>' . "\n";
        
        return $html;
    }
    
    /**
     * Genera el botón de PayPal
     * 
     * @param string $texto Texto del botón (por defecto: "Pagar con PayPal")
     * @return string HTML del botón
     */
    public static function generarBotonPayPal($texto = 'Pagar con PayPal') {
        return '<button type="submit" form="paypal-form" class="btn btn-primary btn-lg btn-paypal">' . 
               htmlspecialchars($texto) . 
               '</button>';
    }
    
    /**
     * Valida que los parámetros obligatorios estén presentes
     * 
     * @param array $datosCompra Array con los datos de la compra
     * @return array Array con los errores encontrados (vacío si no hay errores)
     */
    public static function validarDatos($datosCompra) {
        $errores = array();
        
        // Validar campos obligatorios
        if (empty($datosCompra['numero_pedido'])) {
            $errores[] = 'Número de pedido requerido';
        }
        
        if (empty($datosCompra['total']) || !is_numeric($datosCompra['total']) || $datosCompra['total'] <= 0) {
            $errores[] = 'Monto total inválido';
        }
        
        if (empty($datosCompra['descripcion'])) {
            $errores[] = 'Descripción del pedido requerida';
        }
        
        // Validar campos opcionales si están presentes
        if (!empty($datosCompra['cliente_email'])) {
            if (!filter_var($datosCompra['cliente_email'], FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'Email del cliente inválido';
            }
        }
        
        return $errores;
    }
    
    /**
     * Registra la información de la transacción en la base de datos
     * 
     * @param object $conexion Conexión a la base de datos
     * @param int $pedidoId ID del pedido
     * @param array $datos Datos de la transacción
     * @return bool true si se registró correctamente, false en caso contrario
     */
    public static function registrarTransaccion($conexion, $pedidoId, $datos) {
        try {
            $stmt = $conexion->prepare(
                "INSERT INTO transaccion_paypal 
                (pedido_id, referencia_paypal, estado, monto, moneda, fecha_creacion, datos_respuesta) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)"
            );
            
            $referencia = $datos['referencia'] ?? 'PENDIENTE';
            $estado = $datos['estado'] ?? 'INICIADA';
            $monto = $datos['monto'] ?? 0;
            $moneda = $datos['moneda'] ?? PAYPAL_CURRENCY;
            $datosJson = json_encode($datos);
            
            $stmt->bind_param(
                'issdss',
                $pedidoId,
                $referencia,
                $estado,
                $monto,
                $moneda,
                $datosJson
            );
            
            if ($stmt->execute()) {
                registrarLogPayPal("Transacción registrada para pedido $pedidoId", 'INFO');
                $stmt->close();
                return true;
            } else {
                registrarLogPayPal("Error al registrar transacción para pedido $pedidoId: " . $stmt->error, 'ERROR');
                $stmt->close();
                return false;
            }
        } catch (Exception $e) {
            registrarLogPayPal("Excepción al registrar transacción: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Actualiza el estado de una transacción
     * 
     * @param object $conexion Conexión a la base de datos
     * @param int $pedidoId ID del pedido
     * @param string $estado Nuevo estado
     * @param array $datos Datos adicionales
     * @return bool true si se actualizó correctamente
     */
    public static function actualizarTransaccion($conexion, $pedidoId, $estado, $datos = array()) {
        try {
            $datosJson = json_encode($datos);
            
            $stmt = $conexion->prepare(
                "UPDATE transaccion_paypal 
                SET estado = ?, datos_respuesta = CONCAT(datos_respuesta, ?) 
                WHERE pedido_id = ? 
                ORDER BY fecha_creacion DESC 
                LIMIT 1"
            );
            
            $actualización = json_encode(["timestamp" => date('Y-m-d H:i:s'), "estado" => $estado, "datos" => $datos]);
            
            $stmt->bind_param('ssi', $estado, $actualización, $pedidoId);
            
            if ($stmt->execute()) {
                registrarLogPayPal("Transacción actualizada: Pedido $pedidoId - Estado: $estado", 'INFO');
                $stmt->close();
                return true;
            } else {
                registrarLogPayPal("Error al actualizar transacción: " . $stmt->error, 'ERROR');
                $stmt->close();
                return false;
            }
        } catch (Exception $e) {
            registrarLogPayPal("Excepción al actualizar transacción: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Obtiene la información de una transacción
     * 
     * @param object $conexion Conexión a la base de datos
     * @param int $pedidoId ID del pedido
     * @return array Array con la información de la transacción o null
     */
    public static function obtenerTransaccion($conexion, $pedidoId) {
        try {
            $stmt = $conexion->prepare(
                "SELECT * FROM transaccion_paypal 
                WHERE pedido_id = ? 
                ORDER BY fecha_creacion DESC 
                LIMIT 1"
            );
            
            $stmt->bind_param('i', $pedidoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $transaccion = $result->fetch_assoc();
                $stmt->close();
                return $transaccion;
            }
            
            $stmt->close();
            return null;
        } catch (Exception $e) {
            registrarLogPayPal("Excepción al obtener transacción: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
}

?>
