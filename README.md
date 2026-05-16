# Culqi Payment Server-to-Server para WooCommerce

**Un plugin de pasarela de pago minimalista, robusto y 100% Server-to-Server para WooCommerce.**

Desarrollado y optimizado por **[soyprogramador.pe](https://soyprogramador.pe)**.

Este plugin integra la pasarela de pagos Culqi V4 utilizando una arquitectura pura de Backend (API Server-to-Server). A diferencia del plugin oficial, esta versión elimina dependencias pesadas de Javascript en el frontend (como modales o iframes intrusivos) e interactúa directamente con el core nativo de WooCommerce, ofreciendo una experiencia de pago fluida, rápida y libre de conflictos.

---

## Características Principales ✨

- **Arquitectura Server-to-Server:** Procesamiento directo a través del backend. Evita bloqueos de navegadores y conflictos con otros plugins de optimización.
- **Soporte Completo 3DS (Autenticación):** Manejo nativo de los flujos de autenticación 3D Secure (*Frictionless* y *Challenge*) previniendo problemas de peticiones duplicadas (Race Conditions y errores HTTP 429).
- **Checkout Minimalista y Nativo:** Los campos de tarjeta de crédito se integran de forma natural en el formulario de pago nativo de WooCommerce.
- **Detección Automática de Marca (SVGs Locales):** Formato automático de números de tarjeta e inyección dinámica del logotipo de la tarjeta (Visa, MasterCard, Amex, Diners) a medida que el cliente escribe.
- **Selector de Tarjetas de Prueba Integrado:** Una herramienta invaluable para desarrolladores. Cuando el plugin está en "Modo Pruebas", muestra automáticamente un selector rápido de tarjetas de prueba oficiales de Culqi en la pantalla de pago (cubriendo flujos 3DS exitosos, sin 3DS y casos de error).

## Requisitos del Sistema 🖥️

- PHP 7.4 o superior
- WordPress 5.5 o superior
- WooCommerce 5.0 o superior
- [Credenciales (Llaves) de Integración o Producción de Culqi](https://www.culqi.com)

## Instalación 📦

1. Descarga el repositorio o el archivo `.zip` del plugin.
2. Sube la carpeta `culqi-payment` al directorio `/wp-content/plugins/` de tu instalación de WordPress.
3. Ingresa a tu panel de administración de WordPress y ve a **Plugins > Plugins instalados**.
4. Busca **Culqi Payment Server-to-Server** y haz clic en **Activar**.

## Configuración ⚙️

1. Dirígete a **WooCommerce > Ajustes > Pagos**.
2. Busca en la lista el método **Culqi Payment Server-to-Server** y actívalo.
3. Haz clic en **Gestionar** (o Configuración) para ingresar tus credenciales.
4. Completa los siguientes campos:
   - **Habilitar/Deshabilitar:** Marca la casilla para habilitar la pasarela.
   - **Título y Descripción:** Personaliza el texto que verán tus clientes al momento de pagar.
   - **Llave Pública (PK) y Llave Privada (SK):** Introduce tus credenciales proporcionadas por el CulqiPanel.
   - **Modo Pruebas:** Activa esta casilla si usarás tus llaves de integración (test). ¡Esto habilitará el menú especial de tarjetas de prueba en el checkout!
5. Guarda los cambios.

## Entorno de Pruebas (Developer Mode) 🧪

Si habilitas la opción de **Modo Pruebas** en la configuración, verás que en tu página de Checkout aparece un recuadro amarillo con un menú desplegable. 

Ese menú contiene tarjetas de prueba listas para usar:
- Tarjetas con flujo 3DS Challenge (Pide confirmación).
- Tarjetas con flujo 3DS Frictionless (Aprobación directa).
- Tarjetas para compras sin 3DS.
- Tarjetas que simulan errores (Fondos insuficientes, tarjeta robada, etc).

Al seleccionar una, los datos (Número, Fecha, CVV y un correo de validación `review@culqi.com`) se autocompletarán instantáneamente.

## Certificación PCI DSS y Paso a Producción 🔒

Al utilizar la pasarela en **Modo Producción (`pk_live_...`)** mediante integración Server-to-Server nativa (API REST `/v2/tokens`), los estándares de seguridad de Visa y Mastercard requieren que el comercio cuente con autorización para el manejo directo de datos de tarjeta.

Por defecto, Culqi bloquea las peticiones backend a `/v2/tokens` en producción devolviendo el código HTTP 400. Para desbloquear este endpoint:
1. El representante legal de la empresa debe contactar a **riesgos@culqi.com** o soporte de Culqi indicando que su integración es vía Backend/API Server-to-Server.
2. Culqi solicitará completar el cuestionario de autoevaluación **PCI SAQ-D** (Self-Assessment Questionnaire D).
3. Una vez completado y firmado declarando el uso de servidores seguros con HTTPS y la no retención de números de tarjeta en base de datos, Culqi habilitará las llaves de producción para tokenización directa.

## Configuración de Webhooks 🔗

Para mantener una sincronización instantánea y en tiempo real entre Culqi y WooCommerce ante pagos exitosos o devoluciones:
1. En tu panel de Culqi, dirígete a **Eventos > Webhooks > + Crear Webhook**.
2. Registra los dos eventos obligatorios:
   - `charge.creation.succeeded` (Cargo exitoso con tarjeta o Yape)
   - `refund.creation.succeeded` (Devolución procesada con éxito)
3. En el campo URL introduce el endpoint nativo de la pasarela:
   `https://tudominio.com/wc-api/culqi_webhook`
4. En **Activar Autenticación**, define un Usuario y Contraseña seguros. 
5. Pega ese mismo Usuario y Contraseña en los ajustes del plugin en WooCommerce (**Usuario de Autenticación** y **Contraseña de Autenticación**).

## Autor y Soporte 👨‍💻

- **Autor:** [soyprogramador.pe](https://soyprogramador.pe)
- **Licencia:** GPLv2 o superior.

*Este plugin es una adaptación independiente de la pasarela Culqi V4 para brindar mayor estabilidad y control en entornos WooCommerce de alto rendimiento.*
