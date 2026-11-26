# OmniStock & POS - Sistema de Gestión de Inventario y Punto de Venta

Bienvenido al repositorio oficial del proyecto **OmniStock POS**. Este sistema web integral ha sido desarrollado para el control de operaciones comerciales, inventario, roles de usuario, simulación de pagos y reportes. Los productos de demostración se cargan en una base local; no aparecen automáticamente en una base remota.

## 🚀 Características Principales

* **Punto de Venta (POS):** Interfaz rápida y ágil para el registro de ventas diarias.
* **Control de Inventario:** Gestión automatizada de stock y catálogo de productos.
* **Gestión de Roles y Accesos:** Permisos diferenciados para administradores y empleados.
* **Reportes y Estadísticas:** Visualización del rendimiento de ventas de manera gráfica.

## 🛠️ Tecnologías Utilizadas

* **PHP:** Lógica del lado del servidor y conexión con la base de datos.
* **JavaScript (ES6+):** Interactividad dinámica en el panel de control y terminal de ventas.
* **HTML5 / CSS3:** Diseño responsivo, moderno, en modo oscuro/claro y adaptado para dispositivos móviles y de escritorio.

## 📋 Estructura del Proyecto

* `admin/`: Paneles de administración y control de usuarios.
* `api/`: Endpoints para el procesamiento de datos y transacciones.
* `assets/`: Archivos multimedia, hojas de estilo CSS y scripts de JavaScript.
* La base de datos se configura por variables de entorno; los productos deben importarse en cada base remota.

## Publicación en Render con MySQL en Railway

Render ejecuta el servicio usando el `Dockerfile` de la raíz, que instala PHP, `pdo_mysql` y `mbstring`. Railway debe proporcionar una base MySQL independiente y persistente.

1. Exporta `omnistock_db` desde phpMyAdmin de XAMPP como SQL e impórtala en la base MySQL de Railway. Mantén el archivo SQL privado y no lo subas a GitHub.
2. En Render → servicio → **Environment**, configura `OMNISTOCK_DB_HOST`, `OMNISTOCK_DB_PORT`, `OMNISTOCK_DB_NAME`, `OMNISTOCK_DB_USER` y `OMNISTOCK_DB_PASSWORD` con los datos de conexión **pública** de Railway. Render no puede usar la dirección privada de Railway ni `localhost`.
3. Configura `APP_ENV=production` y `OMNISTOCK_PUBLIC_DEMO_ENABLED=0`. Para una base nueva, define también `OMNISTOCK_INITIAL_ADMIN_PASSWORD` con una contraseña nueva.
4. Despliega la rama `main` y revisa los logs. Si falta la conexión MySQL, la aplicación debe mostrar un error de conexión, no crear una base SQLite alternativa ni datos de prueba.

No uses scripts PHP públicos para importar SQL ni guardes contraseñas en archivos del repositorio. La conexión pública de MySQL requiere credenciales nuevas, fuertes y guardadas solo en Railway/Render.

La ruta `/demo.php` ofrece una vista pública de solo lectura con productos ficticios. No inicia sesión ni permite modificar el inventario real.
