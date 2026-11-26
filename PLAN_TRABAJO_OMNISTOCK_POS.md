# Plan de trabajo — OmniStock & POS Engine

**Fecha:** 9 de noviembre de 2026  
**Tipo de documento:** Plan de ejecución y cotización preliminar  
**Moneda de referencia:** USD  
**Estado:** Estimación para planificación; requiere validación de alcance y país de operación antes de contratar.

## 1. Resumen ejecutivo

OmniStock & POS Engine será un sistema híbrido para comercios pequeños y medianos. La aplicación de caja e inventario funcionará en la red local con PHP 8.x y MySQL (XAMPP, MySQL en el puerto 3307, según el entorno indicado). Una API en Node.js y servicios cloud gestionarán la sincronización remota y los respaldos externos cuando haya conectividad.

El objetivo es que las ventas, los movimientos de inventario y los cierres de caja no dependan de internet. La conexión sí será necesaria para enviar y consultar información cloud, administrar respaldos externos y acceder a funciones remotas.

**Estimación base:** 8 semanas de calendario, equipo de 6–7 personas en dedicación parcial/por fases, desarrollo de **USD 17,000** y costos recurrentes estimados desde **USD 556 por año**, sin incluir mantenimiento, impuestos ni servicios opcionales.

## 2. Objetivos y alcance

- Agilizar cobros y operaciones de caja con una interfaz de vendedor sencilla.
- Mantener productos, existencias, usuarios y permisos bajo administración controlada.
- Operar localmente durante interrupciones de internet y sincronizar al restablecerse la conexión.
- Conservar trazabilidad de ventas, movimientos, cierres y cambios administrativos.
- Automatizar respaldos locales y remotos en formatos SQL y JSON.
- Entregar una solución documentada, probada y preparada para despliegue en XAMPP y cloud.

### Fuera del alcance base

Integración con facturación electrónica o fiscal, terminales bancarias, hardware especializado, migración masiva de datos, aplicación móvil nativa, alta disponibilidad multi-región y soporte 24/7. Estos elementos requieren estimación y aprobación aparte.

## 3. Roles y permisos

Los permisos se aplicarán en la interfaz y se validarán también en el servidor; ocultar un botón no sustituye la autorización. Todas las acciones sensibles deben quedar registradas con usuario, fecha, operación y entidad afectada.

| Rol | Responsabilidades y permisos principales |
|---|---|
| **Vendedor** | Iniciar sesión; consultar productos disponibles; buscar o escanear productos; crear ventas; aplicar únicamente descuentos autorizados; cobrar y registrar el método de pago; emitir/imprimir comprobante si está configurado; consultar sus operaciones permitidas; abrir, consultar y cerrar su caja según las políticas del negocio. No administra usuarios ni modifica el catálogo maestro. |
| **Administrador** | CRUD de productos, categorías, precios, impuestos configurados, existencias e imágenes; gestión de usuarios, roles y permisos; consultar reportes de ventas por rango de fechas, vendedor y método de pago; revisar diferencias y realizar cierres; mantener parámetros del negocio; consultar bitácoras y estado de sincronización; restaurar datos mediante un procedimiento controlado. |

**Criterios de seguridad de cuentas:** las contraseñas no se guardarán cifradas de forma reversible, sino con hash adaptativo seguro (por ejemplo, Argon2id o bcrypt) y sal. Los datos sensibles que deban recuperarse se cifrarán en reposo con claves fuera de la base de datos. Se aplicarán controles de sesión, límites de intentos, mínimo privilegio y baja/desactivación de cuentas.

## 4. Funcionamiento híbrido y continuidad

### Operación local

1. La aplicación PHP y la base MySQL se ejecutan en el equipo servidor de la tienda o en una máquina local dedicada; los terminales autorizados acceden por la red interna.
2. La configuración de MySQL utilizará el puerto 3307 indicado, verificando que no exista conflicto con otros servicios. El puerto no se expondrá a internet.
3. Las funciones de venta, caja, catálogo disponible y movimientos esenciales se ejecutan contra la base local. Si se pierde la conexión externa, la caja puede continuar con las capacidades y datos ya disponibles en la tienda.
4. Cada operación que deba replicarse se registra primero en una cola local persistente (patrón *outbox*), junto con un identificador único y marca de tiempo.

