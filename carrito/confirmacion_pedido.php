<?php
require '../config/conexion.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
$userRole = $_SESSION['user_role'] ?? null;
if ($userRole !== 'client') {
    header('Location: ../auth/signin.php');
    exit;
}

// Obtener numero_pedido
$numeroPedido = $_GET['numero_pedido'] ?? '';
if (!$numeroPedido) {
    header('Location: ../index.php');
    exit;
}

try {
    // Obtener datos del pedido
    $stmt = $conexion->prepare("
        SELECT p.*, c.nombre, c.apellidos, c.email, c.telefono, c.direccion
        FROM pedido p
        JOIN cliente c ON p.idCliente = c.id
        WHERE p.numSeguimiento = ?
    ");
    $stmt->bind_param('s', $numeroPedido);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pedido) {
        throw new Exception("Pedido no encontrado");
    }

    // Obtener líneas del pedido (productos comprados)
    $stmt = $conexion->prepare("
        SELECT lc.cantidad, m.marca, m.modelo, m.precio
        FROM linea_compra lc
        JOIN movil m ON lc.idMovil = m.id
        WHERE lc.idCompra = ?
    ");
    $stmt->bind_param('i', $pedido['idCompra']);
    $stmt->execute();
    $lineas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['mensaje'] = "Error: " . $e->getMessage();
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: ../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php require '../components/navbar.php';
    renderNavbar(['type' => 'main', 'basePath' => '../']); ?>

    <!-- Header -->
    <header class="page-header wave-light success">
        <div class="container">
            <h1>✅ ¡Pedido Confirmado!</h1>
            <p>Tu pedido ha sido procesado correctamente</p>
        </div>
    </header>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Mensaje de éxito -->
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                    <span class="me-3" style="font-size: 1.5rem;">✓</span>
                    <div>
                        <strong>¡Pedido procesado correctamente!</strong>
                        <p class="mb-0 small opacity-75">¡Gracias por tu compra! Tu pedido ha sido creado.</p>
                    </div>
                </div>

                <!-- Detalles del pedido -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard"></i> Detalles del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <small class="text-muted d-block">Número de Pedido</small>
                                    <strong class="fs-5"><?php echo htmlspecialchars($pedido['numSeguimiento']); ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <small class="text-muted d-block">Total Pagado</small>
                                    <strong class="fs-5 text-success">€<?php echo number_format($pedido['precioTotal'], 2, ',', '.'); ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <small class="text-muted d-block">Método de Pago</small>
                                    <strong style="color: <?php echo $pedido['formaPago'] === 'paypal' ? '#0070ba' : '#28a745'; ?>;">
                                        <?php
                                        $icon = $pedido['formaPago'] === 'paypal' ? '<i class="fas fa-credit-card"></i>' : ($pedido['formaPago'] === 'tarjeta' ? '<i class="fas fa-credit-card"></i>' : '<i class="fas fa-money-bill"></i>');
                                        echo $icon . ' ' . ucfirst($pedido['formaPago']);
                                        ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <small class="text-muted d-block">Fecha</small>
                                    <strong><?php echo date('d/m/Y H:i'); ?></strong>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted d-block">Estado</small>
                                        <span class="badge bg-info fs-6"><?php echo ucfirst($pedido['estado']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="visualizar_factura.php?numero_pedido=<?php echo urlencode($numeroPedido); ?>" class="btn btn-info btn-lg">
                        <i class="fas fa-eye"></i> Visualizar Factura
                    </a>
                    <a href="descargar_factura.php?numero_pedido=<?php echo urlencode($numeroPedido); ?>" class="btn btn-success btn-lg">
                        <i class="fas fa-file"></i> Descargar Factura (Facturae)
                    </a>
                    <button onclick="descargarPDF('<?php echo urlencode($numeroPedido); ?>')" class="btn btn-danger btn-lg">
                        <i class="fas fa-file-pdf"></i> Descargar Factura (PDF)
                    </button>
                    <a href="../index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-home"></i> Volver a la Tienda
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer mt-auto">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Nevom - Todos los derechos reservados</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function descargarPDF(numeroPedido) {
            var iframe = document.createElement('iframe');
            iframe.src = 'descargar_factura_pdf.php?numero_pedido=' + numeroPedido;
            iframe.style.display = 'none';
            document.body.appendChild(iframe);
            // Opcional: remover el iframe después de un tiempo
            setTimeout(function() {
                document.body.removeChild(iframe);
            }, 5000);
        }
    </script>
</body>

</html>