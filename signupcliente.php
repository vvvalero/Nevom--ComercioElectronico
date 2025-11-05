<?php
require 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Asegurar que exista la tabla users
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
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = 'client'; // Solo permite registrarse como cliente

    if ($nombre === '' || $email === '' || $password === '') {
        $error = 'Rellena todos los campos.';
    } else {
        // Comprobar si el email ya existe
        $stmt = $conexion->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'El email ya está registrado.';
            $stmt->close();
        } else {
            $stmt->close();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conexion->prepare('INSERT INTO users (nombre, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $ins->bind_param('ssss', $nombre, $email, $hash, $role);
            if ($ins->execute()) {
                // Registrar en sesión y redirigir
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_role'] = $role;
                header('Location: index.php');
                exit;
            } else {
                $error = 'Error al crear la cuenta: ' . $conexion->error;
            }
            $ins->close();
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
    <title>Registro - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="card shadow-lg rounded-4">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="mb-2">📱 Nevom</h2>
                        <h3 class="mb-4">Crear Cuenta</h3>
                        <p class="text-muted">Únete a nosotros y disfruta de ofertas exclusivas</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control form-control-lg" placeholder="Tu nombre completo" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control form-control-lg" placeholder="tu@email.com" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control form-control-lg" placeholder="Mínimo 8 caracteres" required>
                        </div>
                        <button class="btn btn-primary w-100 btn-lg rounded-pill mb-3" type="submit">
                            Registrarse
                        </button>
                        <div class="text-center">
                            <span class="text-muted">¿Ya tienes cuenta?</span>
                            <a href="signin.php" class="text-decoration-none fw-semibold">Inicia sesión</a>
                        </div>
                        <hr class="my-4">
                        <div class="text-center">
                            <a href="index.php" class="text-muted text-decoration-none">← Volver al inicio</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
