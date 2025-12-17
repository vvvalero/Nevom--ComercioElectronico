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
    'direccion' => 'Calle Ejemplo 123, 28001 Madrid, España',
    'telefono' => '912345678',
    'email' => 'info@nevom.com'
];

// Calcular IVA (21%)
$iva = $pedido['precioTotal'] * 0.21;
$baseImponible = $pedido['precioTotal'] - $iva;

// Generar HTML con jsPDF para crear PDF
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generando PDF...</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <p>Generando PDF...</p>

    <script>
        window.onload = function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Datos de la empresa
            const empresa = {
                nombre: "<?php echo addslashes($empresa['nombre']); ?>",
                nif: "<?php echo addslashes($empresa['nif']); ?>",
                direccion: "<?php echo addslashes($empresa['direccion']); ?>",
                telefono: "<?php echo addslashes($empresa['telefono']); ?>",
                email: "<?php echo addslashes($empresa['email']); ?>"
            };

            // Datos del pedido
            const pedido = {
                numero: "<?php echo addslashes($numeroPedido); ?>",
                fecha: "<?php echo date('d/m/Y'); ?>",
                nombre: "<?php echo addslashes($pedido['nombre'] . ' ' . $pedido['apellidos']); ?>",
                direccion: "<?php echo addslashes($pedido['direccion']); ?>",
                telefono: "<?php echo addslashes($pedido['telefono']); ?>",
                email: "<?php echo addslashes($pedido['email']); ?>",
                precioTotal: <?php echo $pedido['precioTotal']; ?>,
                baseImponible: <?php echo $baseImponible; ?>,
                iva: <?php echo $iva; ?>
            };

            // Líneas del pedido
            const lineas = [
                <?php
                $lineas_js = [];
                foreach ($lineas as $linea) {
                    $subtotal = $linea['precio'] * $linea['cantidad'];
                    $iva_linea = $subtotal * 0.21;
                    $lineas_js[] = '["' . addslashes($linea['marca'] . ' ' . $linea['modelo']) . '", ' . $linea['cantidad'] . ', "' . number_format($linea['precio'], 2, ',', ' ') . ' €", "' . number_format($subtotal, 2, ',', ' ') . ' €", "' . number_format($iva_linea, 2, ',', ' ') . ' €", "' . number_format($subtotal + $iva_linea, 2, ',', ' ') . ' €"]';
                }
                echo implode(',', $lineas_js);
                ?>
            ];

            // Configurar fuente
            doc.setFont("helvetica", "normal");

            // Título de la empresa
            doc.setFontSize(16);
            doc.setTextColor(0, 123, 255);
            doc.text(empresa.nombre, 105, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.text(empresa.direccion, 105, 30, { align: 'center' });
            doc.text(`NIF: ${empresa.nif} | Tel: ${empresa.telefono} | Email: ${empresa.email}`, 105, 35, { align: 'center' });

            // Título de la factura
            doc.setFontSize(14);
            doc.setTextColor(0, 123, 255);
            doc.text("Factura Electrónica", 105, 50, { align: 'center' });
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.text(`Número de Factura: ${pedido.numero} | Serie: NV | Fecha: ${pedido.fecha}`, 105, 60, { align: 'center' });

            // Vendedor
            doc.setFontSize(12);
            doc.setTextColor(0, 123, 255);
            doc.text("Vendedor", 20, 80);
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.text(empresa.nombre, 20, 90);
            doc.text(empresa.direccion, 20, 95);
            doc.text(`NIF: ${empresa.nif}`, 20, 100);
            doc.text(`Tel: ${empresa.telefono}`, 20, 105);
            doc.text(`Email: ${empresa.email}`, 20, 110);

            // Cliente
            doc.setFontSize(12);
            doc.setTextColor(0, 123, 255);
            doc.text("Cliente", 110, 80);
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.text(pedido.nombre, 110, 90);
            doc.text(pedido.direccion, 110, 95);
            doc.text("NIF: 12345678Z", 110, 100);
            doc.text(`Tel: ${pedido.telefono}`, 110, 105);
            doc.text(`Email: ${pedido.email}`, 110, 110);

            // Tabla de productos
            const tableColumn = ["Descripción", "Cantidad", "Precio Unit.", "Base Imponible", "IVA (21%)", "Total"];
            const tableRows = lineas;

            doc.autoTable({
                head: [tableColumn],
                body: tableRows,
                startY: 125,
                theme: 'grid',
                headStyles: { fillColor: [248, 249, 250], textColor: 0 },
                styles: { fontSize: 8 }
            });

            // Totales
            let finalY = doc.lastAutoTable.finalY + 10;
            doc.setFontSize(10);
            doc.text(`Base Imponible: ${pedido.baseImponible.toFixed(2).replace('.', ',')} €`, 140, finalY);
            doc.text(`IVA (21%): ${pedido.iva.toFixed(2).replace('.', ',')} €`, 140, finalY + 5);
            doc.setFontSize(12);
            doc.setTextColor(0, 123, 255);
            doc.text(`Total Factura: ${pedido.precioTotal.toFixed(2).replace('.', ',')} €`, 140, finalY + 10);

            // Descargar
            doc.save(`factura_${pedido.numero}.pdf`);
        };
    </script>
</body>
</html>
<?php
exit;
?>