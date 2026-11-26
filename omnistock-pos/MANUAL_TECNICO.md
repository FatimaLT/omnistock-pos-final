# Manual técnico — OmniStock & POS

## 1. Descripción general

OmniStock & POS es una aplicación web para operar ventas de mostrador, consultar productos y existencias, revisar ventas y facilitar la comunicación entre administración y caja. Incluye reseñas, ubicaciones de sucursales, un resumen administrativo y un conversor de divisas.

Este manual describe el código presente en este repositorio. La aplicación es un sistema PHP tradicional: **no está construida con React ni Vite**, y no requiere una compilación de frontend.

## 2. Stack tecnológico

| Capa | Tecnología |
|---|---|
| Presentación | HTML renderizado con PHP, CSS y JavaScript nativo |
| Backend | PHP 8.0 o posterior, PDO y endpoints JSON |
| Base de datos | MySQL 5.7+ o MariaDB 10.2+; tablas transaccionales InnoDB |
| Mapa | Leaflet y mosaicos de OpenStreetMap; requiere conexión externa para los mosaicos |
| Herramientas de desarrollo | XAMPP o servidor Apache/PHP; tareas locales de VS Code |

Extensiones PHP requeridas por las rutas y operaciones existentes: `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `openssl` y `hash`. No hay `composer.json`, `package.json` ni gestor de paquetes frontend. Hay comprobaciones automatizadas básicas sin dependencias externas en `tests/run.php`, ejecutables junto con las verificaciones de sintaxis desde `scripts/validate.ps1`.

## 3. Arquitectura y módulos

La aplicación separa las vistas, la lógica de acceso a datos y los recursos estáticos:

```text
omnistock-pos/
├── admin/                 Vistas y operaciones administrativas
├── api/                   Endpoints JSON para ventas, productos y módulos
├── assets/css/            Estilos de caja, administración y módulos
├── assets/js/             Interacciones del POS y módulos
├── components/
│   ├── atoms/             Elementos de interfaz reutilizables
│   ├── molecules/         Formularios y bloques pequeños
│   ├── organisms/         Módulos compuestos
│   ├── templates/         Estructura común de páginas
│   └── pages/             Vistas completas de módulos
├── config/                Sesiones, autenticación, base de datos y configuración
├── database/              Esquemas, datos de ejemplo y migraciones SQL
├── public/img/            Imágenes de productos y mensajes
├── views/                 Vistas de caja
├── index.php              Punto de venta
├── login.php              Inicio de sesión
├── ticket.php             Comprobante y encuesta postventa
└── .env.example           Plantilla de configuración
```

### Rutas y servicios principales

| Ruta | Acceso / función |
|---|---|
| `login.php` | Inicio de sesión |
| `index.php`, `views/productos.php`, `views/vendedor_pos.php` | Punto de venta autenticado |
| `admin/` | Catálogo y operaciones de administración |
| `admin/reports.php` | Reportes y respaldo |
| `admin/messages.php`, `admin/users.php` | Mensajería y administración de usuarios |
| `admin/sucursal-resumen.php` | Indicadores, resumen heurístico y configuración de tasas |
| `resenas.php` | Reseñas registradas en base de datos y opiniones locales del dispositivo |
| `mapa-sucursales.php` | Mapa y direcciones configuradas en `config/branches.php` |
| `ticket.php?id={id}` | Comprobante autorizado para el vendedor de la venta o un administrador |
| `api/products.php`, `api/sales.php` | Catálogo, historial del día y registro transaccional de ventas |
| `api/reviews.php` | Consulta y publicación de reseñas con sesión y token CSRF |
| `api/branch-summary.php` | Métricas del resumen, solo para administración |
| `api/exchange-rates.php` | Consulta de tasas y actualización administrativa |
| `api/warehouse-messages.php`, `api/stock-alerts.php` | Mensajes internos y alertas de inventario |

Las rutas amigables `/resenas` y `/mapa-sucursales` requieren Apache con `mod_rewrite` y `AllowOverride All`. Las rutas `.php` permanecen disponibles cuando el servidor no procesa `.htaccess`.

### Flujo de venta

1. El navegador obtiene el catálogo; el servidor vuelve a validar productos, precios y existencias al cobrar.
2. `api/sales.php` valida sesión, token CSRF, método de pago, cantidades y stock.
3. La venta, sus detalles y el descuento de inventario se escriben en una transacción InnoDB, bloqueando las filas de producto.
4. Solo después de que el servidor confirma la venta se abre `ticket.php`; la encuesta se muestra en ese comprobante.

## 4. Base de datos y configuración

`config/env.php` carga las variables de `.env` si están definidas. Las variables del servidor tienen precedencia sobre el archivo. La inicialización del esquema aplica las versiones 1–4 (usuarios/ventas/mensajería, catálogo, reseñas y tasas de cambio) al conectarse a la base.

Variables principales:

| Variable | Propósito |
|---|---|
| `OMNISTOCK_DB_HOST`, `OMNISTOCK_DB_PORT` | Host y puerto MySQL |
| `OMNISTOCK_DB_NAME`, `OMNISTOCK_DB_USER`, `OMNISTOCK_DB_PASSWORD` | Base y cuenta de aplicación |
| `OMNISTOCK_INITIAL_ADMIN_USERNAME`, `OMNISTOCK_INITIAL_ADMIN_PASSWORD`, `OMNISTOCK_INITIAL_ADMIN_NAME` | Cuenta administrativa inicial |
| `OMNISTOCK_PUBLIC_DEMO_ENABLED` y `OMNISTOCK_PUBLIC_DEMO_*` | Cuentas controladas de demostración |
| `OMNISTOCK_TRANSFER_BANK`, `OMNISTOCK_TRANSFER_CLABE`, `OMNISTOCK_TRANSFER_HOLDER` | Banco y beneficiario de transferencia |
| `OMNISTOCK_TRANSFER_CONCEPT`, `OMNISTOCK_TRANSFER_REFERENCE`, `OMNISTOCK_TRANSFER_AUTHORIZATION` | Concepto, referencia precargada y folio simulado para la demo |
| `OMNISTOCK_STORE_NAME` | Nombre mostrado en el comprobante |
| `OMNISTOCK_ENABLE_TEST_SALE` | Habilita la utilidad de ticket de prueba; mantener en `0` fuera de pruebas aisladas |
| `OMNISTOCK_ENABLE_LOCAL_DEMO_SESSION` | Habilita una utilidad de sesión demo local; mantener en `0` en hosting |

La plantilla `.env.example` contiene credenciales públicas de demostración y datos ficticios para portafolio. Al usarla, despliega únicamente una base aislada con datos de prueba. Para una operación real, desactiva `OMNISTOCK_PUBLIC_DEMO_ENABLED`, elimina las variables de demo y configura credenciales nuevas y privadas. No publiques `.env`.

La transferencia requiere banco, CLABE y titular configurados. El servidor comprueba que la CLABE configurada tenga 18 dígitos y valida la referencia capturada; **no verifica el dígito de control, titularidad ni recepción bancaria**. La cuenta regresiva de la interfaz no confirma un depósito. El cajero debe verificarlo con el banco.

## 5. Instalación local en Windows

1. Instala XAMPP con PHP 8+, MySQL/MariaDB y las extensiones indicadas arriba. Inicia MySQL.
2. Coloca el proyecto, por ejemplo, en `C:\xampp\htdocs\omnistock-pos`.
3. Crea la base de datos y configura `.env`:

   ```sql
   CREATE DATABASE omnistock_db
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```

   Copia `.env.example` a `.env` y ajusta host, nombre de base, usuario, contraseña y credenciales iniciales. La cuenta SQL necesita permisos para crear y migrar las tablas durante la inicialización.

4. Desde PowerShell, inicia el servidor local de desarrollo:

   ```powershell
   Set-Location C:\xampp\htdocs\omnistock-pos
   C:\xampp\php\php.exe -S 127.0.0.1:8765 -t .
   ```

5. Abre `http://127.0.0.1:8765/login.php`. En el primer acceso a la base se inicializa el esquema. Las tareas de VS Code pueden iniciar este servidor y abrir el login; VS Code puede pedir autorización para ejecutar tareas automáticas al abrir la carpeta.

