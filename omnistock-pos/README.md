# OmniStock & POS v1.0.0

**Estado:** MVP funcional para demostración y evaluación; requiere validación del hosting antes de una operación productiva.  
**Versión:** 1.0.0  
**Última actualización:** 25 de noviembre de 2026

## 📋 Resumen

OmniStock POS es un **sistema web de punto de venta** construido con **PHP 8+**, JavaScript nativo y **MySQL 5.7+**, orientado a pequeños y medianos negocios. Los pagos con tarjeta y transferencia son simulaciones; no debe usarse para procesar pagos reales sin integrar y certificar un proveedor de pago.

**Características principales:**
- ✅ **Tres métodos de pago:** Efectivo, Tarjeta (simulado), Transferencia (CLABE validada)
- ✅ **Control de inventario** con alertas de stock crítico
- ✅ **Sistema RBAC** (Role-Based Access Control): Admin + Vendedores
- ✅ **Comunicación interna** entre admin y vendedores para reabastecimiento
- ✅ **Reportes financieros** (admin-only)
- ✅ **Sesiones seguras** con CSRF tokens y Bcrypt
- ✅ **Transacciones ACID** con MySQL InnoDB
- ✅ **Validación automática de base de datos** en primer acceso
- ✅ **Reseñas, mapa y resumen administrativo** integrados con el POS existente

### Módulos de experiencia y sucursales

- **Reseñas:** abre `/omnistock-pos/resenas` con una sesión autenticada. Cada cuenta puede publicar o actualizar una reseña; se almacena con la migración aditiva de esquema versión 3.
- **Mapa:** abre `/omnistock-pos/mapa-sucursales`. Leaflet/OpenStreetMap muestra las ubicaciones mantenidas en `config/branches.php`; dirección y coordenadas se presentan en marcadores y en el panel admin.
- **Resumen admin:** abre `/omnistock-pos/admin/sucursal-resumen.php`; agrega ventas de los últimos 30 días e inventario disponibles. La narración es una simulación local basada en reglas, no una conexión a un proveedor de IA, y señala que las ventas no están asignadas a sucursales individuales.
- **Conversor del POS:** convierte entre `USD`, `EUR`, `GBP` y `MXN`. El administrador puede ajustar los factores de referencia desde el resumen de sucursales; los valores iniciales demostrativos son USD 17.20, EUR 18.60 y GBP 22.00 MXN por unidad. No son cotizaciones en tiempo real ni se emplean para calcular o cobrar ventas.
- **Rutas amigables:** las rutas `/resenas` y `/mapa-sucursales` usan las reglas de `.htaccess`; Apache debe tener habilitado `mod_rewrite` y permitir `AllowOverride`.

La interfaz conserva la arquitectura PHP/JavaScript/CSS de este repositorio y organiza los módulos en `components/atoms`, `components/molecules`, `components/organisms`, `components/templates` y `components/pages`; no introduce una aplicación React/Vite paralela al sistema autenticado. La configuración de tasas se guarda de forma aditiva en el esquema versión 4.

## 📌 Inicio Rápido

### 1. Configuración de Variables de Entorno

```bash
cp .env.example .env
# Edita .env con tus credenciales:
#   - OMNISTOCK_DB_HOST, OMNISTOCK_DB_PORT, OMNISTOCK_DB_NAME, OMNISTOCK_DB_USER, OMNISTOCK_DB_PASSWORD
#   - OMNISTOCK_INITIAL_ADMIN_PASSWORD (mín. 12 caracteres)
#   - OMNISTOCK_SEED_DEMO_SELLER=1 (opcional)
```

### 2. Crear Base de Datos

