<?php
// Incluir conexión externa
require '../config/conexion.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Verificar que el usuario está logueado y es cliente
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

if (!$userName || $userRole !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Mensaje de éxito si viene de procesar_venta
$mensaje = $_SESSION['mensaje_venta'] ?? null;
$tipo_mensaje = $_SESSION['tipo_mensaje'] ?? 'success';
unset($_SESSION['mensaje_venta'], $_SESSION['tipo_mensaje']);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vender Mi Móvil - NEVOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'main', 'activeLink' => 'vender', 'basePath' => '../']); ?>

    <!-- Hero Section -->
    <section class="hero-section wave-light">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title">Vende Tu Móvil</h1>
                    <p class="hero-subtitle">
                        Obtén una valoración instantánea y vende tu móvil de forma rápida y segura
                    </p>
                </div>
                <div class="col-lg-5 text-center mt-5 mt-lg-0">
                    <div style="font-size: 10rem; opacity: 0.9;"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Formulario de Venta -->
    <section class="py-5">
        <div class="container">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show shadow-sm" role="alert">
                    <strong><?= $tipo_mensaje === 'success' ? '✅' : '❌' ?></strong> <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-lg">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Información de Tu Móvil</h4>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert alert-info">
                                <h5>ℹ️ Cómo funciona:</h5>
                                <ul class="mb-0">
                                    <li>Completa la información de tu móvil</li>
                                    <li>Recibirás una <strong>valoración instantánea</strong></li>
                                    <li>Si aceptas, procesaremos tu solicitud de venta</li>
                                    <li>Te contactaremos para coordinar la recogida</li>
                                </ul>
                            </div>

                            <form action="procesar_venta.php" method="POST" class="needs-validation" novalidate>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="marca" class="form-label fw-bold">Marca *</label>
                                        <input type="text" class="form-control" id="marca" name="marca" required 
                                               placeholder="Ej: Samsung, Apple, Xiaomi">
                                        <div class="invalid-feedback">
                                            Por favor, ingresa la marca del móvil
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="modelo" class="form-label fw-bold">Modelo *</label>
                                        <input type="text" class="form-control" id="modelo" name="modelo" required
                                               placeholder="Ej: Galaxy S21, iPhone 13">
                                        <div class="invalid-feedback">
                                            Por favor, ingresa el modelo del móvil
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="capacidad" class="form-label fw-bold">Capacidad (GB) *</label>
                                        <select class="form-select" id="capacidad" name="capacidad" required>
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

                                    <div class="col-md-6">
                                        <label for="color" class="form-label fw-bold">Color *</label>
                                        <input type="text" class="form-control" id="color" name="color" required
                                               placeholder="Ej: Negro, Blanco, Azul">
                                        <div class="invalid-feedback">
                                            Por favor, ingresa el color del móvil
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <label for="estado" class="form-label fw-bold">Estado del Dispositivo *</label>
                                        <select class="form-select" id="estado" name="estado" required>
                                            <option value="">Selecciona el estado</option>
                                            <option value="Como nuevo">Como nuevo - Sin señales de uso</option>
                                            <option value="Excelente">Excelente - Mínimas marcas de uso</option>
                                            <option value="Bueno">Bueno - Algunos signos normales de uso</option>
                                            <option value="Aceptable">Aceptable - Signos visibles de uso</option>
                                            <option value="Regular">Regular - Desgaste significativo pero funcional</option>
                                        </select>
                                        <div class="invalid-feedback">
                                            Por favor, selecciona el estado del dispositivo
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <label for="comentarios" class="form-label fw-bold">Comentarios Adicionales</label>
                                        <textarea class="form-control" id="comentarios" name="comentarios" rows="4"
                                                  placeholder="Describe cualquier detalle adicional sobre el móvil (opcional)"></textarea>
                                        <small class="text-muted">Menciona si tiene accesorios, caja original, etc.</small>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        Obtener Valoración
                                    </button>
                                    <a href="../index.php" class="btn btn-outline-secondary">
                                        ← Volver al Inicio
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="text-center text-light opacity-75">
                <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados | Proyecto Educativo</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validación de formularios Bootstrap
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
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

<?php
// Cerrar conexión
$conexion->close();
?>
