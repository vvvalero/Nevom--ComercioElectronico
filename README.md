# Nevom - Comercio Electrónico de Móviles

Sistema de comercio electrónico desarrollado en PHP para la venta y gestión de dispositivos móviles.

## 📋 Descripción

Nevom es una plataforma web para la gestión de un comercio de móviles que incluye funcionalidades de venta, compra y reparación de dispositivos. El sistema cuenta con dos tipos de usuarios: clientes y administradores.

## 🗂️ Estructura del Proyecto

```
nevom/
├── admin/                      # Panel de administración
│   ├── addMovil.php           # Añadir nuevos móviles al catálogo
│   ├── indexadmin.php         # Panel principal del administrador
│   └── visorBBDD.php          # Visualizador de la base de datos
│
├── assets/                     # Recursos estáticos
│   └── css/
│       └── style.css          # Estilos de la aplicación
│
├── auth/                       # Sistema de autenticación
│   ├── logout.php             # Cerrar sesión
│   ├── signin.php             # Inicio de sesión
│   ├── signupadmin.php        # Registro de administradores
│   └── signupcliente.php      # Registro de clientes
│
├── carrito/                    # Gestión del carrito de compras
│   ├── actualizar_carrito.php # Actualizar cantidades
│   ├── agregar_carrito.php    # Añadir productos al carrito
│   ├── carrito.php            # Vista del carrito
│   ├── eliminar_carrito.php   # Eliminar productos del carrito
│   ├── procesar_compra.php    # Finalizar compra
│   └── vaciar_carrito.php     # Vaciar todo el carrito
│
├── config/                     # Configuración
│   └── conexion.php           # Conexión a la base de datos
│
├── sql/                        # Base de datos
│   └── nevombbdd.sql          # Script de creación de la BBDD
│
└── index.php                   # Página principal
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

### Pasos de Instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/vvvalero/Nevom--ComercioElectronico.git
cd Nevom--ComercioElectronico
```

2. **Configurar el servidor web**
   - Copia el proyecto a tu carpeta de servidor web (ej: `htdocs` en XAMPP)
   - O configura un virtual host apuntando a la carpeta del proyecto

3. **Acceder a la aplicación**
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
- ✅ Procesar compras
- ✅ Gestión del perfil

### Para Administradores
- ✅ Panel de administración
- ✅ Añadir nuevos móviles al catálogo
- ✅ Visualizar y gestionar la base de datos
- ✅ Gestión de pedidos
- ✅ Registro de nuevos administradores

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

## 🔧 Configuración Adicional

### Modificar la configuración de la base de datos
Edita `config/conexion.php` según tu entorno:
```php
$hostname = 'localhost';  // Servidor MySQL
$usuario = 'root';        // Usuario MySQL
$password = '';           // Contraseña MySQL
$bbdd = 'nevombbdd';      // Nombre de la base de datos
```
