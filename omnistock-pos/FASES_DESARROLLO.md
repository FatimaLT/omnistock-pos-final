# OmniStock POS - Fases de Desarrollo

## Resumen Ejecutivo

**OmniStock POS Engine** es una solución completa de punto de venta y gestión de inventario diseñada para pequeños y medianos negocios. Este documento detalla las etapas de desarrollo del MVP (Producto Mínimo Viable) desde su concepción hasta la producción.

**Versión:** 1.0.0  
**Estado:** Listo para Producción  
**Última Actualización:** Noviembre 2026

---

## Fase 1: Análisis y Especificación

### Duración: Semana 1-2

#### Objetivos Completados
- ✅ Identificación de requisitos funcionales
- ✅ Análisis de stakeholders (Administrador, Vendedor)
- ✅ Mapeo de procesos de negocio
- ✅ Definición de arquitectura de software

#### Requisitos Funcionales
1. **Gestión de Usuarios**
   - Autenticación segura con contraseñas hasheadas (Bcrypt)
   - Dos roles: Administrador y Vendedor
   - Control de acceso basado en rol (RBAC)

2. **Punto de Venta**
   - Búsqueda de productos en catálogo en tiempo real
   - Carrito de compra interactivo
   - Múltiples métodos de pago (Efectivo, Tarjeta, Transferencia)
   - Generación de tickets/comprobantes

3. **Gestión de Inventario**
   - Catálogo de productos con precio y stock
   - Alertas de stock crítico
   - Seguimiento de movimientos

4. **Comunicación Interna**
   - Mensajería entre Vendedor y Administrador
   - Sistema de solicitudes de reabastecimiento
   - Notificaciones en tiempo real

#### Restricciones Identificadas
- **Acceso a Reportes:** Solo administrador
- **Visibilidad de Inventory:** Tiempo real, actualización post-venta
- **Seguridad de Pago:** Protección contra inyección SQL y XSS
- **Desempeño:** Respuesta en <500ms en operaciones críticas

---

## Fase 2: Diseño de Base de Datos

### Duración: Semana 2-3

#### Arquitectura de BD
- **Motor:** MySQL 5.7+
- **Charset:** UTF-8mb4 (soporte completo Unicode)
- **Transacciones:** InnoDB (garantía ACID)

#### Tablas Principales

```
📊 MODELO ENTIDAD-RELACIÓN

┌─────────────┐          ┌──────────────┐
│  usuarios   │◄─────────│   ventas     │
├─────────────┤          ├──────────────┤
│ id (PK)     │          │ id (PK)      │
│ nombre_usuario          │ vendedor_id  │◄──┐
│ password_hash           │ subtotal     │   │
│ tipo_usuario (ENUM)     │ iva          │   │
│ creado_en               │ total        │   │
└─────────────┘          │ metodo_pago  │   │
                         │ fecha_venta  │   │
                         └──────────────┘   │
                                │            │
                                ▼            │
                         ┌──────────────┐    │
                         │detalle_ventas│   │
                         ├──────────────┤   │
                         │ id (PK)      │   │
                         │ venta_id (FK)├───┘
                         │ producto_id  │◄──┐
                         │ cantidad     │   │
                         │ subtotal_linea    │
                         └──────────────┘   │
                                             │
                         ┌──────────────┐    │
                         │  productos   │    │
                         ├──────────────┤    │
                         │ id (PK)      ├────┘
                         │ nombre       │
                         │ precio       │
                         │ stock        │
                         │ stock_minimo │
                         └──────────────┘

┌──────────────────────┐
│ mensajes_almacen     │
├──────────────────────┤
│ id (PK)              │
│ emisor_id (FK)       │
│ receptor_rol (ENUM)  │
│ tipo (ENUM)          │
│ asunto               │
│ mensaje              │
│ producto_id (FK)     │
│ estado (ENUM)        │
│ fecha_envio          │
└──────────────────────┘
```

#### Tabla: usuarios
| Campo | Tipo | Atributos | Descripción |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identificador único |
| nombre_usuario | VARCHAR(80) | UNIQUE, NOT NULL | Login único |
| password_hash | VARCHAR(255) | NOT NULL | Contraseña hasheada (Bcrypt) |
| tipo_usuario | ENUM('vendedor','admin') | NOT NULL | Rol del usuario |
| creado_en | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Fecha de creación |

