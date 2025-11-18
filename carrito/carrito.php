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
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="../index.php">
                📱 Nevom
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php#productos">Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="carrito.php">
                            🛒 Carrito (<span id="cantidad-carrito"><?= $cantidadTotal ?></span>)
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                            👤 <?= htmlspecialchars($userName) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../admin/visorBBDD.php">Mis Pedidos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="bg-dark text-white py-5 mb-5 text-center shadow-sm" style="margin-top: 56px;">
        <div class="container">
            <h1 class="mb-0">🛒 Mi Carrito de Compras</h1>
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
                        <div style="font-size: 5rem; opacity: 0.3;">🛒</div>
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
                            <h5 class="mb-0">📦 Productos en tu Carrito</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
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
                                                        <div class="me-3" style="font-size: 2rem;">📱</div>
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
                                                    <form method="post" action="eliminar_carrito.php" class="d-inline">
                                                        <input type="hidden" name="movil_id" value="<?= $producto['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('¿Eliminar este producto del carrito?')">
                                                            🗑️
                                                        </button>
                                                    </form>
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
                                <form method="post" action="vaciar_carrito.php" class="d-inline">
                                    <button type="submit" class="btn btn-outline-danger" 
                                            onclick="return confirm('¿Vaciar todo el carrito?')">
                                        🗑️ Vaciar Carrito
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna de Resumen -->
                <div class="col-lg-4">
                    <!-- Resumen del Pedido -->
                    <div class="card shadow-lg mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">📊 Resumen del Pedido</h5>
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
                                    <select name="forma_pago" class="form-select" required>
                                        <option value="">-- Selecciona --</option>
                                        <option value="tarjeta">Tarjeta de Crédito/Débito</option>
                                        <option value="transferencia">Transferencia Bancaria</option>
                                        <option value="efectivo">Efectivo (Contrareembolso)</option>
                                        <option value="paypal">PayPal</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill">
                                    Finalizar Compra
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Información de Entrega -->
                    <div class="card shadow-lg">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">📍 Información de Entrega</h5>
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
                    mensajeEnvio.innerHTML = `💡 Añade ${data.falta_envio_gratis}€ más para envío gratis`;
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
            const formaPago = this.querySelector('[name="forma_pago"]').value;
            if (!formaPago) {
                e.preventDefault();
                alert('Por favor, selecciona una forma de pago');
                return false;
            }
            
            // Confirmación de compra
            const total = this.dataset.total || '<?= number_format($totalCarrito >= 50 ? $totalCarrito : $totalCarrito + 5, 2) ?>';
            if (!confirm('¿Confirmar la compra por ' + total + '€?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>

</body>

</html>