<?php
require '../config/conexion.php';

// Verificar que sea una petición GET con numero_pedido
$numeroPedido = $_GET['numero_pedido'] ?? '';
if (!$numeroPedido) {
    die('Número de pedido no válido');
}

try {
    // Obtener datos del pedido con cliente
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
        die('Pedido no encontrado');
    }

    // Obtener líneas del pedido
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
    die('Error al obtener datos del pedido: ' . $e->getMessage());
}

// Datos de la empresa (vendedor) - Hardcodeados para el ejemplo
$empresa = [
    'nombre' => 'Nevom Comercio Electrónico S.L.',
    'nif' => 'B12345678',
    'direccion' => 'P.º de los Estudiantes, s/n, 02006 Albacete',
    'telefono' => '912345678',
    'email' => 'info@nevom.com'
];

// Calcular IVA (21%)
$iva = $pedido['precioTotal'] * 0.21;
$baseImponible = $pedido['precioTotal'] - $iva;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura - <?php echo htmlspecialchars($numeroPedido); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/nevom/assets/css/style.css" rel="stylesheet">
</head>

<body style="font-family: 'Roboto', sans-serif; background-color: var(--gray-50); color: var(--gray-800);">
    <?php
    // Iniciar sesión para el navbar
    if (session_status() === PHP_SESSION_NONE) session_start();
    require '../components/navbar.php';
    renderNavbar(['type' => 'cliente', 'activeLink' => 'movil', 'basePath' => '../']);
    ?>

    <div class="container-fluid py-4">
        <div class="invoice-container">
            <!-- Header -->
            <div class="invoice-header">
                <div class="company-name"><?php echo htmlspecialchars($empresa['nombre']); ?></div>
                <div class="company-details">
                    <div><?php echo htmlspecialchars($empresa['direccion']); ?></div>
                    <div>NIF: <?php echo htmlspecialchars($empresa['nif']); ?> | Tel: <?php echo htmlspecialchars($empresa['telefono']); ?> | Email: <?php echo htmlspecialchars($empresa['email']); ?></div>
                </div>
                <div class="invoice-title">Factura Electrónica</div>
            </div>

            <!-- Invoice Info -->
            <div class="invoice-info">
                <div class="info-grid">
                    <div>
                        <div class="info-item">
                            <span class="info-label">Número de Factura:</span>
                            <span><?php echo htmlspecialchars($numeroPedido); ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="info-item">
                            <span class="info-label">Fecha de Emisión:</span>
                            <span><?php echo date('d/m/Y'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Forma de Pago:</span>
                            <span><?php echo htmlspecialchars(ucfirst($pedido['formaPago'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parties -->
            <div class="parties-section">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="party-box">
                            <div class="party-title">Vendedor</div>
                            <div class="party-details">
                                <strong><?php echo htmlspecialchars($empresa['nombre']); ?></strong><br>
                                <?php echo htmlspecialchars($empresa['direccion']); ?><br>
                                NIF: <?php echo htmlspecialchars($empresa['nif']); ?><br>
                                Tel: <?php echo htmlspecialchars($empresa['telefono']); ?><br>
                                Email: <?php echo htmlspecialchars($empresa['email']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="party-box">
                            <div class="party-title">Cliente</div>
                            <div class="party-details">
                                <strong><?php echo htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellidos']); ?></strong><br>
                                <?php echo htmlspecialchars($pedido['direccion']); ?><br>
                                NIF: 12345678Z<br>
                                Tel: <?php echo htmlspecialchars($pedido['telefono']); ?><br>
                                Email: <?php echo htmlspecialchars($pedido['email']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="products-section">
                <h5 class="mb-3" style="color: #007bff; font-weight: 600;">Detalle de Productos</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>Base Imponible</th>
                                <th>IVA (21%)</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineas as $linea): ?>
                                <?php
                                $subtotal = $linea['precio'] * $linea['cantidad'];
                                $iva_linea = $subtotal * 0.21;
                                ?>
                                <tr>
                                    <td class="product-name"><?php echo htmlspecialchars($linea['marca'] . ' ' . $linea['modelo']); ?></td>
                                    <td><?php echo htmlspecialchars($linea['cantidad']); ?></td>
                                    <td><?php echo number_format($linea['precio'], 2, ',', ' '); ?> €</td>
                                    <td><?php echo number_format($subtotal, 2, ',', ' '); ?> €</td>
                                    <td><?php echo number_format($iva_linea, 2, ',', ' '); ?> €</td>
                                    <td><strong><?php echo number_format($subtotal + $iva_linea, 2, ',', ' '); ?> €</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Totals -->
            <div class="totals-section">
                <div class="totals-table">
                    <div class="totals-row">
                        <span>Base Imponible:</span>
                        <span><?php echo number_format($baseImponible, 2, ',', ' '); ?> €</span>
                    </div>
                    <div class="totals-row">
                        <span>IVA (21%):</span>
                        <span><?php echo number_format($iva, 2, ',', ' '); ?> €</span>
                    </div>
                    <div class="totals-row total">
                        <span>Total Factura:</span>
                        <span><?php echo number_format($pedido['precioTotal'], 2, ',', ' '); ?> €</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions-section">
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="descargar_factura_facturae.php?numero_pedido=<?php echo urlencode($numeroPedido); ?>" class="btn btn-success">
                        <i class="fas fa-file"></i> Descargar Factura (Facturae)
                    </a>
                    <button onclick="descargarPDF('<?php echo urlencode($numeroPedido); ?>')" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Descargar Factura (PDF)
                    </button>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <a href="confirmacion_pedido.php?numero_pedido=<?php echo urlencode($numeroPedido); ?>" class="btn btn-secondary">
                        ← Volver
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function descargarPDF(numeroPedido) {
            var iframe = document.createElement('iframe');
            iframe.src = 'descargar_factura_pdf.php?numero_pedido=' + numeroPedido;
            iframe.style.display = 'none';
            document.body.appendChild(iframe);
        }
    </script>
</body>

</html>