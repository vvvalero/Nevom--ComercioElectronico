<?php
require '../config/conexion.php';

// Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',

        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Verificar que el usuario esté logueado como cliente
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

if (!$userName || $userRole !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Obtener datos actuales del cliente
$stmt = $conexion->prepare("
    SELECT c.nombre, c.apellidos, c.email, c.telefono, c.direccion, u.nombre as user_nombre, u.email as user_email
    FROM cliente c
    JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $clienteId);
$stmt->execute();
$result = $stmt->get_result();
$cliente = $result->fetch_assoc();
$stmt->close();

if (!$cliente) {
    $_SESSION['mensaje'] = 'Error: No se encontraron los datos del cliente';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: ../index.php');
    exit;
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $userNombre = trim($_POST['user_nombre'] ?? '');

    $errores = [];

    if (empty($nombre)) $errores[] = 'El nombre es obligatorio';
    if (empty($apellidos)) $errores[] = 'Los apellidos son obligatorios';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Email inválido';
    if (empty($telefono)) $errores[] = 'El teléfono es obligatorio';
    if (empty($direccion)) $errores[] = 'La dirección es obligatoria';
    if (empty($userNombre)) $errores[] = 'El nombre de usuario es obligatorio';

    // Verificar si el email ya existe en otro usuario
    $stmt = $conexion->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param('si', $email, $userId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errores[] = 'El email ya está en uso por otro usuario';
    }
    $stmt->close();

    if (empty($errores)) {
        // Actualizar users
        $stmt = $conexion->prepare("UPDATE users SET nombre = ?, email = ? WHERE id = ?");
        $stmt->bind_param('ssi', $userNombre, $email, $userId);
        $stmt->execute();
        $stmt->close();

        // Actualizar cliente
        $stmt = $conexion->prepare("UPDATE cliente SET nombre = ?, apellidos = ?, email = ?, telefono = ?, direccion = ? WHERE id = ?");
        $stmt->bind_param('sssssi', $nombre, $apellidos, $email, $telefono, $direccion, $clienteId);
        $stmt->execute();
        $stmt->close();

        // Actualizar sesión
        $_SESSION['user_name'] = $userNombre;

        $_SESSION['mensaje'] = 'Perfil actualizado correctamente';
        $_SESSION['mensaje_tipo'] = 'success';

        // Recargar datos
        $stmt = $conexion->prepare("
            SELECT c.nombre, c.apellidos, c.email, c.telefono, c.direccion, u.nombre as user_nombre, u.email as user_email
            FROM cliente c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $result = $stmt->get_result();
        $cliente = $result->fetch_assoc();
        $stmt->close();
    } else {
        $mensajeError = implode('<br>', $errores);
    }
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi Perfil - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php';
    renderNavbar(['type' => 'main', 'activeLink' => 'perfil', 'basePath' => '../']); ?>

    <div class="container mt-3 pt-3">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg rounded-4">
                    <div class="card-header bg-primary text-white text-center">
                        <h3 class="mb-0"><i class="fas fa-user me-2"></i>Mi Perfil</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($_SESSION['mensaje'])): ?>
                            <div class="alert alert-<?php echo $_SESSION['mensaje_tipo']; ?> alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['mensaje']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['mensaje'], $_SESSION['mensaje_tipo']); ?>
                        <?php endif; ?>

                        <?php if (!empty($errores)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errores as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title mb-4 text-primary">
                                                <i class="fas fa-user-circle me-2"></i>Información de Usuario
                                            </h5>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <i class="fas fa-user me-1"></i>Nombre de Usuario
                                                </label>
                                                <input type="text" name="user_nombre" class="form-control form-control-lg" value="<?php echo htmlspecialchars($cliente['user_nombre']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <i class="fas fa-envelope me-1"></i>Email
                                                </label>
                                                <input type="email" name="email" class="form-control form-control-lg" value="<?php echo htmlspecialchars($cliente['user_email']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title mb-4 text-primary">
                                                <i class="fas fa-id-card me-2"></i>Información Personal
                                            </h5>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <i class="fas fa-signature me-1"></i>Nombre
                                                </label>
                                                <input type="text" name="nombre" class="form-control form-control-lg" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <i class="fas fa-signature me-1"></i>Apellidos
                                                </label>
                                                <input type="text" name="apellidos" class="form-control form-control-lg" value="<?php echo htmlspecialchars($cliente['apellidos']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <i class="fas fa-phone me-1"></i>Teléfono
                                                </label>
                                                <input type="tel" name="telefono" class="form-control form-control-lg" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <i class="fas fa-map-marker-alt me-1"></i>Dirección
                                                </label>
                                                <textarea name="direccion" class="form-control form-control-lg" rows="3" required><?php echo htmlspecialchars($cliente['direccion']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5 py-3 shadow-sm">
                                    <i class="fas fa-save me-2"></i>Actualizar Perfil
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>