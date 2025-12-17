<?php
require '../config/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['confirm_password'] ?? '';

    // Validar que todos los campos estén llenos
    if ($nombre === '' || $apellidos === '' || $email === '' || $telefono === '' || $direccion === '' || $password === '') {
        $error = 'Por favor, rellena todos los campos.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del email no es válido.';
    } elseif (!preg_match('/^\d+$/', $telefono)) {
        $error = 'El teléfono debe contener solo números.';
    } else {
        // Comprobar si el email ya existe
        $stmt = $conexion->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'El email ya está registrado. <a href="signin.php">Inicia sesión</a>';
            $stmt->close();
        } else {
            $stmt->close();
            
            // Verificar si el teléfono ya existe
            $stmtTel = $conexion->prepare('SELECT id FROM cliente WHERE telefono = ? LIMIT 1');
            $stmtTel->bind_param('s', $telefono);
            $stmtTel->execute();
            $stmtTel->store_result();
            
            if ($stmtTel->num_rows > 0) {
                $error = 'El teléfono ya está registrado.';
                $stmtTel->close();
            } else {
                $stmtTel->close();
                
                // Iniciar transacción
                $conexion->begin_transaction();
                
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'client';
                    
                    // 1. Insertar en tabla users
                    $insUser = $conexion->prepare('INSERT INTO users (nombre, email, password_hash, role) VALUES (?, ?, ?, ?)');
                    $insUser->bind_param('ssss', $nombre, $email, $hash, $role);
                    
                    if (!$insUser->execute()) {
                        throw new Exception('Error al crear la cuenta de usuario.');
                    }
                    
                    // 2. Obtener el ID del usuario recién creado
                    $user_id = $conexion->insert_id;
                    $insUser->close();
                    
                    // 3. Insertar en tabla cliente con la relación user_id
                    $insCliente = $conexion->prepare('INSERT INTO cliente (user_id, nombre, apellidos, email, telefono, direccion) VALUES (?, ?, ?, ?, ?, ?)');
                    $insCliente->bind_param('isssss', $user_id, $nombre, $apellidos, $email, $telefono, $direccion);
                    
                    if (!$insCliente->execute()) {
                        throw new Exception('Error al crear el perfil de cliente.');
                    }
                    
                    $cliente_id = $conexion->insert_id;
                    $insCliente->close();
                    
                    // Confirmar transacción
                    $conexion->commit();
                    
                    // Registrar en sesión
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['cliente_id'] = $cliente_id;
                    $_SESSION['user_name'] = $nombre;
                    $_SESSION['user_role'] = $role;
                    $_SESSION['user_email'] = $email;
                    
                    // Redirigir al index o dashboard de cliente
                    header('Location: ../index.php');
                    exit;
                    
                } catch (Exception $e) {
                    // Revertir cambios si algo falla
                    $conexion->rollback();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de Cliente - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'simple', 'simpleText' => 'Registro de Cliente', 'basePath' => '../']); ?>

    <!-- Hero Section -->
    <section class="hero-section wave-light">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title">Crear Cuenta de Cliente</h1>
                    <p class="hero-subtitle">
                        Únete a Nevom y comienza a comprar y vender móviles de forma segura
                    </p>
                </div>
                <div class="col-lg-5 text-center mt-5 mt-lg-0">
                    <i class="fas fa-user-plus" style="font-size: 10rem; opacity: 0.9;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Formulario de Registro -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-lg">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Registro de Cliente</h4>
                        </div>
                        <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="clientSignupForm" class="needs-validation" novalidate>
                        
                        <!-- Información Personal -->
                        <div class="mb-4">
                            <h5 class="text-muted mb-3"><i class="fas fa-user me-2"></i>Información Personal</h5>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nombre *</label>
                                <input type="text" name="nombre" class="form-control form-control-lg" 
                                       placeholder="Ej: Juan" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                                <div class="invalid-feedback">
                                    Por favor, ingresa tu nombre
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Apellidos *</label>
                                <input type="text" name="apellidos" class="form-control form-control-lg" 
                                       placeholder="Ej: García López" value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>" required>
                                <div class="invalid-feedback">
                                    Por favor, ingresa tus apellidos
                                </div>
                            </div>
                        </div>

                        <!-- Información de Contacto -->
                        <div class="mb-4">
                            <h5 class="text-muted mb-3"><i class="fas fa-envelope me-2"></i>Información de Contacto</h5>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Email *</label>
                                <input type="email" name="email" class="form-control form-control-lg" 
                                       placeholder="tu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                <div class="invalid-feedback">
                                    Por favor, ingresa un email válido
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Teléfono *</label>
                                <input type="tel" name="telefono" class="form-control form-control-lg" 
                                       placeholder="666777888" pattern="[0-9]+" 
                                       value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" required>
                                <div class="invalid-feedback">
                                    Por favor, ingresa tu teléfono
                                </div>
                                <small class="text-muted">Solo números, sin espacios</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Dirección *</label>
                                <input type="text" name="direccion" class="form-control form-control-lg" 
                                       placeholder="Calle Mayor 123, 28001 Madrid" 
                                       value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>" required>
                                <div class="invalid-feedback">
                                    Por favor, ingresa tu dirección
                                </div>
                            </div>
                        </div>

                        <!-- Seguridad -->
                        <div class="mb-4">
                            <h5 class="text-muted mb-3"><i class="fas fa-shield-alt me-2"></i>Seguridad</h5>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Contraseña *</label>
                                <input type="password" name="password" class="form-control form-control-lg" 
                                       placeholder="Mínimo 6 caracteres" minlength="6" required>
                                <div class="invalid-feedback">
                                    La contraseña debe tener al menos 6 caracteres
                                </div>
                                <small class="text-muted">Mínimo 6 caracteres, incluye mayúsculas, minúsculas y números</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Confirmar Contraseña *</label>
                                <input type="password" name="confirm_password" class="form-control form-control-lg" 
                                       placeholder="Repite tu contraseña" minlength="6" required>
                                <div class="invalid-feedback">
                                    Las contraseñas no coinciden
                                </div>
                            </div>
                        </div>

                        <!-- Botón de Registro -->
                        <button class="btn btn-primary w-100 btn-lg rounded-pill mb-3" type="submit">
                            <i class="fas fa-user-plus me-2"></i>Crear Cuenta
                        </button>

                        <div class="text-center">
                            <span class="text-muted">¿Ya tienes cuenta?</span>
                            <a href="signin.php" class="text-decoration-none fw-semibold">Inicia sesión</a>
                        </div>

                        <hr class="my-4">

                        <div class="text-center">
                            <a href="../index.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Volver al inicio
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap form validation
        (function () {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
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
