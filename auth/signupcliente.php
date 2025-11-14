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
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validar que todos los campos estén llenos
    if ($nombre === '' || $apellidos === '' || $email === '' || $telefono === '' || $direccion === '' || $password === '') {
        $error = 'Por favor, rellena todos los campos.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del email no es válido.';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="card shadow-lg rounded-4">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold mb-2">Nevom</h2>
                        <h3 class="mb-3">Crear Cuenta de Cliente</h3>
                        <p class="text-muted">Completa el formulario para registrarte</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="clientSignupForm">
                        
                        <!-- Información Personal -->
                        <div class="mb-4">
                            <h5 class="text-muted mb-3"><i class="bi bi-person-circle me-2"></i>Información Personal</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="nombre" class="form-control form-control-lg" 
                                       placeholder="Ej: Juan" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Apellidos *</label>
                                <input type="text" name="apellidos" class="form-control form-control-lg" 
                                       placeholder="Ej: García López" value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>" required>
                            </div>
                        </div>

                        <!-- Información de Contacto -->
                        <div class="mb-4">
                            <h5 class="text-muted mb-3"><i class="bi bi-envelope me-2"></i>Información de Contacto</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control form-control-lg" 
                                       placeholder="tu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Teléfono *</label>
                                <input type="tel" name="telefono" class="form-control form-control-lg" 
                                       placeholder="666777888" 
                                       value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" required>
                                <small class="text-muted">Solo números, sin espacios</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dirección *</label>
                                <input type="text" name="direccion" class="form-control form-control-lg" 
                                       placeholder="Calle Mayor 123, 28001 Madrid" 
                                       value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>" required>
                            </div>
                        </div>

                        <!-- Seguridad -->
                        <div class="mb-4">
                            <h5 class="text-muted mb-3"><i class="bi bi-shield-lock me-2"></i>Seguridad</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Contraseña *</label>
                                <input type="password" name="password" class="form-control form-control-lg" 
                                       placeholder="Mínimo 6 caracteres" minlength="6" id="password" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirmar Contraseña *</label>
                                <input type="password" name="password_confirm" class="form-control form-control-lg" 
                                       placeholder="Repite tu contraseña" minlength="6" id="password_confirm" required>
                                <small class="text-danger d-none" id="passwordError">Las contraseñas no coinciden</small>
                            </div>
                        </div>

                        <!-- Botón de Registro -->
                        <button class="btn btn-primary w-100 btn-lg rounded-pill mb-3" type="submit">
                            <i class="bi bi-person-plus me-2"></i>Crear Cuenta
                        </button>

                        <div class="text-center">
                            <span class="text-muted">¿Ya tienes cuenta?</span>
                            <a href="signin.php" class="text-decoration-none fw-semibold">Inicia sesión</a>
                        </div>

                        <hr class="my-4">

                        <div class="text-center">
                            <a href="../index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i>Volver al inicio
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación de contraseñas en tiempo real
        const password = document.getElementById('password');
        const passwordConfirm = document.getElementById('password_confirm');
        const passwordError = document.getElementById('passwordError');

        function checkPasswords() {
            if (passwordConfirm.value !== '' && password.value !== passwordConfirm.value) {
                passwordError.classList.remove('d-none');
                passwordConfirm.classList.add('is-invalid');
            } else {
                passwordError.classList.add('d-none');
                passwordConfirm.classList.remove('is-invalid');
            }
        }

        password.addEventListener('input', checkPasswords);
        passwordConfirm.addEventListener('input', checkPasswords);

        // Validación del formulario
        document.getElementById('clientSignupForm').addEventListener('submit', function(e) {
            if (password.value !== passwordConfirm.value) {
                e.preventDefault();
                passwordError.classList.remove('d-none');
                passwordConfirm.focus();
                return false;
            }
        });
    </script>
</body>

</html>