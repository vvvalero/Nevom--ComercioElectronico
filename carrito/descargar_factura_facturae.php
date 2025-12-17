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

// Generar XML Facturae
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

// Root element
$facturae = $dom->createElement('Facturae');
$facturae->setAttribute('xmlns', 'http://www.facturae.es/Facturae/2009/v3.2/Facturae');
$facturae->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
$facturae->setAttribute('xsi:schemaLocation', 'http://www.facturae.es/Facturae/2009/v3.2/Facturae Facturae.xsd');
$dom->appendChild($facturae);

// FileHeader
$fileHeader = $dom->createElement('FileHeader');
$facturae->appendChild($fileHeader);

$schemaVersion = $dom->createElement('SchemaVersion', '3.2');
$fileHeader->appendChild($schemaVersion);

$modality = $dom->createElement('Modality', 'I');
$fileHeader->appendChild($modality);

$invoiceIssuerType = $dom->createElement('InvoiceIssuerType', 'EM');
$fileHeader->appendChild($invoiceIssuerType);

$batch = $dom->createElement('Batch');
$fileHeader->appendChild($batch);

$batchIdentifier = $dom->createElement('BatchIdentifier', 'BATCH_' . $pedido['numSeguimiento']);
$batch->appendChild($batchIdentifier);

$invoicesCount = $dom->createElement('InvoicesCount', '1');
$batch->appendChild($invoicesCount);

$totalInvoicesAmount = $dom->createElement('TotalInvoicesAmount');
$batch->appendChild($totalInvoicesAmount);

$totalAmount = $dom->createElement('TotalAmount', number_format($pedido['precioTotal'], 2, '.', ''));
$totalInvoicesAmount->appendChild($totalAmount);

$totalOutstandingAmount = $dom->createElement('TotalOutstandingAmount');
$batch->appendChild($totalOutstandingAmount);

$outstandingAmount = $dom->createElement('TotalAmount', number_format($pedido['precioTotal'], 2, '.', ''));
$totalOutstandingAmount->appendChild($outstandingAmount);

$totalExecutableAmount = $dom->createElement('TotalExecutableAmount');
$batch->appendChild($totalExecutableAmount);

$executableAmount = $dom->createElement('TotalAmount', number_format($pedido['precioTotal'], 2, '.', ''));
$totalExecutableAmount->appendChild($executableAmount);

$invoiceCurrencyCode = $dom->createElement('InvoiceCurrencyCode', 'EUR');
$batch->appendChild($invoiceCurrencyCode);

// Parties
$parties = $dom->createElement('Parties');
$facturae->appendChild($parties);

// SellerParty
$sellerParty = $dom->createElement('SellerParty');
$parties->appendChild($sellerParty);

$taxIdentification = $dom->createElement('TaxIdentification');
$sellerParty->appendChild($taxIdentification);

$personTypeCode = $dom->createElement('PersonTypeCode', 'J');
$taxIdentification->appendChild($personTypeCode);

$residenceTypeCode = $dom->createElement('ResidenceTypeCode', 'R');
$taxIdentification->appendChild($residenceTypeCode);

$taxIdentificationNumber = $dom->createElement('TaxIdentificationNumber', $empresa['nif']);
$taxIdentification->appendChild($taxIdentificationNumber);

$party = $dom->createElement('Party');
$sellerParty->appendChild($party);

$partyIdentification = $dom->createElement('PartyIdentification', $empresa['nif']);
$party->appendChild($partyIdentification);

$partyName = $dom->createElement('PartyName', $empresa['nombre']);
$party->appendChild($partyName);

$address = $dom->createElement('AddressInSpain');
$party->appendChild($address);

$address = $dom->createElement('Address', $empresa['direccion']);
$party->appendChild($address);

$contactDetails = $dom->createElement('ContactDetails');
$party->appendChild($contactDetails);

$telephone = $dom->createElement('Telephone', $empresa['telefono']);
$contactDetails->appendChild($telephone);

$electronicMail = $dom->createElement('ElectronicMail', $empresa['email']);
$contactDetails->appendChild($electronicMail);

