<?php
require 'conexion.php';

$mensaje = ''; 

if (isset($_POST['enviar'])) {
    $marca = htmlspecialchars($_POST['marca']);
    $modelo = htmlspecialchars($_POST['modelo']);
    $capacidad = (int) $_POST['capacidad'];
    $stock = (int) $_POST['stock'];
    $color = htmlspecialchars($_POST['color']);
    $precio = (float) $_POST['precio'];

    // Insertar en la BBDD
    $sql = "INSERT INTO movil (marca, modelo, capacidad, stock, color, precio) 
            VALUES ('$marca', '$modelo', $capacidad, $stock, '$color', $precio)";

    if ($conexion->query($sql) === TRUE) {
        $mensaje = "<div class='alert alert-success'>Móvil añadido correctamente.</div>";
        // Redirigir a index.php después de 1 segundo
        header("refresh:1;url=index.php");
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al añadir el móvil: " . $conexion->error . "</div>";
    }
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Móvil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<header class="bg-dark text-white py-4 mb-5 text-center shadow-sm">
    <h1>Añadir Móvil</h1>
</header>

<div class="container">
    <?php
        // Mostrar mensaje si hay alguno
        if ($mensaje) {
            echo $mensaje;
        }
    ?>

    <form action="" method="post">
        <div class="mb-3">
            <label>Marca:</label>
            <input type="text" name="marca" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Modelo:</label>
            <input type="text" name="modelo" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Capacidad (GB):</label>
            <input type="number" name="capacidad" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Stock:</label>
            <input type="number" name="stock" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Color:</label>
            <input type="text" name="color" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Precio:</label>
            <input type="number" step="0.01" name="precio" class="form-control" required>
        </div>
        <input type="submit" name="enviar" value="Añadir Móvil" class="btn btn-primary">
    </form>
</div>

</body>
</html>
