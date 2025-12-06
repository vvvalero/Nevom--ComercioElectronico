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
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'simple', 'simpleText' => 'Iniciar Sesión', 'basePath' => '../']); ?>

    <div class="auth-container">
        <div class="card shadow-lg rounded-4">
            <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="mb-2">Nevom</h2>
                        <h3 class="mb-4">Iniciar Sesión</h3>
                        <p class="text-muted">Accede a tu cuenta para continuar</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control form-control-lg" placeholder="tu@email.com" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
                        </div>
                        <button class="btn btn-primary w-100 btn-lg rounded-pill mb-3" type="submit">
                            Iniciar Sesión
                        </button>
                        <div class="text-center">
                            <span class="text-muted">¿No tienes cuenta?</span>
                            <a href="signupcliente.php" class="text-decoration-none fw-semibold">Regístrate aquí</a>
                        </div>
                        <hr class="my-4">
                        <div class="text-center">
                            <a href="../index.php" class="text-muted text-decoration-none">← Volver al inicio</a>
                        </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