```bash
mysql -u root -p
CREATE DATABASE omnistock_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 3. Acceder a la Aplicación

```
http://localhost/omnistock-pos/login.php
```

**La base de datos se inicializa automáticamente.** No es necesario importar scripts SQL.

### Requisitos

- **PHP:** 8.0 o superior
- **MySQL:** 5.7+ o MariaDB 10.2+
- **Extensiones PHP:** pdo_mysql, json, filter, hash
- **Servidor:** Apache, Nginx o equiv.

## 📚 Documentación

- **[MANUAL_TECNICO.md](./MANUAL_TECNICO.md)** → Arquitectura, API endpoints, troubleshooting
- **[FASES_DESARROLLO.md](./FASES_DESARROLLO.md)** → Especificaciones del proyecto y fases de desarrollo
- **[CHECKLIST_PRODUCCION.md](./CHECKLIST_PRODUCCION.md)** → Verificación pre-despliegue en servidor público
- **[.env.example](./.env.example)** → Variables de configuración

---

## Detalles Técnicos (Documentación Original)

### Interfaz web para punto de venta

Lee el catálogo desde MySQL mediante PHP y procesa ventas con control transaccional de existencias.

### Requisitos de Base de Datos

> **NOTA:** A partir de v1.0.0, la mayoría de scripts SQL se ejecutan automáticamente. Sin embargo, si migras desde versiones antiguas, consulta las secciones de **Migración** abajo.

- Base de datos `omnistock_db` creada (automático en primer acceso)
- Tabla `usuarios` (auto-creada en primer acceso)
- Tablas `ventas` y `detalle_ventas` (auto-creadas en primer acceso)
- Tabla `mensajes_almacen` (auto-creada en primer acceso)
- Tabla `productos` (auto-creada en primer acceso)

## Configuración

Por defecto, la conexión utiliza `localhost:3306`, base `omnistock_db`, usuario `root` y contraseña vacía (configuración frecuente de XAMPP local). Sobrescribe los valores en el entorno de PHP/Apache para otros datos:

| Variable | Valor predeterminado |
|---|---|
| `OMNISTOCK_DB_HOST` | `localhost` |
| `OMNISTOCK_DB_PORT` | `3306` |
| `OMNISTOCK_DB_NAME` | `omnistock_db` |
| `OMNISTOCK_DB_USER` | `root` |
| `OMNISTOCK_DB_PASSWORD` | vacío |

No publiques credenciales ni expongas el puerto de MySQL a internet. En un entorno compartido o de producción, utiliza un usuario MySQL dedicado con permiso de solo lectura para el endpoint del catálogo y una contraseña segura.

Al iniciar una conexión, OmniStock crea las tablas que falten (incluido un catálogo base `productos`) y aplica migraciones aditivas idempotentes para ventas, referencias de transferencia y mensajería. Las tablas `productos` ya existentes se conservan sin cambios. El usuario MySQL necesita permisos `CREATE`, `ALTER` e `INSERT` en la base. No se eliminan tablas ni datos. En instalaciones nuevas no se crea una cuenta admin con contraseña pública: define `OMNISTOCK_INITIAL_ADMIN_PASSWORD` y, opcionalmente, `OMNISTOCK_INITIAL_ADMIN_USERNAME`/`OMNISTOCK_INITIAL_ADMIN_NAME` para crearla con hash al primer inicio.

Para habilitar el perfil de prueba solicitado, define explícitamente `OMNISTOCK_SEED_DEMO_SELLER=1` y `OMNISTOCK_DEFAULT_SELLER_PASSWORD` antes de iniciar PHP/Apache. La contraseña debe tener entre 12 y 200 caracteres; el registro es idempotente y se guarda con `password_hash()`. Para una recuperación puntual de las cuentas iniciales, configura también `OMNISTOCK_RESET_INITIAL_USERS_ONCE=1`; la migración de esquema versión 6 actualiza sus hashes existentes una sola vez (admin y vendedor si está habilitado). Retira esa variable después de confirmar el inicio de sesión. No guardes contraseñas en archivos del proyecto.

## Acceso de usuarios

1. Crea la tabla de usuarios importando `database/schema.sql` en `omnistock_db`.
2. Desde una consola local ejecuta `php bin/create-user.php <usuario> <vendedor|admin>` y escribe una contraseña de al menos 12 caracteres cuando se solicite. La contraseña se almacena con `password_hash()` y `PASSWORD_DEFAULT`; no se guarda en texto plano.
3. Abre `http://localhost/omnistock-pos/login.php`. El inicio de sesión usa `password_verify()`, sesiones PHP y mensajes genéricos ante credenciales incorrectas.
4. Los usuarios autenticados como `vendedor` que intenten abrir `/omnistock-pos/admin/` son enviados al POS. Los usuarios no autenticados deben iniciar sesión.
5. Cierra la sesión con el icono del perfil en el POS.