### Sincronización

- Un servicio Node.js consumirá la cola local cuando la API cloud esté disponible y enviará eventos de forma autenticada, con reintentos graduales.
- La API procesará eventos de forma idempotente para evitar ventas o movimientos duplicados tras reintentos.
- Se conservarán estados por evento: pendiente, enviado, confirmado o con error; el administrador podrá revisar y reintentar errores sin borrar la evidencia.
- Los datos de venta y movimientos confirmados serán append-only; las correcciones se registrarán como ajustes relacionados, no como borrado silencioso.
- Las reglas de conflicto para datos editables (por ejemplo, precio o descripción) se definirán antes de construir la sincronización. No se debe resolver automáticamente un conflicto de inventario sumando o sobrescribiendo valores sin una regla contable explícita.
- Se guardarán identificadores estables, marcas de tiempo UTC y versión de esquema. La sincronización incluirá monitoreo de retraso y alertas ante errores persistentes.

### Respaldos automáticos SQL y JSON

- Crear respaldos programados de MySQL (`.sql`) y exportaciones JSON de entidades y eventos necesarios para portabilidad y recuperación.
- Política inicial sugerida: respaldo SQL diario, exportación JSON diaria y respaldo completo semanal; conservar 14 copias diarias y 8 semanales, sujeto a requisitos legales y espacio.
- Mantener al menos una copia local y una copia cloud cifrada; no considerar completa la estrategia si ambas copias están en el mismo equipo o ubicación.
- Verificar integridad al terminar cada copia, registrar resultado y alertar si falla. Ejecutar una prueba de restauración al menos trimestralmente.
- Cifrar los respaldos antes de transferirlos y restringir el acceso por cuenta/rol. La clave de cifrado se almacenará separada de los archivos.
- Documentar el procedimiento de recuperación y los objetivos iniciales: **RPO** (pérdida máxima de datos) de hasta 24 horas para respaldos programados y **RTO** (tiempo objetivo de recuperación) de hasta 4 horas en una restauración estándar. Las operaciones locales aún no respaldadas fuera del sitio pueden perderse ante daño físico del servidor.

## 5. Módulos y entregables

| Módulo | Entregables principales |
|---|---|
| Descubrimiento y arquitectura | Requisitos priorizados, flujos de caja, matriz de permisos, modelo de datos y decisiones de sincronización. |
| Usuarios y seguridad | Inicio/cierre de sesión, roles, permisos, hash de contraseñas, controles de sesión y bitácora administrativa. |
| Catálogo e imágenes | Alta, edición, consulta y desactivación de productos; categorías, precios, imágenes y validaciones. |
| Punto de venta y caja | Búsqueda rápida, carrito, cobro, comprobante configurable, apertura/cierre y registro de diferencias. |
| Inventario | Existencias, movimientos trazables, ajustes autorizados y consultas básicas. |
| Reportes y cierres | Ventas por rango, vendedor y método de pago; totales y conciliación de caja. |
| API y sincronización | API Node.js autenticada, cola/reintentos, idempotencia, estados de sincronización y registro de errores. |
| Respaldos y recuperación | Tareas SQL/JSON, almacenamiento cloud cifrado, retención, monitoreo y guía de restauración. |
| QA y despliegue | Pruebas funcionales y de integración, configuración XAMPP, despliegue cloud, documentación y transferencia. |

## 6. Plan y calendario estimado

El calendario presupone disponibilidad oportuna del responsable del negocio para validar procesos y datos de prueba. Las fases pueden solaparse con aprobación de sus dependencias.

| Semana | Fase | Resultado / punto de control |
|---|---|---|
| 1 | Descubrimiento y diseño | Requisitos aprobados, flujos, permisos, arquitectura y criterios de aceptación. |
| 2 | Base técnica y seguridad | Entornos, modelo inicial, acceso, roles, bitácora y estructura del catálogo. |
| 3 | Catálogo e inventario | CRUD, imágenes, existencias y movimientos con trazabilidad. |
| 4–5 | POS y caja | Flujo completo de venta, cobro, comprobante, apertura y cierre de caja. |
| 6 | Reportes y modo local | Reportes principales y pruebas de operación en red local sin internet. |
| 7 | API, sincronización y respaldos | Integración cloud, reintentos, exportaciones SQL/JSON y recuperación de prueba. |
| 8 | QA, piloto y entrega | Correcciones, capacitación, documentación, piloto y aceptación final. |

