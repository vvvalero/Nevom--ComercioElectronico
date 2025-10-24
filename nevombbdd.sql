-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-10-2025 a las 11:59:48
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `nevombbdd`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

CREATE TABLE `cliente` (
  `id` int(255) NOT NULL,
  `nombre` varchar(300) NOT NULL,
  `apellidos` varchar(300) NOT NULL,
  `email` varchar(300) NOT NULL,
  `telefono` varchar(100) NOT NULL,
  `direccion` varchar(300) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci COMMENT='Tabla que almacena los datos sobre los Clientes';

--
-- Volcado de datos para la tabla `cliente`
--

INSERT INTO `cliente` (`id`, `nombre`, `apellidos`, `email`, `telefono`, `direccion`) VALUES
(1, 'cliente1', 'apellido1', 'email@email.com', '666666666', 'C/calle nº numero');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compra`
--

CREATE TABLE `compra` (
  `id` int(255) NOT NULL,
  `idLineaCompra` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `compra`
--

INSERT INTO `compra` (`id`, `idLineaCompra`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `linea_compra`
--

CREATE TABLE `linea_compra` (
  `id` int(255) NOT NULL,
  `idMovil` int(255) NOT NULL,
  `cantidad` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `linea_compra`
--

INSERT INTO `linea_compra` (`id`, `idMovil`, `cantidad`) VALUES
(1, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `linea_reparacion`
--

CREATE TABLE `linea_reparacion` (
  `id` int(255) NOT NULL,
  `idMovil` int(255) NOT NULL,
  `tipoReparacion` varchar(300) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `linea_venta`
--

CREATE TABLE `linea_venta` (
  `id` int(255) NOT NULL,
  `idMovil` int(255) NOT NULL,
  `cantidad` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movil`
--

CREATE TABLE `movil` (
  `id` int(255) NOT NULL,
  `marca` varchar(200) NOT NULL,
  `modelo` varchar(200) NOT NULL,
  `capacidad` int(255) NOT NULL,
  `stock` int(255) NOT NULL,
  `color` varchar(200) NOT NULL,
  `precio` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `movil`
--

INSERT INTO `movil` (`id`, `marca`, `modelo`, `capacidad`, `stock`, `color`, `precio`) VALUES
(1, 'marca1', 'modelo1', 16, 1, 'color1', 100);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido`
--

CREATE TABLE `pedido` (
  `id` int(255) NOT NULL,
  `precioTotal` float NOT NULL,
  `cantidadTotal` float NOT NULL,
  `formaPago` varchar(300) NOT NULL,
  `idVenta` int(255) DEFAULT NULL,
  `idCompra` int(255) DEFAULT NULL,
  `idReparacion` int(255) DEFAULT NULL,
  `idCliente` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `pedido`
--

INSERT INTO `pedido` (`id`, `precioTotal`, `cantidadTotal`, `formaPago`, `idVenta`, `idCompra`, `idReparacion`, `idCliente`) VALUES
(1, 100, 1, 'tarjeta', NULL, 1, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reparacion`
--

CREATE TABLE `reparacion` (
  `id` int(255) NOT NULL,
  `idLineaReparacion` int(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta`
--

CREATE TABLE `venta` (
  `id` int(255) NOT NULL,
  `idLineaVenta` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_cliente` (`email`),
  ADD UNIQUE KEY `tlf_cliente` (`telefono`);

--
-- Indices de la tabla `compra`
--
ALTER TABLE `compra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Compra_LineaCompra_FK` (`idLineaCompra`);

--
-- Indices de la tabla `linea_compra`
--
ALTER TABLE `linea_compra`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idMovil` (`idMovil`);

--
-- Indices de la tabla `linea_reparacion`
--
ALTER TABLE `linea_reparacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idMovil_UNIQUE` (`idMovil`);

--
-- Indices de la tabla `linea_venta`
--
ALTER TABLE `linea_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `LineaVenta_Movil_FK` (`idMovil`);

--
-- Indices de la tabla `movil`
--
ALTER TABLE `movil`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pedido`
--
ALTER TABLE `pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Pedido_Cliente_FK` (`idCliente`),
  ADD KEY `Pedido_Venta_FK` (`idVenta`),
  ADD KEY `Pedido_Reparacion_FK` (`idReparacion`),
  ADD KEY `Pedido_Compra_FK` (`idCompra`);

--
-- Indices de la tabla `reparacion`
--
ALTER TABLE `reparacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Reparacion_LineaReparacion_FK` (`idLineaReparacion`);

--
-- Indices de la tabla `venta`
--
ALTER TABLE `venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Venta_LineaVenta_FK` (`idLineaVenta`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `linea_reparacion`
--
ALTER TABLE `linea_reparacion`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `compra`
--
ALTER TABLE `compra`
  ADD CONSTRAINT `Compra_LineaCompra_FK` FOREIGN KEY (`idLineaCompra`) REFERENCES `linea_compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `linea_compra`
--
ALTER TABLE `linea_compra`
  ADD CONSTRAINT `LineaCompra_Movil_FK` FOREIGN KEY (`idMovil`) REFERENCES `movil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `linea_reparacion`
--
ALTER TABLE `linea_reparacion`
  ADD CONSTRAINT `LineaReparacion_Movil_FK` FOREIGN KEY (`idMovil`) REFERENCES `movil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `linea_venta`
--
ALTER TABLE `linea_venta`
  ADD CONSTRAINT `LineaVenta_Movil_FK` FOREIGN KEY (`idMovil`) REFERENCES `movil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedido`
--
ALTER TABLE `pedido`
  ADD CONSTRAINT `Pedido_Cliente_FK` FOREIGN KEY (`idCliente`) REFERENCES `cliente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Pedido_Compra_FK` FOREIGN KEY (`idCompra`) REFERENCES `compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Pedido_Reparacion_FK` FOREIGN KEY (`idReparacion`) REFERENCES `reparacion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Pedido_Venta_FK` FOREIGN KEY (`idVenta`) REFERENCES `venta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `reparacion`
--
ALTER TABLE `reparacion`
  ADD CONSTRAINT `Reparacion_LineaReparacion_FK` FOREIGN KEY (`idLineaReparacion`) REFERENCES `reparacion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `venta`
--
ALTER TABLE `venta`
  ADD CONSTRAINT `Venta_LineaVenta_FK` FOREIGN KEY (`idLineaVenta`) REFERENCES `linea_venta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
