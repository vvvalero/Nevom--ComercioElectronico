<?php
    // Incluir conexión externa
    require 'conexion.php';

    /**
     * Ejecuta una consulta SQL y devuelve el resultado.
     */
    function ejecutarConsulta($conexion, $sql) {
        $resultado = $conexion->query($sql);
        if (!$resultado) {
            throw new Exception("Error en la consulta ({$conexion->errno}): {$conexion->error}");
        }
        return $resultado;
    }

    /**
     * Genera una tabla HTML con Bootstrap a partir de una consulta SQL.
     */
    function mostrarTabla($conexion, $titulo, $tabla) {
        $sql = "SELECT * FROM $tabla";
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

    // Listado de tablas a mostrar
    $tablas = [
        'cliente' => 'Clientes',
        'pedido' => 'Pedidos',
        'compra' => 'Compras',
        'linea_compra' => 'Líneas de Compra',
        'venta' => 'Ventas',
        'linea_venta' => 'Líneas de Venta',
        'reparacion' => 'Reparaciones',
        'linea_reparacion' => 'Líneas de Reparación',
        'movil' => 'Móviles'
    ];
?>

<!-- EMPIEZA EL HTML DE LA PÁGINA !-->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Tablas - Nevombbdd</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <header class="bg-dark text-white py-4 mb-5 text-center shadow-sm">
        <h1>Gestión de Base de Datos Nevom</h1>
    </header>

    <?php
    // Mostrar todas las tablas dinámicamente
    foreach ($tablas as $tabla => $titulo) {
        mostrarTabla($conexion, $titulo, $tabla);
    }

    // Cerrar conexión
    $conexion->close();
    ?>
</body>
</html>