El servidor integrado de PHP es solo para desarrollo. No procesa `.htaccess`; prueba las rutas `.php` directamente o usa Apache para verificar las rutas amigables.

Para instalaciones existentes, revisa el esquema antes de aplicar migraciones. Los scripts de `database/` no deben ejecutarse indiscriminadamente sobre una base con datos reales. `database/seed_products.sql` agrega datos de ejemplo y debe usarse solo en una base de demostración.

## 6. Despliegue en línea

1. **Prepara el hosting.** Usa PHP 8+, MySQL/MariaDB, HTTPS y las extensiones requeridas. Configura zona horaria y límites de subida acordes con los archivos permitidos.
2. **Crea la base de datos.** Usa una base dedicada y un usuario SQL exclusivo. La inicialización actual necesita permisos de creación/alteración de esquema; limita el acceso a esa base y protege las credenciales.
3. **Publica el código.** Sube la aplicación por un canal seguro. Mantén `.env` fuera del control de versiones; si el proveedor lo permite, define las variables en el panel del hosting en lugar de guardar el archivo dentro del document root.
4. **Configura el entorno.** Define las variables `OMNISTOCK_DB_*`, crea una contraseña administrativa única de al menos 12 caracteres y decide expresamente si el sitio será una demo pública o una operación real. No uses cuentas públicas de demo con datos reales.
5. **Configura Apache.** Habilita `mod_rewrite` y `AllowOverride All` si se requieren `/resenas` y `/mapa-sucursales`. El `.htaccess` del proyecto bloquea rutas de archivos ocultos; confirma que el hosting realmente respete esas reglas.
6. **Si usas Nginx**, recuerda que no lee `.htaccess`. Añade reglas equivalentes para negar archivos/rutas ocultos y `/backups/`, y configura los `try_files` o redireccionamientos de las rutas amigables.
7. **Permisos mínimos.** El usuario del servidor solo necesita escritura en las carpetas usadas para cargas y respaldos (`public/img/productos/`, `public/img/mensajes/` y `backups/`). No uses permisos `777`; evita servir respaldos por HTTP.
8. **Verifica antes de anunciar el sitio.** Inicia sesión con cada rol, confirma el catálogo, consulta el resumen, reseñas y mapa, verifica el fallback de direcciones, y comprueba la generación de un ticket en una base de demostración. Revisa los logs del servidor y no realices transacciones reales durante estas pruebas.
9. **Operación continua.** Programa respaldos fuera del document root, protege su descarga, monitoriza errores y actualiza PHP/MySQL de forma controlada.

