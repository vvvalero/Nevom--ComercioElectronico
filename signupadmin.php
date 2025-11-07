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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? 'client') === 'admin' ? 'admin' : 'client';

    // Validar campos comunes
    if ($nombre === '' || $email === '' || $password === '') {
        $error = 'Rellena todos los campos obligatorios.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        // Comprobar si el email ya existe en users
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
            
            // Iniciar transacción para asegurar consistencia
            $conexion->begin_transaction();
            
            try {
                if ($role === 'client') {
                    // Para clientes, validar campos adicionales
                    $apellidos = trim($_POST['apellidos'] ?? '');
                    $direccion = trim($_POST['direccion'] ?? '');
                    $telefono = trim($_POST['telefono'] ?? '');
                    
                    if ($apellidos === '' || $direccion === '' || $telefono === '') {
                        throw new Exception('Los clientes deben rellenar todos los campos.');
                    }
                    
                    // Verificar si el teléfono ya existe
                    $stmtTel = $conexion->prepare('SELECT id FROM cliente WHERE telefono = ? LIMIT 1');
                    $stmtTel->bind_param('s', $telefono);
                    $stmtTel->execute();
                    $stmtTel->store_result();
                    if ($stmtTel->num_rows > 0) {
                        throw new Exception('El teléfono ya está registrado.');
                    }
                    $stmtTel->close();
                    
                    // 1. Insertar primero en tabla users
                    $insUser = $conexion->prepare('INSERT INTO users (nombre, email, password_hash, role) VALUES (?, ?, ?, ?)');
                    $insUser->bind_param('ssss', $nombre, $email, $hash, $role);
                    
                    if (!$insUser->execute()) {
                        throw new Exception('Error al crear la cuenta de usuario.');
                    }
                    
                    // 2. Obtener el ID del usuario recién creado
                    $user_id = $conexion->insert_id;
                    $insUser->close();
                    
                    // 3. Insertar en tabla cliente con la relación user_id
                    $insCliente = $conexion->prepare('INSERT INTO cliente (id, nombre, apellidos, email, telefono, direccion) VALUES (?, ?, ?, ?, ?, ?)');
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
                    
                    header('Location: index.php');
                    exit;
                    
                } else {
                    // Para administradores, solo insertar en users
                    $ins = $conexion->prepare('INSERT INTO users (nombre, email, password_hash, role) VALUES (?, ?, ?, ?)');
                    $ins->bind_param('ssss', $nombre, $email, $hash, $role);
                    
                    if (!$ins->execute()) {
                        throw new Exception('Error al crear la cuenta de administrador.');
                    }
                    
                    $user_id = $conexion->insert_id;
                    $ins->close();
                    
                    // Confirmar transacción
                    $conexion->commit();
                    
                    // Registrar en sesión
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $nombre;
                    $_SESSION['user_role'] = $role;
                    $_SESSION['user_email'] = $email;
                    
                    header('Location: index.php');
                    exit;
                }
            } catch (Exception $e) {
                // Revertir cambios si algo falla
                $conexion->rollback();
                $error = $e->getMessage();
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
                        <h2 class="mb-2">Nevom</h2>
                        <h3 class="mb-4">Crear Cuenta</h3>
                        <p class="text-muted">Registra una cuenta con su rol</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="registerForm">
                        <!-- Selector de Rol (Primero) -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Tipo de Cuenta *</label>
                            <select name="role" id="roleSelect" class="form-select form-select-lg" required>
                                <option value="">-- Selecciona un tipo de cuenta --</option>
                                <option value="client">Cliente</option>
                                <option value="admin">Administrador</option>
                            </select>
                            <small class="text-muted">Los administradores pueden gestionar productos y pedidos</small>
                        </div>

                        <!-- Campos Comunes -->
                        <div id="commonFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" name="nombre" class="form-control form-control-lg" 
                                       placeholder="Ej: Juan" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control form-control-lg" 
                                       placeholder="tu@email.com" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña *</label>
                                <input type="password" name="password" class="form-control form-control-lg" 
                                       placeholder="Mínimo 6 caracteres" minlength="6" required>
                            </div>
                        </div>

                        <!-- Campos Solo para Clientes -->
                        <div id="clientFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Apellidos *</label>
                                <input type="text" name="apellidos" class="form-control form-control-lg" 
                                       placeholder="Ej: García López" id="apellidosInput">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dirección *</label>
                                <input type="text" name="direccion" class="form-control form-control-lg" 
                                       placeholder="Ej: Calle Mayor 123, Madrid" id="direccionInput">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono *</label>
                                <input type="tel" name="telefono" class="form-control form-control-lg" 
                                       placeholder="Ej: 666 777 888" id="telefonoInput">
                                <small class="text-muted">Solo números, sin espacios ni guiones</small>
                            </div>
                        </div>

                        <!-- Botón de Envío -->
                        <div id="submitButton" style="display: none;">
                            <button class="btn btn-primary w-100 btn-lg rounded-pill mb-3" type="submit">
                                Crear Cuenta
                            </button>
                        </div>

                        <div class="text-center">
                            <span class="text-muted">¿Ya tienes cuenta?</span>
                            <a href="signin.php" class="text-decoration-none fw-semibold">Inicia sesión</a>
                        </div>
                        <hr class="my-4">
                        <div class="text-center">
                            <a href="index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Volver al inicio
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejo dinámico de campos según el rol seleccionado
        document.getElementById('roleSelect').addEventListener('change', function() {
            const role = this.value;
            const commonFields = document.getElementById('commonFields');
            const clientFields = document.getElementById('clientFields');
            const submitButton = document.getElementById('submitButton');
            
            const apellidosInput = document.getElementById('apellidosInput');
            const direccionInput = document.getElementById('direccionInput');
            const telefonoInput = document.getElementById('telefonoInput');

            if (role === '') {
                // Si no hay selección, ocultar todo
                commonFields.style.display = 'none';
                clientFields.style.display = 'none';
                submitButton.style.display = 'none';
            } else if (role === 'client') {
                // Mostrar campos comunes y de cliente
                commonFields.style.display = 'block';
                clientFields.style.display = 'block';
                submitButton.style.display = 'block';
                
                // Hacer campos de cliente requeridos
                apellidosInput.setAttribute('required', 'required');
                direccionInput.setAttribute('required', 'required');
                telefonoInput.setAttribute('required', 'required');
            } else if (role === 'admin') {
                // Mostrar solo campos comunes
                commonFields.style.display = 'block';
                clientFields.style.display = 'none';
                submitButton.style.display = 'block';
                
                // Quitar requerimiento de campos de cliente
                apellidosInput.removeAttribute('required');
                direccionInput.removeAttribute('required');
                telefonoInput.removeAttribute('required');
            }
        });

        // Validación de formulario antes de enviar
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const role = document.getElementById('roleSelect').value;
            
            if (role === '') {
                e.preventDefault();
                alert('Por favor, selecciona un tipo de cuenta');
                return false;
            }
        });
    </script>
</body>

</html>