Desde **Administración → Usuarios**, un administrador puede crear perfiles `vendedor`; el formulario solicita el nombre, correo (si la tabla lo utiliza) y una contraseña de al menos 12 caracteres, almacenada con `password_hash()`. El vendedor inicia sesión con el correo o nombre de usuario configurado en la base. No se crean cuentas ni contraseñas predeterminadas.

### Accesos rápidos de demostración

El `.env.example` mantiene el panel público desactivado por defecto. Con `OMNISTOCK_PUBLIC_DEMO_ENABLED=1`, el formulario muestra las credenciales configuradas y puede crear cuentas demo en desarrollo. Para una presentación escolar pública, también se puede activar explícitamente `OMNISTOCK_ALLOW_PUBLIC_CREDENTIALS=1` en producción: cualquier visitante podrá ingresar con esas cuentas y modificar los datos. Esta opción no crea cuentas demo en producción. Úsala solo para la presentación, sin datos reales, y desactívala y cambia las contraseñas después.

La ruta `/demo.php` es una página aparte con datos ficticios de solo lectura; no sustituye la aplicación principal ni permite usar el POS, el mapa o las reseñas.

### Reabastecimiento de stock crítico

En el catálogo administrativo, **Atención: Productos con Stock Crítico** muestra los productos bajo su mínimo. **Pedir y avisar al vendedor** genera una orden pendiente y envía a todos los vendedores un aviso de que el producto está en camino. La tabla `ordenes_compra` se crea con la migración de esquema versión 5.

### Inicio automático en VS Code

Al abrir esta carpeta como espacio de trabajo en VS Code, la tarea `OmniStock: iniciar y abrir interfaz` comprueba `http://127.0.0.1:8765/login.php`, inicia el servidor PHP si hace falta y abre una sola página cuando el login responde. Si VS Code pregunta si se permiten tareas automáticas para esta carpeta, selecciona **Permitir**. Deben estar disponibles PHP (en `PATH`, `OMNISTOCK_PHP_EXE`, `C:\xampp\php\php.exe` o `C:\xampp2\php\php.exe`) y MySQL en el puerto configurado. La comprobación de inicio verifica la página web; no sustituye la comprobación de conexión a MySQL al iniciar sesión.

La cuenta creada con rol `admin` puede administrar el catálogo desde `/omnistock-pos/admin/`. El panel detecta los nombres comunes de columnas de `productos` documentados abajo; requiere columnas para ID, nombre y precio. La subida de imágenes requiere que el proceso de Apache pueda escribir en `public/img/productos/`; configura `upload_max_filesize=5M` y `post_max_size=6M` en PHP para permitir el tamaño máximo documentado. Configura credenciales de base de datos seguras para los entornos compartidos y habilita HTTPS en producción.

El panel permite alta, consulta, edición y eliminación de productos. Las imágenes aceptadas son PNG, JPG/JPEG y WEBP de hasta 5 MB; valida extensión, tipo MIME y contenido real de imagen en el servidor. El cambio rápido de existencias se procesa sin abandonar el panel. Si tu tabla usa otros nombres de columnas, agrega los alias correspondientes en `config/product_catalog.php`.

El endpoint de catálogo usa PDO con consultas preparadas y un límite de 300 productos; expone únicamente campos normalizados. Si existe `categorias` con columnas reconocidas de ID y nombre y `productos.categoria_id`, carga las etiquetas desde esa tabla y relaciona los productos mediante esa clave. También admite el esquema sencillo con una categoría de texto en `productos`. Las pestañas muestran conteos, incluyendo categorías vacías de la tabla relacional, y priorizan Bebidas, Botanas, Galletas y Abarrotes cuando existen. La API acepta `?categoria=<id>` para filtrar con parámetro preparado. Las tarjetas muestran descripción, existencias, alerta de stock bajo al alcanzar `stock_minimo` y un respaldo visual de imagen. Las vistas `views/productos.php` y `views/vendedor_pos.php` reutilizan el POS autenticado de la raíz, así que el carrito, cobro seguro y ticket funcionan igual en ambas rutas. La ruta `views/vendedor_pos.php` admite perfiles vendedor y administrador, sin ofrecer funciones de gestión administrativa en la pantalla de caja. El vendedor puede solicitar reabastecimiento desde las tarjetas de stock bajo; la solicitud se valida en el servidor, incluye nombre de vendedor y producto, y se envía al administrador. Las fotografías del catálogo se almacenan en `public/img/productos/`. El endpoint reconoce estos nombres comunes:

