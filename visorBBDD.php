<?php
// Incluir conexión externa
require 'conexion.php';
// Iniciar sesión (con parámetros seguros si no existe)
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

// Nombre y rol del usuario logueado (si aplica)
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

/**
 * Ejecuta una consulta SQL y devuelve el resultado.
 */
function ejecutarConsulta($conexion, $sql)
{
    $resultado = $conexion->query($sql);
    if (!$resultado) {
        throw new Exception("Error en la consulta ({$conexion->errno}): {$conexion->error}");
    }
    return $resultado;
}

/**
 * Genera una tabla HTML con Bootstrap a partir de una consulta SQL.
 */
function mostrarTabla($conexion, $titulo, $tabla, $where = '')
{
    $sql = "SELECT * FROM $tabla" . ($where ? " WHERE $where" : '');
    $resultado = ejecutarConsulta($conexion, $sql);

    echo "<div class='container my-5'>";
    echo "<h2 class='text-center mb-4'>$titulo</h2>";

    if ($resultado->num_rows === 0) {
        echo "<div class='alert alert-warning text-center shadow-sm'>
                    La tabla <strong>$tabla</strong> no contiene registros.
                  </div>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped table-bordered text-center align-middle shadow-sm'>";

        // Cabecera
        $campos = array_keys($resultado->fetch_assoc());
        echo "<thead class='table-dark'><tr>";
        foreach ($campos as $campo) {
            echo "<th>" . htmlspecialchars($campo) . "</th>";
        }
        echo "</tr></thead><tbody>";

        // Volver al primer registro y mostrar todos
        $resultado->data_seek(0);
        while ($registro = $resultado->fetch_assoc()) {
            echo "<tr>";
            foreach ($registro as $valor) {
                echo "<td>" . htmlspecialchars($valor) . "</td>";
            }
            echo "</tr>";
        }

        echo "</tbody></table></div>";
    }

    echo "</div>";
    $resultado->free();
}

// Determinar tablas y filtros según rol
$tablas = [];
if ($userRole === 'admin') {
    $tablas = [
        'cliente' => 'Clientes',
        'pedido' => 'Pedidos',
        'compra' => 'Compras',
        'linea_compra' => 'Líneas de Compra',
        'venta' => 'Ventas',
        'linea_venta' => 'Líneas de Venta',
        'reparacion' => 'Reparaciones',
        'linea_reparacion' => 'Líneas de Reparación',
        'movil' => 'Móviles',
        'users' => 'Usuarios'
    ];
} else {
    // Cliente: limitar visión solo a sus datos + catálogo móviles
    // Asegurar que tenemos cliente_id (si no, intentar obtenerlo)
    if (!isset($_SESSION['cliente_id']) && isset($_SESSION['user_id'])) {
        $tmp = $conexion->prepare('SELECT id FROM cliente WHERE user_id = ? LIMIT 1');
        $tmp->bind_param('i', $_SESSION['user_id']);
        if ($tmp->execute()) {
            $tmp->store_result();
            if ($tmp->num_rows === 1) {
                $tmp->bind_result($cid);
                $tmp->fetch();
                $_SESSION['cliente_id'] = $cid;
            }
        }
        $tmp->close();
    }
    $tablas = [
        'cliente' => 'Mi Perfil',
        'pedido' => 'Mis Pedidos',
        'movil' => 'Catálogo de Móviles'
    ];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor de Base de Datos - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>

<body>

    <header class="bg-dark text-white py-4 mb-5 shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <h1 class="mb-0">Visor de Base de Datos - Nevom</h1>
            <div>
                <?php if (!$userName): ?>
                    <a href="signin.php" class="btn btn-outline-light me-2">Iniciar sesión</a>
                    <a href="signupcliente.php" class="btn btn-outline-light">Registrarse</a>
                <?php else: ?>
                    <span class="me-3">Hola, <?= htmlspecialchars($userName) ?> 👤</span>
                    <a href="indexadmin.php" class="btn btn-outline-light me-2">Inicio</a>
                    <a href="logout.php" class="btn btn-outline-light">Cerrar sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php
    // Mostrar mensaje flash desde la sesión (si existe)
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $type = ($f['type'] ?? 'info') === 'success' ? 'success' : 'danger';
        echo "<div class='container'><div class='alert alert-" . htmlspecialchars($type) . " mt-3'>" . htmlspecialchars($f['text']) . "</div></div>";
        unset($_SESSION['flash']);
    }

    // Mostrar tablas con filtros según rol
    if ($userRole === 'admin') {
        foreach ($tablas as $tabla => $titulo) {
            mostrarTabla($conexion, $titulo, $tabla);
        }
    } else {
        // Si no hay sesión de usuario, redirigir a login
        if (!$userName || !isset($_SESSION['user_id'])) {
            header('Location: signin.php');
            exit;
        }
        $clienteId = $_SESSION['cliente_id'] ?? null;
        // Mostrar solo su propio perfil
        if ($clienteId) {
            mostrarTabla($conexion, $tablas['cliente'], 'cliente', 'id=' . intval($clienteId));
            // Pedidos asociados al cliente
            mostrarTabla($conexion, $tablas['pedido'], 'pedido', 'idCliente=' . intval($clienteId));
        }
        // Catálogo móviles completo (no sensible)
        mostrarTabla($conexion, $tablas['movil'], 'movil');
    }

    // Cerrar conexión
    $conexion->close();
    ?>

    <footer>
        <div class="container">
            <div class="text-center text-light opacity-75 py-3">
                <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Visor de Base de Datos</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
