-- phpMyAdmin SQL Dump
-- Base de datos: `nevombbdd`
-- Script limpio con AUTO_INCREMENT y claves primarias

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

-- Nota: Por seguridad no se incluye aquí un administrador por defecto. Puedes crear uno
-- con un script PHP que use password_hash() o con un INSERT manual incluyendo un hash.

-- --------------------------------------------------------
-- Tabla `cliente`
-- --------------------------------------------------------
CREATE TABLE `cliente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(300) NOT NULL,
  `apellidos` varchar(300) NOT NULL,
  `email` varchar(300) NOT NULL,
  `telefono` varchar(100) NOT NULL,
  `direccion` varchar(300) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_cliente` (`email`),
  UNIQUE KEY `tlf_cliente` (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci COMMENT='Tabla que almacena los datos sobre los Clientes';

INSERT INTO `cliente` (`nombre`, `apellidos`, `email`, `telefono`, `direccion`) VALUES
('cliente1', 'apellido1', 'email@email.com', '666666666', 'C/calle nº numero');

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
  PRIMARY KEY (`id`),
  UNIQUE KEY `idMovil` (`idMovil`),
  CONSTRAINT `LineaCompra_Movil_FK` FOREIGN KEY (`idMovil`) REFERENCES `movil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `linea_compra` (`idMovil`, `cantidad`) VALUES
(1, 1);

-- --------------------------------------------------------
-- Tabla `compra`
-- --------------------------------------------------------
CREATE TABLE `compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idLineaCompra` int(11) NOT NULL,
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
  UNIQUE KEY `idMovil_UNIQUE` (`idMovil`),
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
  `precioTotal` float NOT NULL,
  `cantidadTotal` float NOT NULL,
  `formaPago` varchar(300) NOT NULL,
  `idVenta` int(11) DEFAULT NULL,
  `idCompra` int(11) DEFAULT NULL,
  `idReparacion` int(11) DEFAULT NULL,
  `idCliente` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Pedido_Cliente_FK` (`idCliente`),
  KEY `Pedido_Venta_FK` (`idVenta`),
  KEY `Pedido_Reparacion_FK` (`idReparacion`),
  KEY `Pedido_Compra_FK` (`idCompra`),
  CONSTRAINT `Pedido_Cliente_FK` FOREIGN KEY (`idCliente`) REFERENCES `cliente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Pedido_Venta_FK` FOREIGN KEY (`idVenta`) REFERENCES `venta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Pedido_Reparacion_FK` FOREIGN KEY (`idReparacion`) REFERENCES `reparacion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Pedido_Compra_FK` FOREIGN KEY (`idCompra`) REFERENCES `compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `pedido` (`precioTotal`, `cantidadTotal`, `formaPago`, `idCompra`, `idCliente`) VALUES
(100, 1, 'tarjeta', 1, 1);