- Nombre: `nombre`, `name`, `producto`, `descripcion_producto`
- Precio: `precio`, `precio_venta`, `precio_unitario`, `price`
- Imagen: `imagen`, `image`, `foto`, `ruta_imagen`, `url_imagen`, `imagen_url`, `imagen_producto`, `image_url`, `product_image`
- Descripción: `descripcion`, `description`, `detalle`
- Categoría: `categoria`, `category`, `nombre_categoria`
- ID de categoría: `categoria_id`, `id_categoria`, `category_id`
- Código: `codigo`, `sku`, `barcode`, `codigo_barras`
- Existencias: `stock`, `existencia`, `cantidad`
- Stock mínimo: `stock_minimo`, `stock_minimo_unidades`, `stock_minimum`, `minimo_stock`

El endpoint ignora filas sin nombre o precio numérico, y solo entrega rutas locales de imagen cuando el archivo existe y es legible en `public/img/productos/` o `assets/img/productos/`. Si la columna de imagen está vacía, intenta encontrar `producto_<id>.jpg`, `.jpeg`, `.png` o `.webp` en esas carpetas. La vista del POS resuelve las imágenes respecto a la raíz de la aplicación, mantiene el contenedor en proporción fija y presenta el nombre del producto con un ícono SVG si la ruta está vacía o la imagen no puede cargarse. Si el esquema real usa otros nombres, actualiza los alias en `config/product_catalog.php`.

### Cargar productos de ejemplo

Importa `database/seed_products.sql` desde phpMyAdmin o la consola de MySQL para crear las categorías Bebidas, Botanas, Galletas, Abarrotes y Dulces, e insertar 21 productos de ejemplo. El script presupone que `productos` incluye `nombre`, `descripcion`, `precio`, `stock`, `imagen_url` y `categoria_id`; no inserta de nuevo productos cuyo nombre ya exista. Si ya existe `categorias`, debe tener columnas `id` y `nombre`.

Las rutas de imagen incluidas son referencias locales en `public/img/productos/`. Coloca allí los archivos correspondientes con esos nombres para mostrar las fotos; si aún no están disponibles, el catálogo mostrará su imagen de respaldo local.

## Ejecución en XAMPP

1. Copia o conserva el directorio en `C:\xampp\htdocs\omnistock-pos` (o configura un alias de Apache para servirlo).
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Verifica que MySQL esté configurado en el puerto `3306` y que `omnistock_db.productos` tenga registros.
4. Abre `http://localhost/omnistock-pos/views/vendedor_pos.php` para entrar a la vista de caja del vendedor. `views/productos.php` y la ruta raíz también siguen disponibles.
5. El enlace de reportes aparece en el POS únicamente para administradores. Si un vendedor abre directamente `admin/reports.php`, se le redirige al POS con un aviso de acceso denegado.
6. Si el catálogo no carga, revisa `http://localhost/omnistock-pos/api/products.php` y el log de errores de PHP/Apache.

### Validación local

Desde PowerShell, en la raíz del proyecto, ejecuta:

```powershell
.\scripts\validate.ps1
```

El comando comprueba la sintaxis de PHP y JavaScript y ejecuta las pruebas unitarias básicas en `tests/run.php`. Estas pruebas no sustituyen una prueba de integración de venta y stock en una base aislada.

La tasa inicial de IVA es 16% y se puede ajustar desde el panel del ticket. El formato de moneda inicial es MXN. Antes de usar en caja real, confirma la moneda y tasa fiscal aplicables, y configura la zona horaria del establecimiento.

### Transferencias bancarias