// BuyerParty
$buyerParty = $dom->createElement('BuyerParty');
$parties->appendChild($buyerParty);

$taxIdentification = $dom->createElement('TaxIdentification');
$buyerParty->appendChild($taxIdentification);

$personTypeCode = $dom->createElement('PersonTypeCode', 'F'); // Física
$taxIdentification->appendChild($personTypeCode);

$residenceTypeCode = $dom->createElement('ResidenceTypeCode', 'R');
$taxIdentification->appendChild($residenceTypeCode);

// Asumir NIF genérico para cliente (en producción, obtener de BD)
$taxIdentificationNumber = $dom->createElement('TaxIdentificationNumber', '12345678Z'); // Placeholder
$taxIdentification->appendChild($taxIdentificationNumber);

$party = $dom->createElement('Party');
$buyerParty->appendChild($party);

$partyIdentification = $dom->createElement('PartyIdentification', '12345678Z');
$party->appendChild($partyIdentification);

$partyName = $dom->createElement('PartyName', $pedido['nombre'] . ' ' . $pedido['apellidos']);
$party->appendChild($partyName);

$address = $dom->createElement('AddressInSpain');
$party->appendChild($address);

$address = $dom->createElement('Address', $pedido['direccion']);
$party->appendChild($address);

$contactDetails = $dom->createElement('ContactDetails');
$party->appendChild($contactDetails);

$telephone = $dom->createElement('Telephone', $pedido['telefono']);
$contactDetails->appendChild($telephone);

$electronicMail = $dom->createElement('ElectronicMail', $pedido['email']);
$contactDetails->appendChild($electronicMail);

// Invoices
$invoices = $dom->createElement('Invoices');
$facturae->appendChild($invoices);

$invoice = $dom->createElement('Invoice');
$invoices->appendChild($invoice);

// InvoiceHeader
$invoiceHeader = $dom->createElement('InvoiceHeader');
$invoice->appendChild($invoiceHeader);

$invoiceNumber = $dom->createElement('InvoiceNumber', $pedido['numSeguimiento']);
$invoiceHeader->appendChild($invoiceNumber);

$invoiceDocumentType = $dom->createElement('InvoiceDocumentType', 'FC');
$invoiceHeader->appendChild($invoiceDocumentType);

$invoiceClass = $dom->createElement('InvoiceClass', 'OO');
$invoiceHeader->appendChild($invoiceClass);

$invoiceIssueDate = $dom->createElement('InvoiceIssueDate', date('Y-m-d'));
$invoiceHeader->appendChild($invoiceIssueDate);

$invoiceCurrencyCode = $dom->createElement('InvoiceCurrencyCode', 'EUR');
$invoiceHeader->appendChild($invoiceCurrencyCode);

$taxesOutputs = $dom->createElement('TaxesOutputs');
$invoiceHeader->appendChild($taxesOutputs);

// Calcular IVA (21%)
$iva = $pedido['precioTotal'] * 0.21;
$baseImponible = $pedido['precioTotal'] - $iva;

$tax = $dom->createElement('Tax');
$taxesOutputs->appendChild($tax);

$taxTypeCode = $dom->createElement('TaxTypeCode', '01');
$tax->appendChild($taxTypeCode);

$taxRate = $dom->createElement('TaxRate', '21.00');
$tax->appendChild($taxRate);

$taxableBase = $dom->createElement('TaxableBase');
$tax->appendChild($taxableBase);

$totalAmount = $dom->createElement('TotalAmount', number_format($baseImponible, 2, '.', ''));
$taxableBase->appendChild($totalAmount);

$taxAmount = $dom->createElement('TaxAmount');
$tax->appendChild($taxAmount);

$totalAmount = $dom->createElement('TotalAmount', number_format($iva, 2, '.', ''));
$taxAmount->appendChild($totalAmount);

// InvoiceLines
$invoiceLines = $dom->createElement('InvoiceLines');
$invoice->appendChild($invoiceLines);

