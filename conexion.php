<?php
// Configuración de conexión
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'nevombbdd';

try {
    // Crear conexión
    $conexion = new mysqli($host, $user, $pass, $db);
    $conexion->set_charset('utf8');

    if ($conexion->connect_errno) {
        throw new Exception("Error de conexión ({$conexion->connect_errno}): {$conexion->connect_error}");
    }

} catch (Exception $e) {
    die("<div class='alert alert-danger text-center'><strong>Error:</strong> " . $e->getMessage() . "</div>");
}
?>
