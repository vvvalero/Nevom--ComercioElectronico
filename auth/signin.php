<?php
require '../config/conexion.php';
// Inicializar sesión con parámetros seguros (solo si aún no se ha iniciado)
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

// Asegurar que exista la tabla users (por si no se importó el SQL)
$createUsers = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('client','admin') NOT NULL DEFAULT 'client'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;";
$conexion->query($createUsers);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Rellena todos los campos.';
    } else {
    $stmt = $conexion->prepare('SELECT id, nombre, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $error = 'Credenciales incorrectas.';
            $stmt->close();
        } else {
            $stmt->bind_result($user_id, $nombre, $hash, $role);
            $stmt->fetch();
            // Verificar que el hash existe y es válido
            if ($hash !== null && password_verify($password, $hash)) {
                // Login OK
                session_regenerate_id(true); // Prevención de fijación de sesión
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_role'] = $role;
                // Intentar obtener el cliente_id si es cliente
                if ($role === 'client') {
                    $stmtCli = $conexion->prepare('SELECT id FROM cliente WHERE user_id = ? LIMIT 1');
                    $stmtCli->bind_param('i', $user_id);
                    if ($stmtCli->execute()) {
                        $stmtCli->store_result();
                        if ($stmtCli->num_rows === 1) {
                            $stmtCli->bind_result($cliente_id);
                            $stmtCli->fetch();
                            $_SESSION['cliente_id'] = $cliente_id;
                        }
                    }
                    $stmtCli->close();
                    // Redirigir clientes a index.php
                    header('Location: ../index.php');
                    exit;
                } else {
                    // Redirigir administradores a indexadmin.php
                    header('Location: ../admin/indexadmin.php');
                    exit;
                }
            } else {
                $error = 'Credenciales incorrectas.';
            }
            $stmt->close();
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
    <title>Iniciar Sesión - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'simple', 'simpleText' => 'Iniciar Sesión', 'basePath' => '../']); ?>

    <!-- Hero Section -->
    <section class="hero-section wave-light">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title">Bienvenido de vuelta</h1>
                    <p class="hero-subtitle">
                        Accede a tu cuenta de Nevom para comprar y vender móviles de forma segura
                    </p>
                </div>
                <div class="col-lg-5 text-center mt-5 mt-lg-0">
                    <i class="fas fa-sign-in-alt" style="font-size: 10rem; opacity: 0.9;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Formulario de Login -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card shadow-lg">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Iniciar Sesión</h4>
                        </div>
                        <div class="card-body p-4">

                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="loginForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email *</label>
                            <input type="email" name="email" class="form-control form-control-lg" placeholder="tu@email.com" required>
                            <div class="invalid-feedback">
                                Por favor, ingresa un email válido
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Contraseña *</label>
                            <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
                            <div class="invalid-feedback">
                                Por favor, ingresa tu contraseña
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 btn-lg rounded-pill mb-3" type="submit">
                            <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                        </button>
                        <div class="text-center">
                            <span class="text-muted">¿No tienes cuenta?</span>
                            <a href="signupcliente.php" class="text-decoration-none fw-semibold">Regístrate aquí</a>
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