### Criterios de aceptación

- El vendedor puede completar una venta y registrar su cobro en la red local sin conexión a internet.
- Los permisos de vendedor y administrador se verifican en el servidor y no permiten operaciones fuera de su rol.
- La venta descuenta existencias una sola vez; una repetición de sincronización no duplica el registro.
- El administrador obtiene ventas en un rango de fechas y un cierre con totales y diferencias trazables.
- Los respaldos SQL y JSON se generan, validan y restauran exitosamente en un entorno de prueba.
- Los errores de sincronización y respaldo son visibles y dejan información suficiente para su diagnóstico.
- El cliente aprueba las pruebas de aceptación y recibe documentación y capacitación acordadas.

## 7. Cotización comercial preliminar

### Desarrollo por módulo

| Módulo | Estimación (USD) |
|---|---:|
| Descubrimiento, requisitos y arquitectura | $900 |
| Usuarios, roles y seguridad | $1,250 |
| Catálogo de productos e imágenes | $1,750 |
| Punto de venta, cobros y caja | $2,800 |
| Inventario y movimientos | $1,800 |
| Reportes y cierres | $1,700 |
| API Node.js y sincronización híbrida | $3,400 |
| Respaldos, recuperación y controles de seguridad | $1,600 |
| QA, despliegue, documentación y capacitación | $1,800 |
| **Total estimado de desarrollo** | **$17,000** |

Los importes son una estimación de planificación en USD, no una oferta vinculante. No incluyen impuestos, comisiones de pago, equipos, licencias de terceros ni funcionalidades expresamente fuera del alcance.

### Infraestructura y servicios anuales

| Concepto | Supuesto de costo | Estimación anual |
|---|---:|---:|
| Hosting cloud para API | $35/mes | $420 |
| Almacenamiento externo de respaldos | $8/mes | $96 |
| Dominio empresarial `.com` | renovación típica, varía por registrador | $15–$25 |
| Dominio empresarial `.app` (alternativa) | renovación típica, varía por registrador | $25–$40 |
| TLS/HTTPS | Certificado automatizado incluido en muchos proveedores | $0 |
| **Subtotal cloud con `.com`** | Hosting + almacenamiento + dominio | **$531–$541/año** |
| **Subtotal cloud con `.app`** | Hosting + almacenamiento + dominio | **$541–$556/año** |

Los precios cloud y de dominio son orientativos y deben confirmarse con proveedores en el país y región elegidos. El presupuesto base de operación usa **$556/año** para cubrir la opción `.app` hasta el extremo superior estimado. No incluye crecimiento de tráfico/almacenamiento, correo empresarial ni monitoreo premium.

### Mantenimiento anual

Presupuesto sugerido: **17% del desarrollo por año = $2,890/año**. Incluye hasta 36 horas anuales de soporte correctivo y ajustes menores, revisión de dependencias, actualizaciones de seguridad planificadas, supervisión básica de respaldos y una prueba anual de restauración. No incluye nuevas funciones, atención 24/7, visitas en sitio ni costos cloud. Horas adicionales y SLA se cotizan por separado.

| Resumen de inversión | Estimación |
|---|---:|
| Desarrollo inicial | $17,000 |
| Operación anual estimada (cloud, respaldo y `.app`) | $556 |
| Mantenimiento anual sugerido | $2,890 |
| **Total estimado del primer año** | **$20,446** |
| **Costo recurrente anual posterior estimado** | **$3,446** |

## 8. Retorno de inversión (ROI)

El ROI depende del número de sucursales, volumen de transacciones, costo laboral, pérdidas actuales y disciplina de inventario. El siguiente ejemplo es ilustrativo, no una promesa de ahorro:

**Supuestos por sucursal:** 1.5 horas de trabajo ahorradas por día × 26 días × $5/hora = **$195/mes**; reducción de mermas o diferencias de **$120/mes**; recuperación de ventas por menor fricción de **$100/mes**. Beneficio estimado: **$415/mes o $4,980/año por sucursal**.