$lineNumber = 1;
foreach ($lineas as $linea) {
    $invoiceLine = $dom->createElement('InvoiceLine');
    $invoiceLines->appendChild($invoiceLine);

    $lineItemNumber = $dom->createElement('SequenceNumber', $lineNumber++);
    $invoiceLine->appendChild($lineItemNumber);

    $itemDescription = $dom->createElement('ItemDescription', $linea['marca'] . ' ' . $linea['modelo']);
    $invoiceLine->appendChild($itemDescription);

    $quantity = $dom->createElement('Quantity', $linea['cantidad']);
    $invoiceLine->appendChild($quantity);

    $unitOfMeasure = $dom->createElement('UnitOfMeasure', '01'); // Unidades
    $invoiceLine->appendChild($unitOfMeasure);

    $unitPriceWithoutTax = $dom->createElement('UnitPriceWithoutTax', number_format($linea['precio'], 2, '.', ''));
    $invoiceLine->appendChild($unitPriceWithoutTax);

    $totalCost = $dom->createElement('TotalCost', number_format($linea['precio'] * $linea['cantidad'], 2, '.', ''));
    $invoiceLine->appendChild($totalCost);

    $grossAmount = $dom->createElement('GrossAmount', number_format($linea['precio'] * $linea['cantidad'], 2, '.', ''));
    $invoiceLine->appendChild($grossAmount);

    $taxesOutputs = $dom->createElement('TaxesOutputs');
    $invoiceLine->appendChild($taxesOutputs);

    $tax = $dom->createElement('Tax');
    $taxesOutputs->appendChild($tax);

    $taxTypeCode = $dom->createElement('TaxTypeCode', '01');
    $tax->appendChild($taxTypeCode);

    $taxRate = $dom->createElement('TaxRate', '21.00');
    $tax->appendChild($taxRate);

    $taxableBase = $dom->createElement('TaxableBase');
    $tax->appendChild($taxableBase);

    $totalAmount = $dom->createElement('TotalAmount', number_format($linea['precio'] * $linea['cantidad'], 2, '.', ''));
    $taxableBase->appendChild($totalAmount);

    $taxAmount = $dom->createElement('TaxAmount');
    $tax->appendChild($taxAmount);

    $totalAmount = $dom->createElement('TotalAmount', number_format(($linea['precio'] * $linea['cantidad']) * 0.21, 2, '.', ''));
    $taxAmount->appendChild($totalAmount);
}

// InvoiceTotals
$invoiceTotals = $dom->createElement('InvoiceTotals');
$invoice->appendChild($invoiceTotals);

$totalGrossAmount = $dom->createElement('TotalGrossAmount', number_format($pedido['precioTotal'], 2, '.', ''));
$invoiceTotals->appendChild($totalGrossAmount);

$totalGrossAmountBeforeTaxes = $dom->createElement('TotalGrossAmountBeforeTaxes', number_format($baseImponible, 2, '.', ''));
$invoiceTotals->appendChild($totalGrossAmountBeforeTaxes);

$totalTaxOutputs = $dom->createElement('TotalTaxOutputs', number_format($iva, 2, '.', ''));
$invoiceTotals->appendChild($totalTaxOutputs);

$totalTaxesWithheld = $dom->createElement('TotalTaxesWithheld', '0.00');
$invoiceTotals->appendChild($totalTaxesWithheld);

$invoiceTotal = $dom->createElement('InvoiceTotal', number_format($pedido['precioTotal'], 2, '.', ''));
$invoiceTotals->appendChild($invoiceTotal);

$totalOutstandingAmount = $dom->createElement('TotalOutstandingAmount', number_format($pedido['precioTotal'], 2, '.', ''));
$invoiceTotals->appendChild($totalOutstandingAmount);

$totalExecutableAmount = $dom->createElement('TotalExecutableAmount', number_format($pedido['precioTotal'], 2, '.', ''));
$invoiceTotals->appendChild($totalExecutableAmount);

// Generar y descargar el XML
header('Content-Type: application/xml');
header('Content-Disposition: attachment; filename="factura_' . $numeroPedido . '.xml"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $dom->saveXML();
exit;
?>