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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php
    // Iniciar sesión para el navbar
    if (session_status() === PHP_SESSION_NONE) session_start();
    require '../components/navbar.php';
    renderNavbar(['type' => 'admin', 'activeLink' => 'movil', 'basePath' => '../']);
    ?>

    <!-- Hero Section -->
    <section class="hero-section wave-light">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title">Añadir Móvil</h1>
                    <p class="hero-subtitle">
                        Agrega un nuevo dispositivo al inventario de Nevom
                    </p>
                </div>
                <div class="col-lg-5 text-center mt-5 mt-lg-0">
                    <i class="fas fa-mobile-alt" style="font-size: 10rem; opacity: 0.9;"></i>
                </div>
            </div>
        </div>
    </section>

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
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Información del Nuevo Móvil</h4>
                    </div>
                    <div class="card-body p-4">
                        <form action="" method="post" class="needs-validation" novalidate>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Marca</label>
                                    <input type="text" name="marca" class="form-control" placeholder="Ej: Samsung" required>
                                    <div class="invalid-feedback">
                                        Por favor, ingresa la marca del móvil
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Modelo</label>
                                    <input type="text" name="modelo" class="form-control" placeholder="Ej: Galaxy S23" required>
                                    <div class="invalid-feedback">
                                        Por favor, ingresa el modelo del móvil
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Capacidad (GB)</label>
                                    <select class="form-select" name="capacidad" required>
                                        <option value="">Selecciona una capacidad</option>
                                        <option value="16">16 GB</option>
                                        <option value="32">32 GB</option>
                                        <option value="64">64 GB</option>
                                        <option value="128">128 GB</option>
                                        <option value="256">256 GB</option>
                                        <option value="512">512 GB</option>
                                        <option value="1024">1 TB</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor, selecciona la capacidad
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Stock</label>
                                    <input type="number" name="stock" class="form-control" placeholder="10" required>
                                    <div class="invalid-feedback">
                                        Por favor, ingresa el stock disponible
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Color</label>
                                    <input type="text" name="color" class="form-control" placeholder="Negro" required>
                                    <div class="invalid-feedback">
                                        Por favor, ingresa el color del móvil
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Precio (€)</label>
                                    <input type="number" step="0.01" name="precio" class="form-control" placeholder="599.99" required>
                                    <div class="invalid-feedback">
                                        Por favor, ingresa el precio del móvil
                                    </div>
                                </div>
                                <div class="col-12 text-center mt-4">
                                    <button type="submit" name="enviar" class="btn btn-primary btn-lg rounded-pill px-5">
                                        <i class="fas fa-plus"></i> Añadir Móvil
                                    </button>
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
    <script>
        // Validación de formulario de Bootstrap
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>

</body>

</html>