El método **Transferencia** se configura mediante `OMNISTOCK_TRANSFER_BANK`, `OMNISTOCK_TRANSFER_CLABE` y `OMNISTOCK_TRANSFER_HOLDER`; `OMNISTOCK_TRANSFER_CONCEPT`, `OMNISTOCK_TRANSFER_REFERENCE` y `OMNISTOCK_TRANSFER_AUTHORIZATION` controlan el concepto, la referencia precargada y el folio simulado. `.env.example` trae los datos ficticios solicitados para la demo; reemplázalos antes de operar con una cuenta real. La CLABE se valida únicamente como una cadena de exactamente 18 dígitos, no se valida su dígito verificador ni titularidad. La referencia de hasta 50 caracteres se guarda en `ventas.referencia_transferencia`. La cuenta regresiva de 30 segundos no confirma el depósito; el cajero debe verificarlo con el banco. Aplica la migración de referencia a bases existentes antes de procesar transferencias.

Para **Tarjeta**, el checkout solicita un PIN de cuatro dígitos y muestra una espera simulada de cinco segundos antes de registrar la venta. No es una autorización bancaria: el PIN no se transmite ni se guarda y no debe usarse el PIN real de una tarjeta.

## Ventas, comprobantes e historial

1. Importa `database/sales_schema.sql` en `omnistock_db` después de crear la base de datos. El script crea `ventas` y `detalle_ventas`, ambas en InnoDB.
2. Si las tablas `ventas` o `detalle_ventas` ya existían con el esquema antiguo de OmniStock, aplica una vez `database/migrate_legacy_sales_schema.sql`. La migración conserva sus columnas/datos existentes y agrega los campos de vendedor, subtotal, IVA, pago, cambio y detalle que requiere el ticket. No vuelvas a importar un esquema destructivo ni elimines las tablas.
3. Verifica que `productos` también use InnoDB y tenga identificador, nombre, precio y existencias con alguno de los nombres reconocidos en `config/product_catalog.php`. El servidor vuelve a leer los precios y bloquea cada fila con `SELECT ... FOR UPDATE`; no utiliza precios enviados por el navegador.
4. Al confirmar el cobro, el POS recopila ID, nombre, cantidad, precio unitario y total del ticket; bloquea tickets vacíos o cantidades mayores al stock cargado y envía la venta por `POST` a `procesar_venta.php`. Este punto de entrada delega en `api/sales.php`, que valida sesión/CSRF, vuelve a leer los precios y existencias reales, y descuenta stock e inserta encabezado y detalle dentro de una transacción PDO. Los nombres, precios y totales enviados por el navegador son solo informativos: no se usan como valores confiables. Ante un error o stock insuficiente, revierte todos los cambios.
5. El vendedor puede consultar sus ventas del día y acumulado desde **Historial del día**. El comprobante en `ticket.php` se puede imprimir en papel térmico de 80 mm o guardar como PDF. Define `OMNISTOCK_STORE_NAME` en el entorno si deseas cambiar el encabezado predeterminado.

El horario del historial y los comprobantes usa la zona horaria configurada para PHP/MySQL. Ajusta la zona en `php.ini` a la zona local del establecimiento y confirma que MySQL usa la misma zona para que el corte coincida con el turno.

`test_venta.php` es una utilidad para generar un ticket de transferencia de prueba por $1.00. Está deshabilitada por defecto; solo se habilita con `OMNISTOCK_ENABLE_TEST_SALE=1` en un entorno de prueba aislado. Requiere sesión autenticada, no descuenta inventario y deja una venta marcada como prueba que aparece en historial/reportes. No la habilites en producción.

## Reportes y respaldo local

- El módulo administrativo `/omnistock-pos/admin/reports.php` muestra ventas del día, del mes, tendencias diarias y mensuales, y los cinco productos con más unidades vendidas. Los gráficos se dibujan con CSS y no dependen de CDN o internet.
- Desde **Generar y descargar respaldo** se crea un dump SQL completo de `omnistock_db` en `backups/` y se descarga al equipo del administrador. Se incluyen estructura, datos, rutinas, triggers, eventos y vistas compatibles con `mysqldump`.
- El proceso necesita que `mysqldump` (incluido con XAMPP) esté instalado y que Apache tenga permiso para escribir en `backups/`. Si no se detecta automáticamente, configura `OMNISTOCK_MYSQLDUMP_PATH` con la ruta del ejecutable.
- La carpeta de copias bloquea el acceso HTTP directo mediante `.htaccess`; conserva además una copia en una ubicación física segura. Para respaldos fuera del equipo local o cifrados se requiere configurar almacenamiento y claves adicionales.
- La descarga está protegida por sesión de administrador y token CSRF. Si se importan copias de seguridad en otra máquina, revisa credenciales, zona horaria y permisos antes de reabrir el POS.

