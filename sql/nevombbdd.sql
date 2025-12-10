-- phpMyAdmin SQL Dump
-- Base de datos: `nevombbdd`
-- Script completo con sincronización users <-> cliente mediante user_id

DROP DATABASE IF EXISTS `nevombbdd`;
CREATE DATABASE `nevombbdd` CHARACTER SET utf8 COLLATE utf8_spanish_ci;
USE `nevombbdd`;

-- --------------------------------------------------------
-- Tabla `users` (usuarios para login)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('client','admin') NOT NULL DEFAULT 'client',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- Insertar usuario cliente y admin por defecto (contraseña admin: password)
INSERT INTO `users` (`nombre`, `email`, `password_hash`, `role`) VALUES
('cliente1', 'email@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client');
INSERT INTO `users` (`id`, `nombre`, `email`, `password_hash`, `role`) VALUES
(4, 'Admin admin', 'administrador@email.com', '$2y$10$p0od9rlK929RkL6Z47l2hezFJBIx7smZA0EhDcqhIvMsnFhQA7o/2', 'admin');

-- --------------------------------------------------------
-- Tabla `cliente`
-- --------------------------------------------------------
CREATE TABLE `cliente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nombre` varchar(300) NOT NULL,
  `apellidos` varchar(300) NOT NULL,
  `email` varchar(300) NOT NULL,
  `telefono` varchar(100) NOT NULL,
  `direccion` varchar(300) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_cliente` (`email`),
  UNIQUE KEY `tlf_cliente` (`telefono`),
  UNIQUE KEY `user_id_unique` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `Cliente_User_FK` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci COMMENT='Tabla que almacena los datos sobre los Clientes';

INSERT INTO `cliente` (`user_id`, `nombre`, `apellidos`, `email`, `telefono`, `direccion`) VALUES
(1, 'cliente1', 'apellido1', 'email@email.com', '666666666', 'C/calle nº numero');

-- --------------------------------------------------------
-- Tabla `movil`
-- --------------------------------------------------------
CREATE TABLE `movil` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marca` varchar(200) NOT NULL,
  `modelo` varchar(200) NOT NULL,
  `capacidad` int(11) NOT NULL,
  `stock` int(11) NOT NULL,
  `color` varchar(200) NOT NULL,
  `precio` float NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `movil` (`marca`, `modelo`, `capacidad`, `stock`, `color`, `precio`) VALUES
('marca1', 'modelo1', 16, 1, 'color1', 100);

-- --------------------------------------------------------
-- Tabla `linea_compra`
-- --------------------------------------------------------
CREATE TABLE `linea_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idMovil` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `idCompra` int(11) NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `LineaCompra_Movil_FK` FOREIGN KEY (`idMovil`) REFERENCES `movil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `linea_compra` (`idMovil`, `cantidad`) VALUES
(1, 1);

-- --------------------------------------------------------
-- Tabla `compra`
-- --------------------------------------------------------
CREATE TABLE `compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idLineaCompra` int(11) NULL,
  PRIMARY KEY (`id`),
  KEY `Compra_LineaCompra_FK` (`idLineaCompra`),
  CONSTRAINT `Compra_LineaCompra_FK` FOREIGN KEY (`idLineaCompra`) REFERENCES `linea_compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `compra` (`idLineaCompra`) VALUES
(1);

-- --------------------------------------------------------
-- Tabla `linea_venta`
-- --------------------------------------------------------
CREATE TABLE `linea_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idMovil` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `LineaVenta_Movil_FK` (`idMovil`),
  CONSTRAINT `LineaVenta_Movil_FK` FOREIGN KEY (`idMovil`) REFERENCES `movil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- --------------------------------------------------------
-- Tabla `venta`
-- --------------------------------------------------------
CREATE TABLE `venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idLineaVenta` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Venta_LineaVenta_FK` (`idLineaVenta`),
  CONSTRAINT `Venta_LineaVenta_FK` FOREIGN KEY (`idLineaVenta`) REFERENCES `linea_venta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- --------------------------------------------------------
-- Tabla `linea_reparacion`
-- --------------------------------------------------------
CREATE TABLE `linea_reparacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idMovil` int(11) NOT NULL,
  `tipoReparacion` varchar(300) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `LineaReparacion_Movil_FK` FOREIGN KEY (`idMovil`) REFERENCES `movil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- --------------------------------------------------------