El archivo `demo_session.php` está deshabilitado salvo configuración explícita y acceso local; `test_venta.php` también está deshabilitado por defecto. No habilites estas utilidades en un hosting público. El antiguo endpoint de reinicialización que podía borrar tablas fue retirado.

## 7. Comportamientos que deben comunicarse en la demostración

- **Tarjeta:** simulación de interfaz con PIN numérico de cuatro dígitos y espera; no hay pasarela, autorización bancaria ni almacenamiento del PIN.
- **Transferencia:** registra el método y la referencia después de la confirmación manual en interfaz; no acredita el pago.
- **Divisas:** tasas configurables/de demostración, no cotización en tiempo real; no intervienen en el cobro.
- **Resumen de sucursales:** análisis local basado en reglas, no una conexión a un modelo de IA. Las ventas no están desglosadas por sucursal si no se asignan explícitamente.
- **Encuesta del ticket:** las respuestas de satisfacción se guardan en `localStorage` del navegador y se muestran en la página de reseñas de ese mismo dispositivo/navegador; no se sincronizan entre equipos. Las reseñas publicadas desde el formulario de la página sí usan la API y la base de datos.
- **Mapa:** las direcciones permanecen disponibles si fallan Leaflet o los mosaicos; el mapa interactivo necesita conectividad a OpenStreetMap.

## 8. Verificaciones técnicas

Ejecuta las verificaciones de sintaxis y los tests unitarios básicos desde PowerShell:

```powershell
.\scripts\validate.ps1
```

El script comprueba la sintaxis de todos los archivos PHP y JavaScript (excepto dependencias externas) y ejecuta `tests/run.php`. También revisa manualmente permisos por rol, formularios, casos de carga/error y logs del navegador/servidor. Estas comprobaciones no sustituyen pruebas de aceptación del flujo de venta en una base aislada, pruebas de carga ni certificación de pagos.
