<?php
require '../config/conexion.php';

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
        // Redirigir a indexadmin.php después de 1 segundo
        header("refresh:1;url=indexadmin.php");
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
    <title>Añadir Móvil - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php 
// Iniciar sesión para el navbar
if (session_status() === PHP_SESSION_NONE) session_start();
require '../components/navbar.php'; 
renderNavbar(['type' => 'admin', 'activeLink' => 'movil', 'basePath' => '../']); 
?>

<header class="bg-dark text-white py-4 mb-5 text-center shadow-sm" style="margin-top: 20px;">
    <div class="container">
        <h1 class="mb-0">📱 Añadir Móvil</h1>
        <p class="mb-0 mt-2 opacity-75">Agrega un nuevo dispositivo al inventario</p>
    </div>
</header>

<div class="container">
    <?php
        // Mostrar mensaje si hay alguno
        if ($mensaje) {
            echo $mensaje;
        }
    ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h3 class="card-title mb-4 text-center">Formulario de Nuevo Móvil</h3>
                    
                    <form action="" method="post">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Marca</label>
                                <input type="text" name="marca" class="form-control" placeholder="Ej: Samsung" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Modelo</label>
                                <input type="text" name="modelo" class="form-control" placeholder="Ej: Galaxy S23" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Capacidad (GB)</label>
                                <input type="number" name="capacidad" class="form-control" placeholder="128" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Stock</label>
                                <input type="number" name="stock" class="form-control" placeholder="10" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-control" placeholder="Negro" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Precio (€)</label>
                                <input type="number" step="0.01" name="precio" class="form-control" placeholder="599.99" required>
                            </div>
                            <div class="col-12 text-center mt-4">
                                <input type="submit" name="enviar" value="➕ Añadir Móvil" class="btn btn-primary btn-lg rounded-pill px-5">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="mt-5">
    <div class="container">
        <div class="text-center text-light opacity-75 py-3">
            <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Panel de Administración</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
