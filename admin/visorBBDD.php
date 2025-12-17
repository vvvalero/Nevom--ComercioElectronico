<?php
// Incluir conexión externa
require '../config/conexion.php';
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
            foreach ($registro as $nombreCampo => $valor) {
                // Si es el campo estado, mostrar con badge de color
                if ($nombreCampo === 'estado') {
                    $badgeClass = match ($valor) {
                        'procesando' => 'bg-info',
                        'preparando' => 'bg-warning text-dark',
                        'enviado' => 'bg-primary',
                        'entregado' => 'bg-success',
                        default => 'bg-secondary'
                    };
                    echo "<td><span class='badge $badgeClass'>" . htmlspecialchars($valor) . "</span></td>";
                } else {
                    echo "<td>" . htmlspecialchars($valor) . "</td>";
                }
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navegación -->
    <?php
    require '../components/navbar.php';
    if ($userRole === 'admin') {
        renderNavbar(['type' => 'admin', 'basePath' => '../']);
    } else {
        renderNavbar(['type' => 'main', 'basePath' => '../']);
    }
    ?>

    <header class="bg-dark text-white py-4 mb-5 shadow-sm" style="margin-top: 20px;">
        <div class="container text-center">
            <h1 class="mb-0"><i class="fas fa-chart-bar"></i> Visor de Base de Datos</h1>
            <p class="mb-0 mt-2 opacity-75">Consulta la información del sistema</p>
        </div>
    </header>

    <div class="container">
        <?php
        // Mostrar mensaje desde la sesión (si existe)
        if (!empty($_SESSION['mensaje'])) {
            $tipo = $_SESSION['mensaje_tipo'] ?? 'info';
            $claseMensaje = match ($tipo) {
                'success' => 'alert-success',
                'danger' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info'
            };
            echo "<div class='alert $claseMensaje alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($_SESSION['mensaje']);
            echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            unset($_SESSION['mensaje']);
            unset($_SESSION['mensaje_tipo']);
        }

        // Mostrar tablas con filtros según rol
        if ($userRole === 'admin') {
            foreach ($tablas as $tabla => $titulo) {
                mostrarTabla($conexion, $titulo, $tabla);
            }
        } else {
            // Si no hay sesión de usuario, redirigir a login
            if (!$userName || !isset($_SESSION['user_id'])) {
                header('Location: ../auth/signin.php');
                exit;
            }
            $clienteId = $_SESSION['cliente_id'] ?? null;
            // Mostrar solo su propio perfil
            if ($clienteId) {
                mostrarTabla($conexion, $tablas['cliente'], 'cliente', 'id=' . intval($clienteId));
                // Pedidos asociados al cliente (con estado visible)
                mostrarTabla($conexion, $tablas['pedido'], 'pedido', 'idCliente=' . intval($clienteId));
            }
            // Catálogo móviles completo (no sensible)
            mostrarTabla($conexion, $tablas['movil'], 'movil');
        }

        // Cerrar conexión
        $conexion->close();
        ?>
    </div>

    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Visor de Base de Datos</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>