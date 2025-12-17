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
$iva = $pedido['precioTotal'] - ($pedido['precioTotal'] / 1.21);
$baseImponible = $pedido['precioTotal'] / 1.21;

// Agregar cálculos al array del pedido
$pedido['baseImponible'] = $baseImponible;
$pedido['iva'] = $iva;
$pedido['fecha'] = date('d/m/Y', strtotime($pedido['fecha_creacion']));
$pedido['formaPago'] = ucfirst(strtolower($pedido['formaPago']));

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
            const empresa = <?php echo json_encode($empresa); ?>;

            // Datos del pedido
            const pedido = <?php echo json_encode(array_merge($pedido, ['numero' => $numeroPedido])); ?>;

            // Líneas del pedido
            const lineas = <?php echo json_encode(array_map(function($linea) {
                $subtotal = $linea['precio'] * $linea['cantidad'];
                $iva_linea = $subtotal * 0.21;
                return [
                    $linea['marca'] . ' ' . $linea['modelo'],
                    $linea['cantidad'],
                    number_format($linea['precio'], 2, ',', ' ') . ' €',
                    number_format($subtotal, 2, ',', ' ') . ' €',
                    number_format($iva_linea, 2, ',', ' ') . ' €',
                    number_format($subtotal + $iva_linea, 2, ',', ' ') . ' €'
                ];
            }, $lineas)); ?>;

            // Configurar fuente
            doc.setFont("helvetica", "normal");

            // Header - Empresa con fondo
            doc.setFillColor(248, 249, 250); // Gris claro
            doc.rect(0, 0, 210, 45, 'F'); // Rectángulo de fondo
            doc.setFontSize(16);
            doc.setTextColor(0, 123, 255); // Azul
            doc.text(empresa.nombre || '', 105, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.text(empresa.direccion || '', 105, 30, { align: 'center' });
            doc.text(`NIF: ${empresa.nif || ''} | Tel: ${empresa.telefono || ''} | Email: ${empresa.email || ''}`, 105, 35, { align: 'center' });

            // Línea separadora
            doc.setDrawColor(0, 123, 255);
            doc.setLineWidth(0.5);
            doc.line(10, 50, 200, 50);

            // Título Factura
            doc.setFontSize(14);
            doc.setTextColor(0, 123, 255);
            doc.text("Factura Electrónica", 105, 60, { align: 'center' });

            // Invoice Info - Grid like
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.text("Número de Factura:", 20, 70);
            doc.text(pedido.numero || '', 60, 70);
            doc.text("Forma de Pago:", 20, 75);
            doc.text(pedido.formaPago || '', 60, 75);
            doc.text("Fecha de Emisión:", 110, 70);
            doc.text(pedido.fecha || '', 150, 70);

            // Línea separadora
            doc.line(10, 85, 200, 86);

            // Parties - Vendedor y Cliente con cajas
            let yPos = 105;
            doc.setFontSize(12);
            doc.setTextColor(0, 123, 255);
            doc.text("Vendedor", 20, yPos);
            doc.text("Cliente", 115, yPos);
            doc.setDrawColor(200, 200, 200);
            doc.setLineWidth(0.2);
            doc.rect(10, yPos - 5, 95, 40); // Caja vendedor ampliada
            doc.rect(110, yPos - 5, 85, 40); // Caja cliente ampliada
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            yPos += 10;
            doc.text(empresa.nombre || '', 15, yPos);
            doc.text(pedido.nombre || '', 115, yPos);
            yPos += 5;
            doc.text(empresa.direccion || '', 15, yPos);
            doc.text(pedido.direccion || '', 115, yPos);
            yPos += 5;
            doc.text(`NIF: ${empresa.nif || ''}`, 15, yPos);
            doc.text("NIF: 12345678Z", 115, yPos);
            yPos += 5;
            doc.text(`Tel: ${empresa.telefono || ''}`, 15, yPos);
            doc.text(`Tel: ${pedido.telefono || ''}`, 115, yPos);
            yPos += 5;
            doc.text(`Email: ${empresa.email || ''}`, 15, yPos);
            doc.text(`Email: ${pedido.email || ''}`, 115, yPos);

            // Tabla de productos con filas alternas
            const tableColumn = ["Descripción", "Cantidad", "Precio Unit.", "Base Imponible", "IVA (21%)", "Total"];
            const tableRows = lineas;

            doc.autoTable({
                head: [tableColumn],
                body: tableRows,
                startY: yPos + 15,
                theme: 'grid',
                headStyles: { fillColor: [0, 123, 255], textColor: 255, fontStyle: 'bold' },
                alternateRowStyles: { fillColor: [245, 245, 245] },
                styles: { fontSize: 8, cellPadding: 4 },
                columnStyles: {
                    0: { cellWidth: 50 },
                    1: { cellWidth: 20, halign: 'center' },
                    2: { cellWidth: 25, halign: 'right' },
                    3: { cellWidth: 25, halign: 'right' },
                    4: { cellWidth: 25, halign: 'right' },
                    5: { cellWidth: 25, halign: 'right' }
                }
            });

            // Totales con caja
            let finalY = doc.lastAutoTable.finalY + 10;
            doc.setDrawColor(0, 123, 255);
            doc.setLineWidth(0.5);
            doc.rect(100, finalY - 5, 95, 20); // Caja totales ajustada
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.text(`Base Imponible: ${pedido.baseImponible ? pedido.baseImponible.toFixed(2).replace('.', ',') : '0,00'} €`, 190, finalY, { align: 'right' });
            doc.text(`IVA (21%): ${pedido.iva ? pedido.iva.toFixed(2).replace('.', ',') : '0,00'} €`, 190, finalY + 5, { align: 'right' });
            doc.setFontSize(12);
            doc.setTextColor(0, 123, 255);
            doc.text(`Total Factura: ${pedido.precioTotal ? pedido.precioTotal.toFixed(2).replace('.', ',') : '0,00'} €`, 190, finalY + 10, { align: 'right' });

            // Pie de página
            doc.setFontSize(8);
            doc.setTextColor(128, 128, 128);
            doc.text("Gracias por su compra - Nevom Comercio Electrónico", 105, 280, { align: 'center' });

            // Descargar
            doc.save(`factura_${pedido.numero || 'sin_numero'}.pdf`);
        };
    </script>
</body>
</html>
<?php
exit;
?>