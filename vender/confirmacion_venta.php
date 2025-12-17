<?php
// Incluir conexión externa
require '../config/conexion.php';

// Iniciar sesión
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

// Verificar que el usuario está logueado y es cliente
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

if (!$userName || $userRole !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Obtener ID del pedido
$numSeguimiento = trim($_GET['numSeguimiento'] ?? '');

if (empty($numSeguimiento)) {
    header('Location: vender_movil.php');
    exit;
}

// Obtener detalles del pedido y venta
$sql = "SELECT p.numSeguimiento, p.precioTotal, p.cantidadTotal, p.formaPago, p.estado,
               m.marca, m.modelo, m.capacidad, m.color, m.precio
        FROM pedido p
        JOIN venta v ON p.idVenta = v.id
        JOIN linea_venta lv ON v.idLineaVenta = lv.id
        JOIN movil m ON lv.idMovil = m.id
        WHERE p.numSeguimiento = ? AND p.idCliente = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param('si', $numSeguimiento, $clienteId);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    $_SESSION['mensaje_venta'] = 'Pedido no encontrado o no tienes permiso para verlo';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: vender_movil.php');
    exit;
}

$pedido = $resultado->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Venta - NEVOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .checkmark-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out;
        }

        .checkmark {
            color: white;
            font-size: 50px;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        .info-row {
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 0;
        }

        .info-row:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>

    <!-- Navegación -->
    <?php require '../components/navbar.php';
    renderNavbar(['type' => 'main', 'basePath' => '../']); ?>

    <!-- Contenido Principal -->
    <section class="py-5 mt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <!-- Tarjeta de Confirmación -->
                    <div class="card shadow-lg border-0">
                        <div class="card-body p-5 text-center">
                            <div class="checkmark-circle">
                                <div class="checkmark">✓</div>
                            </div>

                            <h1 class="display-5 fw-bold text-success mb-3">¡Venta Registrada!</h1>
                            <p class="lead text-muted mb-4">
                                Tu solicitud de venta ha sido procesada exitosamente
                            </p>

                            <div class="alert alert-info mb-4">
                                <strong><i class="fas fa-clipboard"></i> Número de Seguimiento:</strong> <?= htmlspecialchars($pedido['numSeguimiento']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Detalles de la Venta -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-mobile-alt"></i> Detalles de Tu Móvil</h5>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-6 fw-bold text-muted">Marca:</div>
                                    <div class="col-6 text-end"><?= htmlspecialchars($pedido['marca']) ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-6 fw-bold text-muted">Modelo:</div>
                                    <div class="col-6 text-end"><?= htmlspecialchars($pedido['modelo']) ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-6 fw-bold text-muted">Capacidad:</div>
                                    <div class="col-6 text-end"><?= htmlspecialchars($pedido['capacidad']) ?> GB</div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-6 fw-bold text-muted">Color:</div>
                                    <div class="col-6 text-end"><?= htmlspecialchars($pedido['color']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Valoración -->
                    <div class="card shadow-sm mt-4 border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-money-bill"></i> Valoración</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <h2 class="display-4 fw-bold text-success">
                                    <?= number_format($pedido['precioTotal'], 2) ?>€
                                </h2>
                                <p class="text-muted">
                                    Método de pago: <span class="badge bg-info"><?= htmlspecialchars($pedido['formaPago']) ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Estado del Pedido -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Estado Actual:</h6>
                                </div>
                                <div>
                                    <span class="badge bg-info fs-6 px-3 py-2">
                                        <?= htmlspecialchars(ucfirst($pedido['estado'])) ?>
                                    </span>
                                </div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                Puedes ver el estado de tu pedido en la página principal
                            </p>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="d-grid gap-2 mt-4">
                        <a href="../index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home"></i> Volver al Inicio
                        </a>
                        <a href="vender_movil.php" class="btn btn-outline-secondary">
                            <i class="fas fa-money-bill"></i> Vender Otro Móvil
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="mt-5">
        <div class="container">
            <div class="text-center text-light opacity-75">
                <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados | Proyecto Educativo</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>

<?php
// Cerrar conexión
$conexion->close();
?>