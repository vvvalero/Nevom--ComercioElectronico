<?php
$hostname = 'localhost';
$usuario = 'root';
$password = '';
$bbdd = 'nevombbdd';

$conexion = new mysqli($hostname, $usuario, $password, $bbdd);

if ($conexion->connect_error) {
    die('Error de Conexión (' . $conexion->connect_errno . '): ' . $conexion->connect_error);
}

if (!$conexion->set_charset('utf8')) {
    die('Error al cargar: ' . $conexion->error);
}
?>