#### Tabla: productos
| Campo | Tipo | Atributos | Descripción |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identificador |
| nombre | VARCHAR(180) | NOT NULL | Nombre del producto |
| descripcion | TEXT | NULL | Descripción detallada |
| precio | DECIMAL(12,2) | NOT NULL | Precio unitario |
| stock | INT UNSIGNED | NOT NULL | Existencias actuales |
| stock_minimo | INT UNSIGNED | NOT NULL | Umbral de alerta |
| categoria | VARCHAR(120) | NOT NULL | Categoría |
| codigo | VARCHAR(100) | UNIQUE | Código SKU |
| creado_en | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Fecha de alta |

#### Tabla: ventas
| Campo | Tipo | Atributos | Descripción |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Número de ticket |
| vendedor_id | BIGINT UNSIGNED | NOT NULL | Usuario que realizó venta |
| subtotal | DECIMAL(12,2) | NOT NULL | Base imponible |
| iva_porcentaje | DECIMAL(5,2) | NOT NULL | % IVA aplicado |
| iva | DECIMAL(12,2) | NOT NULL | Monto de IVA |
| total | DECIMAL(12,2) | NOT NULL | Monto total |
| metodo_pago | ENUM | NOT NULL | Efectivo/Tarjeta/Transferencia |
| efectivo_recibido | DECIMAL(12,2) | NULL | Dinero entregado |
| cambio | DECIMAL(12,2) | NOT NULL | Monto de vuelto |
| referencia_transferencia | VARCHAR(50) | NULL | Folio de transferencia |
| fecha_venta | DATETIME | NOT NULL | Timestamp de venta |

#### Tabla: detalle_ventas
| Campo | Tipo | Atributos | Descripción |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identificador |
| venta_id | BIGINT UNSIGNED | FK → ventas | Venta asociada |
| producto_id | VARCHAR(100) | NOT NULL | ID del producto |
| nombre_producto | VARCHAR(255) | NOT NULL | Snapshot del nombre |
| precio_unitario | DECIMAL(12,2) | NOT NULL | Precio en momento venta |
| cantidad | INT UNSIGNED | NOT NULL | Unidades |
| subtotal_linea | DECIMAL(12,2) | NOT NULL | cantidad × precio |

#### Tabla: mensajes_almacen
| Campo | Tipo | Atributos | Descripción |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Identificador |
| emisor_id | BIGINT UNSIGNED | FK → usuarios | Quien envía |
| receptor_rol | ENUM('vendedor','admin') | NOT NULL | A quién va |
| receptor_id | BIGINT UNSIGNED | NULL | Usuario específico |
| tipo | ENUM | NOT NULL | aviso/alerta_stock/solicitud |
| asunto | VARCHAR(150) | NOT NULL | Título del mensaje |
| mensaje | TEXT | NOT NULL | Contenido |
| producto_id | VARCHAR(100) | NULL | Producto relacionado |
| cantidad_sugerida | INT UNSIGNED | NULL | Qty sugerida reabastecimiento |
| estado | ENUM('pendiente','atendida') | NOT NULL | Status |
| atendida_en | DATETIME | NULL | Timestamp de resolución |
| fecha_envio | DATETIME | NOT NULL | Timestamp de envío |

---

## Fase 3: Desarrollo Core del POS

### Duración: Semana 3-6

#### Módulos Implementados

##### 3.1 Autenticación y Autorización
- **Archivo:** `config/auth.php`, `login.php`
- **Mecanismos:**
  - Sesiones PHP seguras con cookies HttpOnly
  - CSRF tokens para formularios
  - Password hashing con algoritmo PASSWORD_DEFAULT (Bcrypt)
  - Session regeneration post-login
  - Detección de sesión expirada

**Flujo de Login:**
```
1. Usuario ingresa credenciales
2. Generación de CSRF token
3. Validación contra BD (prepared statements)
4. Verificación password_verify()
5. Regeneración de session_id()
6. Almacenamiento: $_SESSION['user_id', 'username', 'role']
7. Redirección según rol (admin → /admin/, vendedor → /views/productos.php)
```

##### 3.2 Punto de Venta (POS)
- **Archivo Principal:** `index.php`
- **Vistas:** `views/productos.php`, `views/vendedor_pos.php`
- **API:** `api/products.php`, `api/sales.php`

**Componentes:**
- Catálogo en tiempo real (búsqueda, filtro por categoría)
- Carrito interactivo (agregar/eliminar items, cálculo de totales)
- Selector de método de pago
- Validación de stock (FOR UPDATE locks)
- Generación de ticket (ticket.php)

##### 3.3 Procesamiento de Venta
- **Archivo:** `api/sales.php`
- **Validaciones:**
  - Autenticación requerida (session check)
  - CSRF token validation
  - Stock availability check
  - IVA percentage validation (0-100%)
  - Payment method validation

