<?php
/*
* conexion.php
* Módulo para conectar con la base de datos.
*/

//// Configuración de la Base de Datos ////
$hostname = 'localhost';          // El servidor, en nuestro caso 'localhost'
$usuario = 'root';                // El usuario de XAMPP, por defecto 'root'
$password = '';                   // La contraseña de XAMPP, por defecto vacía
$bbdd = 'nevombbdd';            // Aquí tendréis que cambiar el nombre y poner la de vuestra BBDD.

////  Crear la conexión ////
$conexion = new mysqli($hostname, $usuario, $password, $bbdd);

//// Comprobar si hay un error de conexión ////
if ($conexion->connect_error) {
    // Detener el script y mostrar el error
    die('Error de Conexión (' . $conexion->connect_errno . '): ' . $conexion->connect_error);
}

//// ¡CRÍTICO para tildes y eñes! ////
//// Establecer el charset a UTF-8 para que la BBDD y PHP hablen el mismo idioma.
if (!$conexion->set_charset('utf8')) {
    die('Error al cargar: ' . $conexion->error);
}

// Si llegamos aquí, la conexión nos ha funcionado.





/*
ESTO QUE OS PONGO A CONTINUACIÓN ES UN EJEMPLO DE LO QUE HABRÍA QUE PONER EN INDEX.PHP

// 1. Incluimos el archivo de conexión.
require 'conexion.php'; // AQUÍ EN ESTE PHP ES DONDE PONEMOS LOS DATOS PARA QUE SE GENERE LA CONEXIÓN A LA BBDD

// 2. Preparamos la consulta SQL para la tabla 'productos'.
$sql = "SELECT id_producto, nombre, descripcion, precio FROM productos ORDER BY precio DESC";

// 3. Ejecutamos la consulta.
$resultado = $conexion->query($sql);

// 4. Comprobamos si la consulta tuvo éxito.
if (!$resultado) {
    die("Error en la consulta: " . $conexion->error);
}
*/


?>