## Alertas de stock crítico y órdenes de compra

1. En una instalación nueva, importa `database/replenishment_schema.sql` una sola vez; agrega `productos.stock_minimo INT NOT NULL DEFAULT 5` y crea `ordenes_compra`. Si `stock_minimo` ya existe pero falta `ordenes_compra`, ejecuta `database/migrate_replenishment_orders.sql`, que crea solo la tabla de órdenes y se puede repetir sin duplicarla.
2. El panel del administrador muestra en Catálogo y Reportes un indicador de los productos con existencias `stock <= stock_minimo`. En el catálogo puedes editar el mínimo por producto; si no lo cambias, el valor inicial es 5.
3. En el modal de alertas puedes revisar imagen, existencias y mínimo. **Generar Orden de Compra/Reabastecer** crea una orden pendiente con la cantidad necesaria para alcanzar el mínimo; no incrementa el inventario automáticamente. Las órdenes pendientes existentes se muestran para evitar duplicados.
4. Al recibir mercancía, actualiza el stock desde el catálogo después de verificar físicamente los productos. La tabla conserva el registro de la solicitud, pero la recepción/cancelación de órdenes no está automatizada en este módulo.

## Mensajería interna y avisos de almacén

1. Importa `database/warehouse_messages_schema.sql` en `omnistock_db` después de crear `usuarios`. Si `mensajes_almacen` ya existía, ejecuta una vez `database/migrate_restock_messages.sql` para agregar tipos de alerta/solicitud, producto, cantidades, estado y recibos de lectura.
2. Desde **Administración → Mensajería**, un administrador puede enviar a todos los vendedores un asunto, texto e imagen opcional. Se aceptan PNG, JPG/JPEG y WEBP de hasta 5 MB en `public/img/mensajes/`; Apache debe tener permiso de escritura en esa carpeta. La vista previa se muestra antes del envío.
3. Inicia sesión con un perfil `vendedor` y abre `/omnistock-pos/views/vendedor_pos.php` (o la raíz del POS). El botón **Avisos** aparece en esa sesión; al abrirlo, muestra mensajes de administración y permite enviar notas al admin. La terminal consulta mensajes cada 10 segundos, indica los pendientes en el botón y muestra un aviso emergente. Al abrir el buzón, los mensajes se marcan como leídos para ese vendedor; el contador de cada vendedor es independiente.
4. El POS muestra stock crítico en cada tarjeta y un indicador global. El vendedor puede enviar una solicitud de reabastecimiento desde la tarjeta; no se aceptan productos que ya superaron su mínimo y se evita duplicar solicitudes pendientes del mismo vendedor.
5. Después de cada venta, si las existencias quedan en o bajo el mínimo, se registra una alerta para administración dentro de la misma transacción de venta (solo una alerta pendiente por producto). En **Administración → Mensajería**, el administrador indica cuántas unidades recibió; el sistema aumenta el inventario y marca la solicitud como atendida en una operación atómica.
6. Si el vendedor no puede atender un aviso, puede seleccionar **Solicitar apoyo al administrador**, explicar el problema y enviar una solicitud. Las solicitudes se envían a `receptor_rol = 'admin'`, aparecen en **Solicitudes de vendedores** y se notifican en el panel de mensajería del administrador. El admin puede responder enviando un aviso a los vendedores.
7. El admin puede responder de forma privada a un mensaje recibido; solo la cuenta vendedor que lo envió verá esa respuesta. Las respuestas y notas nuevas se actualizan automáticamente en las bandejas.
7. Los recibos individuales de `mensajes_almacen_lecturas` sirven para ambos roles. La columna `mensajes_almacen.leido` indica que todas las cuentas activas del rol destinatario han leído el mensaje. El módulo necesita MySQL y una sesión autenticada del POS.
8. Para permitir adjuntos de 5 MB, configura `upload_max_filesize=5M` y `post_max_size=6M` en PHP. La carpeta de adjuntos deshabilita el listado y el acceso a extensiones ejecutables mediante `.htaccess`.