**Flujo de Transacción:**
```
START TRANSACTION
├─ FOR EACH item in cart:
│  ├─ SELECT product FOR UPDATE (lock)
│  ├─ Validate stock >= requested quantity
│  ├─ UPDATE stock - quantity
│  └─ Check if stock < minimum → auto-create alert message
├─ INSERT INTO ventas (totals, metadata)
├─ INSERT multiple INTO detalle_ventas (line items)
├─ COMMIT or ROLLBACK
└─ RETURN JSON with sale ID
```

**Métodos de Pago Implementados:**

| Método | Validación | Captura | Registro |
|--------|-----------|---------|----------|
| **Efectivo** | Monto >= Total | Monto recibido | Cambio calculado |
| **Tarjeta** | PIN 4 dígitos | Simulación local | No enviado a banco |
| **Transferencia** | 18 dígitos CLABE | Folio bancario | Guardado en BD |

##### 3.4 Gestión de Inventario
- **Stock Mínimo:** Configurableacademias por producto
- **Alertas Críticas:** Auto-generated cuando stock ≤ mínimo
- **Visualización Admin:** Dashboard con productos críticos
- **Reabastecimiento:** Solicitudes vía mensajería interna

---

## Fase 4: Seguridad - Control de Acceso (RBAC)

### Duración: Semana 4-5

#### Implementación de Roles y Permisos

```
╔════════════════════════════════════════════════════════╗
║                    MATRIZ DE PERMISOS                 ║
╠════════════════════════════════════════════════════════╣
║ FUNCIONALIDAD         │ ADMIN │ VENDEDOR │ ANONIMO   ║
├───────────────────────┼───────┼──────────┼───────────┤
║ Login                 │  ✓    │    ✓     │    ✓      ║
║ Punto de Venta        │  ✓    │    ✓     │    ✗      ║
║ Buscar Productos      │  ✓    │    ✓     │    ✗      ║
║ Procesar Venta        │  ✓    │    ✓     │    ✗      ║
║ Ver Ticket            │  ✓    │    ✓     │    ✗      ║
║ Ver Reportes Ventas   │  ✓    │    ✗     │    ✗      ║
║ Administrar Productos │  ✓    │    ✗     │    ✗      ║
║ Gestionar Usuarios    │  ✓    │    ✗     │    ✗      ║
║ Ver Mensajes Admin    │  ✓    │    ✗     │    ✗      ║
║ Enviar Mensajes       │  ✓    │    ✓     │    ✗      ║
║ Ver Alertas Stock     │  ✓    │    ✗     │    ✗      ║
╚════════════════════════════════════════════════════════╝
```

#### Protecciones Implementadas

1. **Validación de Rol en Punto de Acceso**
   ```php
   $user = require_admin_user();  // Redirige si NO es admin
   ```

2. **Protección de API**
   - Validación de sesión en cada endpoint
   - CSRF token requerido para POST
   - Verificación de propiedad de recurso

3. **Encriptación de Contraseñas**
   - Algoritmo: PASSWORD_DEFAULT (Bcrypt, 2y)
   - Costo: 10 (default)
   - Re-hashing automático si necesario

4. **Protección SQL Injection**
   - Prepared statements en todas las queries
   - PDO con ERRMODE_EXCEPTION
   - Identificadores escapados con backticks

5. **Protección XSS**
   - htmlspecialchars() en toda salida HTML
   - json_encode() con JSON_UNESCAPED_UNICODE
   - Content-Type headers apropiados

---

## Fase 5: Pruebas QA y Validación

### Duración: Semana 6-7

#### Test Cases Ejecutados

##### 5.1 Pruebas de Autenticación
- ✅ Login con credenciales válidas → Redirección correcta
- ✅ Login con credenciales inválidas → Mensaje de error
- ✅ CSRF token inválido → Rechazo de sesión
- ✅ Session hijacking prevention → Session regeneration post-login
- ✅ Password rehashing automático → Upgrade de hash si es necesario

##### 5.2 Pruebas de POS
- ✅ Búsqueda de productos → Resultados correctos
- ✅ Agregar a carrito → Totales actualizados
- ✅ Cambiar cantidad → Cálculo correcto
- ✅ Remover item → Carrito actualizado
- ✅ Aplicar IVA → Monto correcto (incluye 0%)
- ✅ Limpiar carrito → Estado vacío

##### 5.3 Pruebas de Métodos de Pago
- ✅ **Efectivo:**
  - Monto recibido >= total → Cambio correcto
  - Monto recibido < total → Rechazo
  
- ✅ **Tarjeta:**
  - PIN 4 dígitos requerido → Validación
  - Wait 5 segundos → Simulación delay procesamiento
  - No envío a banco → Seguridad local
  
