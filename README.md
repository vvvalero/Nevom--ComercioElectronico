# Nevom - Comercio Electrónico de Móviles

Sistema de comercio electrónico desarrollado en PHP para la venta y gestión de dispositivos móviles.

## 📋 Descripción

Nevom es una plataforma web para la gestión de un comercio de móviles que incluye funcionalidades de venta, compra y reparación de dispositivos. El sistema cuenta con dos tipos de usuarios: clientes y administradores. Incluye integración con PayPal para pagos seguros y generación de facturas en PDF.

## 🗂️ Estructura del Proyecto

```
nevom/
├── admin/                      # Panel de administración
│   ├── actualizar_estado_pedido.php  # Actualizar estado de pedidos
│   ├── actualizar_estado_venta.php   # Actualizar estado de ventas
│   ├── addMovil.php            # Añadir nuevos móviles al catálogo
│   ├── ajustar_precio_venta.php      # Ajustar precios de venta
│   ├── gestionar_compras.php   # Gestionar compras
│   ├── gestionar_ventas.php    # Gestionar ventas
│   ├── indexadmin.php          # Panel principal del administrador
│   └── visorBBDD.php           # Visualizador de la base de datos
│
├── assets/                     # Recursos estáticos
│   └── css/
│       └── style.css           # Estilos de la aplicación
│
├── auth/                       # Sistema de autenticación
│   ├── logout.php              # Cerrar sesión
│   ├── signin.php              # Inicio de sesión
│   ├── signupadmin.php         # Registro de administradores
│   └── signupcliente.php       # Registro de clientes
│
├── carrito/                    # Gestión del carrito de compras
│   ├── actualizar_carrito.php  # Actualizar cantidades
│   ├── agregar_carrito.php     # Añadir productos al carrito
│   ├── carrito.php             # Vista del carrito
│   ├── confirmacion_pedido.php # Confirmación de pedido
│   ├── descargar_factura_pdf.php # Descargar factura en PDF
│   ├── descargar_factura.php   # Descargar factura
│   ├── eliminar_carrito.php    # Eliminar productos del carrito
│   ├── procesar_compra.php     # Finalizar compra
│   ├── vaciar_carrito.php      # Vaciar todo el carrito
│   └── visualizar_factura.php  # Visualizar factura
│
├── cliente/                    # Área de cliente
│   ├── mis_pedidos.php         # Ver pedidos realizados
│   └── perfil.php              # Gestionar perfil de usuario
│
├── components/                 # Componentes reutilizables
│   └── navbar.php              # Barra de navegación
│
├── config/                     # Configuración
│   ├── conexion.php            # Conexión a la base de datos
│   ├── paypal_config.php       # Configuración de PayPal
│   └── procesador_paypal.php   # Procesador de pagos PayPal
│
├── paypal/                     # Integración PayPal
│   ├── cancelacion_pago.php    # Cancelación de pago
│   ├── confirmacion_pago.php   # Confirmación de pago
│   └── procesar_pago.php       # Procesar pago
│
├── sql/                        # Base de datos
│   └── nevombbdd.sql           # Script de creación de la BBDD
│
├── vender/                     # Funcionalidades de venta
│   ├── confirmacion_venta.php  # Confirmación de venta
│   ├── procesar_venta.php      # Procesar venta
│   └── vender_movil.php        # Vender móvil
│
├── index.php                   # Página principal
└── README.md                   # Este archivo
```

## 🗄️ Base de Datos

El sistema utiliza MySQL con las siguientes tablas:

- **users**: Usuarios del sistema (clientes y administradores)
- **cliente**: Información detallada de los clientes
- **movil**: Catálogo de móviles (marca, modelo, capacidad, stock, color, precio)
- **linea_compra**: Líneas de productos en compras
- **compra**: Compras realizadas
- **linea_venta**: Líneas de productos en ventas
- **venta**: Ventas realizadas
- **linea_reparacion**: Líneas de reparaciones
- **reparacion**: Reparaciones solicitadas
- **pedido**: Pedidos generales (ventas, compras o reparaciones)

## 🚀 Instalación

### Requisitos Previos

- PHP 7.0 o superior
- MySQL 5.7 o superior
- Servidor web (Apache/Nginx)
- phpMyAdmin (opcional, para gestión visual de la BBDD)
- Cuenta de PayPal para integración de pagos (opcional)

### Pasos de Instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/vvvalero/Nevom--ComercioElectronico.git
cd Nevom--ComercioElectronico
```

2. **Configurar la base de datos**
   - Importa el archivo `sql/nevombbdd.sql` en tu servidor MySQL
   - O utiliza phpMyAdmin para ejecutar el script

3. **Configurar el servidor web**
   - Copia el proyecto a tu carpeta de servidor web (ej: `htdocs` en XAMPP)
   - O configura un virtual host apuntando a la carpeta del proyecto

4. **Configurar PayPal (opcional)**
   - Edita `config/paypal_config.php` con tus credenciales de PayPal

5. **Acceder a la aplicación**
   - Abre tu navegador en: `http://localhost/nevom/`

## 👤 Usuarios por Defecto

El sistema incluye dos usuarios de prueba:

**Cliente:**
- Email: `email@email.com`
- Contraseña: `password`

**Administrador:**
- Email: `administrador@email.com`
- Contraseña: `password`

## 🛠️ Funcionalidades

### Para Clientes
- ✅ Registro e inicio de sesión
- ✅ Visualización del catálogo de móviles
- ✅ Añadir productos al carrito
- ✅ Modificar cantidades en el carrito
- ✅ Eliminar productos del carrito
- ✅ Procesar compras con PayPal
- ✅ Gestión del perfil
- ✅ Ver pedidos realizados
- ✅ Descargar facturas en PDF
- ✅ Visualizar facturas

### Para Administradores
- ✅ Panel de administración
- ✅ Añadir nuevos móviles al catálogo
- ✅ Visualizar y gestionar la base de datos
- ✅ Gestión de pedidos y estados
- ✅ Registro de nuevos administradores
- ✅ Gestionar compras y ventas
- ✅ Ajustar precios de venta
- ✅ Procesar ventas directas

## 💳 Integración PayPal

El sistema incluye integración completa con PayPal para pagos seguros:
- Procesamiento de pagos en tiempo real
- Confirmación automática de transacciones
- Cancelación de pagos
- Generación de facturas tras pago exitoso

## 🔐 Sistema de Autenticación

El proyecto utiliza:
- Contraseñas hasheadas con `password_hash()` (bcrypt)
- Sesiones PHP para mantener usuarios autenticados
- Roles de usuario (client/admin) para control de acceso

## 📝 Notas Técnicas

- El proyecto utiliza PHP puro sin frameworks
- MySQLi para la conexión a la base de datos
- Sesiones PHP para el carrito de compras
- Charset UTF-8 para soporte de caracteres especiales
- Generación de PDFs con librerías PHP nativas

## 🔧 Configuración Adicional

### Modificar la configuración de la base de datos
Edita `config/conexion.php` según tu entorno:
```php
$hostname = 'localhost';  // Servidor MySQL
$usuario = 'root';        // Usuario MySQL
$password = '';           // Contraseña MySQL
$bbdd = 'nevombbdd';      // Nombre de la base de datos
```

### Configuración de PayPal
Edita `config/paypal_config.php`:
```php
// Credenciales de PayPal
define('PAYPAL_CLIENT_ID', 'tu_client_id');
define('PAYPAL_CLIENT_SECRET', 'tu_client_secret');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' o 'live'
```
