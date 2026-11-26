# Checklist de calidad y liberación — OmniStock & POS

**Revisión local:** 25 de noviembre de 2026  
**Estado:** validación parcial; no es una certificación para operar con dinero real.

## Evidencia comprobada

- `scripts/validate.ps1`: sintaxis de **59 archivos PHP** y **15 archivos JavaScript**.
- `tests/run.php`: **7 comprobaciones unitarias, 0 fallos** (formato de CLABE y helpers del catálogo).
- Navegación local autenticada por los roles de administrador y vendedor, y revisión de rutas principales en una sesión anterior.
- POS: se verificó la configuración de transferencia ficticia, referencia precargada y cuenta regresiva; se canceló antes de registrar la venta. No se modificó inventario.
- La revisión local no observó errores de consola en los flujos recorridos.

## Pendiente antes de presentar

- [ ] Probar en una base aislada la venta completa: cobro, registro de encabezado/detalle, descuento de existencias, ticket y encuesta.
- [ ] Probar errores de stock insuficiente, datos inválidos y rollback sin tocar la base de demostración compartida.
- [ ] Revisar POS y administración en móvil, tablet y escritorio; verificar teclado, foco, etiquetas y contraste.
- [ ] Confirmar en Apache las rutas amigables, el fallback del mapa sin mosaicos y los permisos por rol.
- [ ] Revisar los logs del servidor después de las pruebas.

## Pendiente antes de publicar en hosting

- [ ] Usar una base aislada y respaldarla; probar también la restauración.
- [ ] Configurar HTTPS, secretos únicos y variables del hosting; no subir `.env`.
- [ ] Desactivar cuentas públicas de demo si se usarán datos reales.
- [ ] Reemplazar los datos ficticios de transferencia; integrar un proveedor bancario/pasarela si se van a aceptar pagos reales.
- [ ] Configurar permisos mínimos para cargas y respaldos; verificar que `.env`, código interno y copias no sean descargables.
- [ ] Verificar que el servidor aplique reglas equivalentes a `.htaccess` y probar la URL publicada.

## Límites que deben explicarse

- El PIN de tarjeta es una simulación: no se autoriza ni procesa un cargo.
- La transferencia muestra datos de demo y una cuenta regresiva; no consulta ni confirma depósitos. Al completar el flujo se registra una venta local y se descuenta stock.
- Las tasas del conversor son configurables/de demostración, no una cotización en tiempo real.
- El resumen de sucursales usa reglas locales; no es un servicio de IA.
- La encuesta postventa usa `localStorage` del navegador; no se sincroniza entre dispositivos.
- Leaflet/OpenStreetMap necesita conexión externa para mosaicos; las direcciones siguen disponibles si estos fallan.

Las pruebas actuales son una base de calidad, no reemplazan una suite de integración/E2E, la aceptación en el hosting ni una certificación de pagos. La guía de instalación y arquitectura está en [MANUAL_TECNICO.md](./MANUAL_TECNICO.md).
