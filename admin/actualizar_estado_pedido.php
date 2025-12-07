<?php
require '../config/conexion.php';

// Iniciar sesión de forma segura
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

// Verificar que el usuario esté logueado como administrador
$userRole = $_SESSION['user_role'] ?? null;

if ($userRole !== 'admin') {
    $_SESSION['mensaje'] = 'No tienes permisos para realizar esta acción';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: ../auth/signin.php');
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gestionar_compras.php');
    exit;
}

// Obtener y validar datos
$pedidoId = intval($_POST['pedido_id'] ?? 0);
$nuevoEstado = trim($_POST['nuevo_estado'] ?? '');

// Estados válidos
$estadosValidos = ['procesando', 'preparando', 'enviado', 'entregado'];

if ($pedidoId <= 0) {
    $_SESSION['mensaje'] = 'ID de pedido inválido';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: gestionar_compras.php');
    exit;
}

if (!in_array($nuevoEstado, $estadosValidos)) {
    $_SESSION['mensaje'] = 'Estado inválido';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: gestionar_compras.php');
    exit;
}

try {
    // Actualizar el estado del pedido
    $stmt = $conexion->prepare("UPDATE pedido SET estado = ? WHERE id = ?");
    $stmt->bind_param('si', $nuevoEstado, $pedidoId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['mensaje'] = "Estado del pedido #$pedidoId actualizado a '$nuevoEstado'";
            $_SESSION['mensaje_tipo'] = 'success';
        } else {
            $_SESSION['mensaje'] = "No se encontró el pedido #$pedidoId";
            $_SESSION['mensaje_tipo'] = 'warning';
        }
    } else {
        throw new Exception("Error al actualizar el estado: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    $_SESSION['mensaje'] = "Error: " . $e->getMessage();
    $_SESSION['mensaje_tipo'] = 'danger';
} finally {
    $conexion->close();
}

header('Location: gestionar_compras.php');
exit;