-- Tabla `reparacion`
-- --------------------------------------------------------
CREATE TABLE `reparacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idLineaReparacion` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `Reparacion_LineaReparacion_FK` (`idLineaReparacion`),
  CONSTRAINT `Reparacion_LineaReparacion_FK` FOREIGN KEY (`idLineaReparacion`) REFERENCES `linea_reparacion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- --------------------------------------------------------
-- Tabla `pedido`
-- --------------------------------------------------------
CREATE TABLE `pedido` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numSeguimiento` varchar(50) NOT NULL,
  `precioTotal` float NOT NULL,
  `cantidadTotal` float NOT NULL,
  `formaPago` varchar(300) NOT NULL,
  `idVenta` int(11) DEFAULT NULL,
  `idCompra` int(11) DEFAULT NULL,
  `idReparacion` int(11) DEFAULT NULL,
  `idCliente` int(11) NOT NULL,
  `estado` enum('procesando','preparando','enviado','entregado','aprobado','rechazado','pagado') DEFAULT 'procesando',
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_entrega` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numSeguimiento_unique` (`numSeguimiento`),
  KEY `Pedido_Cliente_FK` (`idCliente`),
  KEY `Pedido_Venta_FK` (`idVenta`),
  KEY `Pedido_Reparacion_FK` (`idReparacion`),
  KEY `Pedido_Compra_FK` (`idCompra`),
  CONSTRAINT `Pedido_Cliente_FK` FOREIGN KEY (`idCliente`) REFERENCES `cliente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Pedido_Venta_FK` FOREIGN KEY (`idVenta`) REFERENCES `venta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Pedido_Reparacion_FK` FOREIGN KEY (`idReparacion`) REFERENCES `reparacion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Pedido_Compra_FK` FOREIGN KEY (`idCompra`) REFERENCES `compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `pedido` (`numSeguimiento`, `precioTotal`, `cantidadTotal`, `formaPago`, `idCompra`, `idCliente`, `estado`) VALUES
('NV-20251208-123456-789', 100, 1, 'tarjeta', 1, 1, 'procesando');

-- Tabla para almacenar transacciones de PayPal
CREATE TABLE IF NOT EXISTS `transaccion_paypal` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT NOT NULL,
  `referencia_paypal` VARCHAR(100),
  `estado` ENUM('INICIADA', 'PAGADO', 'COMPLETADA', 'FALLIDA', 'CANCELADA') DEFAULT 'INICIADA',
  `monto` DECIMAL(10, 2) NOT NULL,
  `moneda` VARCHAR(3) DEFAULT 'EUR',
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `datos_respuesta` LONGTEXT,
  `notas` TEXT,
  FOREIGN KEY (`pedido_id`) REFERENCES `pedido`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `pedido_unico` (`pedido_id`),
  INDEX `idx_estado` (`estado`),
  INDEX `idx_referencia_paypal` (`referencia_paypal`),
  INDEX `idx_fecha` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para almacenar logs de transacciones
CREATE TABLE IF NOT EXISTS `log_paypal` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT,
  `tipo` ENUM('INFO', 'ERROR', 'SUCCESS', 'WARNING') DEFAULT 'INFO',
  `mensaje` TEXT NOT NULL,
  `datos_adicionales` JSON,
  `fecha_log` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pedido` (`pedido_id`),
  INDEX `idx_tipo` (`tipo`),
  INDEX `idx_fecha` (`fecha_log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar foreign keys después de crear todas las tablas
ALTER TABLE linea_compra ADD CONSTRAINT LineaCompra_Compra_FK FOREIGN KEY (idCompra) REFERENCES compra(id) ON DELETE CASCADE ON UPDATE CASCADE;