Fórmula: **ROI del primer año = (beneficio anual − inversión del primer año) / inversión del primer año × 100**.

| Escenario | Beneficio anual supuesto | ROI primer año sobre $20,446 | Recuperación aproximada |
|---|---:|---:|---:|
| 1 sucursal | $4,980 | −75.6% | 49 meses |
| 5 sucursales | $24,900 | 21.8% | 9.9 meses |

El cálculo supone que el sistema se usa en todas las sucursales durante el año completo, que los beneficios se materializan desde el inicio y que los costos compartidos no se multiplican por tienda. Antes de aprobar la inversión, conviene medir durante 2–4 semanas tiempos de cobro, errores de inventario, mermas y costo laboral reales; luego recalcular los escenarios. No se incluyen impuestos, financiamiento, costo de equipos ni crecimiento futuro.

## 9. Equipo requerido para acelerar la entrega

Para un calendario de ocho semanas se recomienda un núcleo de **6–7 personas**, algunas con dedicación parcial. El modo “tiempo récord” depende de asignar revisiones de negocio dentro de 1–2 días y evitar cambios de alcance durante las fases críticas.

| Rol | Cantidad | Participación sugerida | Responsabilidad |
|---|---:|---|---|
| Product Owner / representante del comercio | 1 | 20–30% | Prioridades, reglas de negocio, decisiones rápidas y aceptación. |
| Líder técnico / arquitecto | 1 | 50–75% | Arquitectura, integración PHP/Node, revisión técnica y decisiones de sincronización. |
| Desarrollador backend PHP/MySQL | 1–2 | Alta | Aplicación local, modelo de datos, ventas, inventario y reportes. |
| Desarrollador Node.js/API | 1 | Alta, especialmente semanas 5–7 | API, autenticación de servicios, sincronización y tareas cloud. |
| Desarrollador frontend | 1 | Alta | Interfaz de caja, catálogo y panel administrativo; puede combinarse con backend si el alcance lo permite. |
| QA / analista de pruebas | 1 | 50–100%, aumentando semanas 4–8 | Pruebas de roles, ventas, desconexión, duplicados, respaldos y aceptación. |
| DevOps / infraestructura y seguridad | 1 | 25–50% | Entornos, despliegue, monitoreo, secretos, respaldos y restauración. |
| UX/UI (opcional, compartido) | 1 | 20–30% al inicio | Flujos de caja, prototipos y validación de usabilidad. |

El equipo puede reducirse a 4–5 personas combinando funciones, pero aumenta el riesgo de retraso y de que QA o recuperación queden insuficientemente cubiertos. La cantidad final depende de la experiencia del equipo y de si existen componentes reutilizables.

## 10. Riesgos y decisiones pendientes

- **Reglas fiscales y comprobantes:** confirmar país, impuestos, formato de recibo y obligaciones legales antes de cerrar alcance.
- **Hardware:** confirmar impresoras, lectores, cajones de dinero y compatibilidad con los terminales.
- **Sincronización multi-sucursal:** acordar qué datos se comparten y cómo se resuelven ediciones concurrentes y movimientos offline.
- **Seguridad y privacidad:** definir clasificación de datos, ubicación/región cloud, retención y quién custodia las claves.
- **Respaldos:** validar volumen esperado, retención legal y tiempo real de restauración con datos representativos.
- **Red local y energía:** definir equipo servidor, UPS, permisos de red y procedimiento operativo si falla el equipo local.
- **Estimación comercial:** confirmar país, impuestos, número de sucursales, usuarios, terminales, migración y SLA para emitir una propuesta contractual.

## 11. Próximos pasos

1. Validar alcance, procesos de caja y matriz de permisos con el comercio.
2. Confirmar país, requerimientos fiscales, cantidad de sucursales y hardware.
3. Tomar una línea base de costos y pérdidas para sustituir los supuestos del ROI.
4. Aprobar arquitectura, proveedor cloud, dominio, retención y SLA.
5. Convertir esta estimación en una propuesta con hitos de pago, calendario contractual y criterios de aceptación firmados.
