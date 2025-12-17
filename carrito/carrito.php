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

// Verificar que el usuario esté logueado
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

// Si no está logueado, redirigir a signin
if (!$userName || $userRole !== 'client' || !$clienteId) {
    $_SESSION['redirect_after_login'] = 'carrito.php';
    header('Location: ../auth/signin.php');
    exit;
}

// Manejar confirmación de eliminación
$showConfirmModal = false;
$confirmProduct = null;
if (isset($_GET['confirm_delete']) && isset($_GET['movil_id'])) {
    $movilIdConfirm = (int) $_GET['movil_id'];
    if ($movilIdConfirm > 0 && isset($_SESSION['carrito'][$movilIdConfirm])) {
        $stmtConfirm = $conexion->prepare("SELECT marca, modelo, capacidad, color, precio FROM movil WHERE id = ?");
        $stmtConfirm->bind_param('i', $movilIdConfirm);
        $stmtConfirm->execute();
        $resultConfirm = $stmtConfirm->get_result();
        $confirmProduct = $resultConfirm->fetch_assoc();
        $stmtConfirm->close();
        if ($confirmProduct) {
            $showConfirmModal = true;
        }
    }
}

// Manejar confirmación de vaciar carrito
$showVaciarModal = false;
if (isset($_GET['vaciar_confirm'])) {
    $showVaciarModal = true;
}

// Inicializar el carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Obtener datos del carrito con información de la base de datos
$productosCarrito = [];
$totalCarrito = 0;
$cantidadTotal = 0;