- ✅ **Transferencia:**
  - CLABE 18 dígitos numéricos → Validación
  - Folio/referencia guardado → Trazabilidad
  - Datos bancarios mostrados → UX clara

##### 5.4 Pruebas de Stock y Alertas
- ✅ Stock suficiente → Venta procesada
- ✅ Stock insuficiente → Rechazo con mensaje
- ✅ Stock <= mínimo → Alerta auto-creada
- ✅ Alerta duplicada → No crearse múltiples

##### 5.5 Pruebas de Seguridad (RBAC)
- ✅ Vendedor accede a reportes → Redirección denegada
- ✅ Anónimo accede a POS → Redirección a login
- ✅ Vendedor ve solo sus mensajes → Filtrado correcto
- ✅ Admin ve todos los mensajes → Sin restricción

##### 5.6 Pruebas de Mensajería
- ✅ Vendedor envía mensaje → Llega a admin
- ✅ Admin responde → Vendedor notificado
- ✅ Marcar como leído → Status actualizado
- ✅ Adjuntos procesos → Validación de ruta

##### 5.7 Pruebas de Base de Datos
- ✅ Inicialización automática en servidor remoto → Tablas creadas
- ✅ Usuarios por defecto → admin@omnistock.com y vendedor@omnistock.com
- ✅ Transactions ACID → Venta atómica (todo o nada)
- ✅ Locks de stock → No overselling

---

## Fase 6: Despliegue a Producción

### Duración: Semana 7

#### Checklist de Despliegue

**Requisitos de Servidor:**
- ✅ PHP 8.0+ (declare(strict_types=1) compatible)
- ✅ MySQL 5.7+ o MariaDB 10.3+
- ✅ Extensión PDO para MySQL
- ✅ OpenSSL (para Bcrypt)
- ✅ cURL (opcional, para transferencias externas)
- ✅ GD o Imagick (opcional, para thumbs de productos)

**Configuración de Entorno (.env):**
```
OMNISTOCK_DB_HOST=localhost
OMNISTOCK_DB_PORT=3306
OMNISTOCK_DB_NAME=omnistock_db
OMNISTOCK_DB_USER=omnistock_user
OMNISTOCK_DB_PASSWORD=SuperPassword123!

OMNISTOCK_INITIAL_ADMIN_USERNAME=admin@omnistock.com
OMNISTOCK_INITIAL_ADMIN_PASSWORD=AdminPassword2026*
OMNISTOCK_INITIAL_ADMIN_NAME=Administrador

OMNISTOCK_SEED_DEMO_SELLER=1
OMNISTOCK_DEFAULT_SELLER_PASSWORD=Vendedor2026*

OMNISTOCK_STORE_NAME=Mi Tienda
OMNISTOCK_TRANSFER_BANK=Banco ABC
OMNISTOCK_TRANSFER_CLABE=012345678901234567  (18 dígitos)
OMNISTOCK_TRANSFER_HOLDER=Empresa S.A.
```

**Verificaciones Pre-Producción:**
- ✅ Código review completado
- ✅ No hay Warnings/Notices de PHP
- ✅ Todas las queries usan prepared statements
- ✅ CSRF tokens en todos los formularios
- ✅ Logs dirigidos a archivo (no stdout)
- ✅ Permisos de archivo: 755 (dir), 644 (archivos)
- ✅ Base de datos con backups automáticos

**Pasos de Despliegue:**
1. Git clone en servidor web
2. Crear archivo .env con credenciales
3. Crear directorio /uploads con permisos 777
4. Ejecutar `php config/database_initializer.php`
5. Verificar logs en /logs/ directorios
6. Acceder a login.php y probar con usuarios seed

**Monitoreo Post-Despliegue:**
- Revisar PHP error_log
- Monitorear tabla `omnistock_schema_migrations`
- Validar sincronización de sesiones
- Prueba de carga: 10 usuarios simultáneos
- Verificar backups diarios de BD

---

## Resumen de Entregas

| Fase | Entrega | Estado |
|------|---------|--------|
| 1 | Especificación | ✅ Completada |
| 2 | Schema BD | ✅ Completada |
| 3 | Desarrollo MVP | ✅ Completada |
| 4 | RBAC y Seguridad | ✅ Completada |
| 5 | QA Testing | ✅ Completada |
| 6 | Despliegue | ✅ Listo |

---

## Contacto y Soporte

**Equipo de Desarrollo:** Copilot AI  
**Versión:** 1.0.0  
**Licencia:** Propietaria  
**Última revisión:** 2026-11-18