if (!empty($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $movilId => $cantidad) {
        $stmt = $conexion->prepare("SELECT id, marca, modelo, capacidad, color, precio, stock FROM movil WHERE id = ?");
        $stmt->bind_param('i', $movilId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($movil = $result->fetch_assoc()) {
            // Verificar que la cantidad solicitada no exceda el stock
            $cantidadReal = min($cantidad, $movil['stock']);

            $movil['cantidad'] = $cantidadReal;
            $movil['subtotal'] = $movil['precio'] * $cantidadReal;
            $productosCarrito[] = $movil;

            $totalCarrito += $movil['subtotal'];
            $cantidadTotal += $cantidadReal;

            // Actualizar cantidad en sesión si fue ajustada
            if ($cantidadReal != $cantidad) {
                $_SESSION['carrito'][$movilId] = $cantidadReal;
            }
        }
        $stmt->close();
    }
}

// Obtener datos del cliente
$stmtCliente = $conexion->prepare("SELECT nombre, apellidos, email, telefono, direccion FROM cliente WHERE id = ?");
$stmtCliente->bind_param('i', $clienteId);
$stmtCliente->execute();
$cliente = $stmtCliente->get_result()->fetch_assoc();
$stmtCliente->close();

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Navegación -->
    <?php require '../components/navbar.php';
    renderNavbar(['type' => 'main', 'activeLink' => 'carrito', 'basePath' => '../']); ?>

    <!-- Header -->
    <header class="bg-dark text-white py-5 mb-5 text-center shadow-sm">
        <div class="container">
            <h1 class="mb-0"><i class="fas fa-shopping-cart"></i> Mi Carrito de Compras</h1>
            <p class="mt-2 mb-0 opacity-75">Revisa tus productos y completa tu pedido</p>
        </div>
    </header>

    <div class="container mb-5">
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-<?= $_SESSION['mensaje_tipo'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['mensaje']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje'], $_SESSION['mensaje_tipo']); ?>
        <?php endif; ?>

        <?php if (empty($productosCarrito)): ?>
            <!-- Carrito Vacío -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-lg text-center p-5">
                        <i class="fas fa-shopping-cart" style="font-size: 5rem; opacity: 0.3;"></i>
                        <h3 class="mt-4 mb-3">Tu carrito está vacío</h3>
                        <p class="text-muted mb-4">¡Agrega productos de nuestro catálogo para comenzar!</p>
                        <a href="../index.php#productos" class="btn btn-primary btn-lg rounded-pill px-5">
                            Ver Productos
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Carrito con Productos -->
            <div class="row g-4">
                <!-- Columna de Productos -->
                <div class="col-lg-8">
                    <div class="card shadow-lg">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-box"></i> Productos en tu Carrito</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="productos-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Precio</th>
                                            <th style="width: 150px;">Cantidad</th>
                                            <th>Subtotal</th>
                                            <th style="width: 80px;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productosCarrito as $producto): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-mobile-alt me-3" style="font-size: 2rem;"></i>
                                                        <div>
                                                            <strong><?= htmlspecialchars($producto['marca']) ?> <?= htmlspecialchars($producto['modelo']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?= htmlspecialchars($producto['capacidad']) ?> GB - <?= htmlspecialchars($producto['color']) ?>
                                                            </small>
                                                            <?php if ($producto['stock'] <= 5): ?>
                                                                <br><span class="badge bg-warning text-dark">¡Solo quedan <?= $producto['stock'] ?>!</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle">
                                                    <strong class="text-primary"><?= number_format($producto['precio'], 2) ?>€</strong>
                                                </td>
                                                <td class="align-middle">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="number"
                                                            name="cantidad"
                                                            value="<?= $producto['cantidad'] ?>"
                                                            min="1"
                                                            max="<?= $producto['stock'] ?>"
                                                            class="form-control form-control-sm text-center cantidad-input"
                                                            style="width: 70px;"
                                                            data-movil-id="<?= $producto['id'] ?>"
                                                            data-precio="<?= $producto['precio'] ?>">
                                                        <div class="spinner-border spinner-border-sm text-primary d-none" role="status">
                                                            <span class="visually-hidden">Actualizando...</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle">
                                                    <strong class="text-success"><?= number_format($producto['subtotal'], 2) ?>€</strong>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <a href="carrito.php?confirm_delete=1&movil_id=<?= $producto['id'] ?>" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="../index.php#productos" class="btn btn-outline-secondary">
                                    Seguir Comprando
                                </a>
                                <a href="carrito.php?vaciar_confirm=1" class="btn btn-outline-danger">
                                    <i class="fas fa-trash"></i> Vaciar Carrito
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna de Resumen -->
                <div class="col-lg-4">
                    <!-- Resumen del Pedido -->
                    <div class="card shadow-lg mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Resumen del Pedido</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Productos (<span id="cantidad-productos"><?= $cantidadTotal ?></span>):</span>
                                <strong id="total-productos"><?= number_format($totalCarrito, 2) ?>€</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Total:</h5>
                                <h4 class="mb-0 text-primary" id="total-final">
                                    <?= number_format($totalCarrito >= 50 ? $totalCarrito : $totalCarrito + 5, 2) ?>€
                                </h4>
                            </div>
                            <form method="post" action="procesar_compra.php" id="formProcesarCompra" data-total="<?= number_format($totalCarrito >= 50 ? $totalCarrito : $totalCarrito + 5, 2) ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Forma de Pago *</label>
                                    <select name="forma_pago" class="form-select" required id="forma_pago_select">
                                        <option value="">-- Selecciona --</option>
                                        <option value="tarjeta">Tarjeta de Crédito/Débito</option>
                                        <option value="transferencia">Transferencia Bancaria</option>
                                        <option value="efectivo">Efectivo (Contrareembolso)</option>
                                        <option value="paypal">PayPal</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill" id="btn_finalizar_compra">
                                    Finalizar Compra
                                </button>
                            </form>

                            <!-- Información de PayPal -->
                            <div class="alert alert-info mt-3" id="info_paypal" style="display: none;">
                                <strong><i class="fas fa-lock"></i> PayPal Seguro:</strong> Serás redirigido a PayPal para completar el pago de forma segura.
                            </div>
                        </div>
                    </div>
                    <!-- Información de Entrega -->
                    <div class="card shadow-lg">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-map-marker-alt"></i> Información de Entrega</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Nombre:</strong><br><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']) ?></p>
                            <p class="mb-2"><strong>Email:</strong><br><?= htmlspecialchars($cliente['email']) ?></p>
                            <p class="mb-2"><strong>Teléfono:</strong><br><?= htmlspecialchars($cliente['telefono']) ?></p>
                            <p class="mb-0"><strong>Dirección:</strong><br><?= htmlspecialchars($cliente['direccion']) ?></p>
                            <hr>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <hr class="border-light opacity-25 my-4">
            <div class="text-center text-light opacity-75">
                <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Modal de Confirmación de Compra -->
    <div class="modal fade" id="confirmarCompraModal" tabindex="-1" aria-labelledby="confirmarCompraModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="confirmarCompraModalLabel">
                        <i class="fas fa-shopping-cart me-2"></i>Confirmar Compra
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-info-circle me-2"></i>Resumen de tu Pedido
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p><i class="fas fa-euro-sign me-2"></i><strong>Total a Pagar:</strong> <span id="modal-total" class="text-primary fs-5"></span></p>
                                    <p><i class="fas fa-credit-card me-2"></i><strong>Forma de Pago:</strong> <span id="modal-forma-pago"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><i class="fas fa-boxes me-2"></i><strong>Cantidad Total:</strong> <span id="modal-cantidad"></span></p>
                                    <p><i class="fas fa-truck me-2"></i><strong>Envío:</strong> <span id="modal-envio"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card border-secondary">
                        <div class="card-header bg-dark text-white">
                            <i class="fas fa-mobile-alt me-2"></i>Productos en tu Carrito
                        </div>
                        <div class="card-body">
                            <div id="modal-productos-lista">
                                <!-- Productos se cargarán aquí dinámicamente -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-success" id="btn-confirmar-compra">
                        <i class="fas fa-check me-2"></i>Confirmar Compra
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para actualizar el carrito automáticamente
        let timeoutId;

        document.querySelectorAll('.cantidad-input').forEach(input => {
            input.addEventListener('change', function() {
                actualizarCantidad(this);
            });

            // También actualizar cuando se usan las flechas del input
            input.addEventListener('input', function() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    actualizarCantidad(this);
                }, 800); // Espera 800ms después de que el usuario deje de escribir
            });
        });

        function actualizarCantidad(input) {
            const movilId = input.dataset.movilId;
            const cantidad = parseInt(input.value);
            const precio = parseFloat(input.dataset.precio);
            const row = input.closest('tr');
            const spinner = row.querySelector('.spinner-border');

            // Validación básica
            if (isNaN(cantidad) || cantidad < 0) {
                return;
            }

            // Mostrar spinner
            spinner.classList.remove('d-none');
            input.disabled = true;

            // Enviar petición AJAX
            const formData = new FormData();
            formData.append('movil_id', movilId);
            formData.append('cantidad', cantidad);
            formData.append('ajax', 'true');

            fetch('actualizar_carrito.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.accion === 'eliminar') {
                            // Eliminar la fila con animación
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                location.reload(); // Recargar para actualizar todo
                            }, 300);
                        } else {
                            // Actualizar subtotal de la fila
                            const subtotalElement = row.querySelector('td:nth-child(4) strong');
                            subtotalElement.textContent = data.subtotal + '€';

                            // Actualizar totales generales
                            actualizarTotales(data);

                            // Mostrar mensaje de éxito (opcional, comentado para no saturar)
                            // mostrarMensaje('success', data.mensaje);
                        }
                    } else {
                        // Error: restaurar valor anterior o ajustar según stock
                        if (data.stock_disponible !== undefined) {
                            input.value = data.stock_disponible;
                            input.max = data.stock_disponible;
                        }
                        mostrarMensaje('warning', data.mensaje);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('danger', 'Error al actualizar el carrito');
                })
                .finally(() => {
                    // Ocultar spinner
                    spinner.classList.add('d-none');
                    input.disabled = false;
                });
        }

        function actualizarTotales(data) {
            // Actualizar cantidad en el navbar
            const cantidadCarrito = document.getElementById('cantidad-carrito');
            if (cantidadCarrito) {
                cantidadCarrito.textContent = data.cantidad_total;
            }

            // Actualizar cantidad de productos en resumen
            const cantidadProductos = document.getElementById('cantidad-productos');
            if (cantidadProductos) {
                cantidadProductos.textContent = data.cantidad_total;
            }

            // Actualizar subtotal de productos
            const totalProductos = document.getElementById('total-productos');
            if (totalProductos) {
                totalProductos.textContent = data.total_carrito + '€';
            }

            // Actualizar envío
            const costoEnvio = document.getElementById('costo-envio');
            if (costoEnvio) {
                if (data.envio_gratis) {
                    costoEnvio.innerHTML = 'GRATIS';
                    costoEnvio.classList.add('text-success');
                } else {
                    costoEnvio.textContent = data.costo_envio + '€';
                    costoEnvio.classList.remove('text-success');
                }
            }

            // Actualizar mensaje de envío gratis
            const mensajeEnvio = document.getElementById('mensaje-envio');
            if (mensajeEnvio) {
                if (data.envio_gratis) {
                    mensajeEnvio.textContent = '✅ ¡Tienes envío gratis!';
                    mensajeEnvio.classList.remove('text-muted');
                    mensajeEnvio.classList.add('text-success');
                } else {
                    mensajeEnvio.innerHTML = `<i class="fas fa-lightbulb"></i> Añade ${data.falta_envio_gratis}€ más para envío gratis`;
                    mensajeEnvio.classList.remove('text-success');
                    mensajeEnvio.classList.add('text-muted');
                }
            }

            // Actualizar total final
            const totalFinal = document.getElementById('total-final');
            if (totalFinal) {
                totalFinal.textContent = data.total_final + '€';
            }

            // Actualizar el total en el confirmación del botón
            const formCompra = document.getElementById('formProcesarCompra');
            if (formCompra) {
                formCompra.dataset.total = data.total_final;
            }
        }

        function mostrarMensaje(tipo, mensaje) {
            // Crear alerta temporal
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto-eliminar después de 3 segundos
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }

        // Validación del formulario de compra
        document.getElementById('formProcesarCompra')?.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevenir submit inmediato

            const formaPago = this.querySelector('[name="forma_pago"]').value;
            if (!formaPago) {
                alert('Por favor, selecciona una forma de pago');
                return false;
            }

            // Si selecciona PayPal, redirigir directamente
            if (formaPago === 'paypal') {
                window.location.href = '../paypal/procesar_pago.php';
                return false;
            }

            // Para otros métodos de pago, mostrar modal de confirmación
            mostrarModalConfirmacion(formaPago, this.dataset.total);
        });

        function mostrarModalConfirmacion(formaPago, total) {
            // Actualizar información en la modal
            document.getElementById('modal-total').textContent = total + '€';
            document.getElementById('modal-forma-pago').textContent = formaPago.charAt(0).toUpperCase() + formaPago.slice(1);
            document.getElementById('modal-cantidad').textContent = document.getElementById('cantidad-productos').textContent;
            
            const totalNum = parseFloat(total.replace(',', '.'));
            const envio = totalNum >= 50 ? 'Gratis' : '5.00€';
            document.getElementById('modal-envio').textContent = envio;

            // Poblar lista de productos
            const productosLista = document.getElementById('modal-productos-lista');
            productosLista.innerHTML = ''; // Limpiar contenido anterior

            // Obtener productos de la tabla
            const filasProductos = document.querySelectorAll('#productos-table tbody tr');
            if (filasProductos.length === 0) {
                productosLista.innerHTML = '<p class="text-muted">No hay productos en el carrito.</p>';
            } else {
                filasProductos.forEach(fila => {
                    const celdas = fila.querySelectorAll('td');
                    if (celdas.length >= 5) {
                        const productoDiv = document.createElement('div');
                        productoDiv.className = 'd-flex justify-content-between align-items-center border-bottom pb-2 mb-2';
                        
                        const infoProducto = celdas[0].querySelector('.d-flex');
                        const nombreProducto = infoProducto ? infoProducto.querySelector('strong').textContent + ' ' + infoProducto.querySelectorAll('small')[0].textContent : 'Producto';
                        const precio = celdas[1].querySelector('strong').textContent;
                        const cantidad = celdas[2].querySelector('input').value;
                        const subtotal = celdas[3].querySelector('strong').textContent;

                        productoDiv.innerHTML = `
                            <div class="d-flex align-items-center">
                                <i class="fas fa-mobile-alt me-3 text-primary"></i>
                                <div>
                                    <strong>${nombreProducto}</strong><br>
                                    <small class="text-muted">Cantidad: ${cantidad}</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div>${precio}</div>
                                <small class="text-muted">Subtotal: ${subtotal}</small>
                            </div>
                        `;
                        productosLista.appendChild(productoDiv);
                    }
                });
            }

            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('confirmarCompraModal'));
            modal.show();

            // Configurar botón de confirmar
            document.getElementById('btn-confirmar-compra').onclick = function() {
                // Cerrar modal
                modal.hide();
                // Enviar formulario
                document.getElementById('formProcesarCompra').submit();
            };
        }

        // Mostrar/ocultar información de PayPal según la forma de pago seleccionada
        const formaPagoSelect = document.getElementById('forma_pago_select');
        const infoPyapal = document.getElementById('info_paypal');

        if (formaPagoSelect) {
            formaPagoSelect.addEventListener('change', function() {
                if (this.value === 'paypal') {
                    infoPyapal.style.display = 'block';
                } else {
                    infoPyapal.style.display = 'none';
                }
            });
        }
    </script>

    <?php if ($showConfirmModal): ?>
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center;">
        <div class="card shadow-lg" style="max-width: 500px; width: 90%;">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h5>
            </div>
            <div class="card-body text-center" style="background-color: white;">
                <i class="fas fa-mobile-alt" style="font-size: 3rem; color: #dc3545; opacity: 0.7;"></i>
                <h5 class="mt-3 mb-4">¿Estás seguro de que quieres eliminar este producto?</h5>
                <div class="mb-4">
                    <h6 class="text-primary"><?= htmlspecialchars($confirmProduct['marca']) ?> <?= htmlspecialchars($confirmProduct['modelo']) ?></h6>
                    <p class="text-muted mb-2">
                        <?= htmlspecialchars($confirmProduct['capacidad']) ?> GB - <?= htmlspecialchars($confirmProduct['color']) ?>
                    </p>
                    <p class="text-success fw-bold fs-5"><?= number_format($confirmProduct['precio'], 2) ?>€</p>
                </div>
                <p class="text-muted">Esta acción no se puede deshacer.</p>
            </div>
            <div class="card-footer bg-light text-center" style="background-color: white !important;">
                <a href="carrito.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <form method="post" action="eliminar_carrito.php" class="d-inline">
                    <input type="hidden" name="movil_id" value="<?= $movilIdConfirm ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Sí, Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showVaciarModal): ?>
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center;">
        <div class="card shadow-lg" style="max-width: 500px; width: 90%;">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Vaciar Carrito</h5>
            </div>
            <div class="card-body text-center" style="background-color: white;">
                <i class="fas fa-shopping-cart" style="font-size: 3rem; color: #dc3545; opacity: 0.7;"></i>
                <h5 class="mt-3 mb-4">¿Estás seguro de que quieres vaciar todo el carrito?</h5>
                <p class="text-muted">Esta acción eliminará todos los productos del carrito y no se puede deshacer.</p>
            </div>
            <div class="card-footer bg-light text-center" style="background-color: white !important;">
                <a href="carrito.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <form method="post" action="vaciar_carrito.php" class="d-inline">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Sí, Vaciar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>

</html>