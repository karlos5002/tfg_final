# MANUAL DE USUARIO

## Cooperativa San Juan Bautista — Plataforma Web

**Anexo del Proyecto Final de Ciclo**
**Destinatario:** Usuarios finales (Cliente, Socio, Operario, Administrador)

---

## Índice

1. [Acceso a la plataforma](#1-acceso-a-la-plataforma)
2. [Manual del rol Cliente](#2-manual-del-rol-cliente)
3. [Manual del rol Socio](#3-manual-del-rol-socio)
4. [Manual del rol Operario](#4-manual-del-rol-operario)
5. [Manual del rol Administrador](#5-manual-del-rol-administrador)
6. [Preguntas frecuentes (FAQ)](#6-preguntas-frecuentes-faq)

---

## 1. Acceso a la plataforma

### 1.1. Página de inicio

Al acceder a la dirección de la plataforma, el usuario aterriza en una página pública con:

- Sección de bienvenida (*hero*) con la presentación de la almazara.
- Resumen de **valores de la marca** (cosecha propia, extracción en frío, certificación ecológica, D.O.P. Aceite Monterrubio).
- Tres productos destacados con acceso directo al catálogo completo.
- Explicación visual del proceso de elaboración (recolección → extracción en frío → embotellado).
- Botón **Reservar visita** (no requiere registro).
- **Mapa** de la ubicación en Herrera del Duque (Badajoz).

### 1.2. Registro de cuenta

Cualquier visitante puede crear una cuenta pulsando **Iniciar sesión** → **Crear cuenta** en la barra de navegación. Los campos obligatorios son:

- Nombre (mínimo 2 caracteres).
- Correo electrónico válido y no registrado previamente.
- Contraseña (mínimo 4 caracteres).
- Confirmación de contraseña.

> Todas las cuentas creadas desde el formulario público nacen con el rol **Cliente**. La promoción a **Socio**, **Operario** o **Administrador** la realiza únicamente un administrador desde el panel interno (sección 5.3).

### 1.3. Inicio de sesión

Al pulsar **Iniciar sesión**, se abre un modal donde se introducen email y contraseña. Tras la autenticación exitosa, el usuario es redirigido automáticamente a la pantalla apropiada según su rol:

| Rol | Pantalla de aterrizaje |
|-----|------------------------|
| Cliente | Página principal con sesión iniciada |
| Socio | `panel_socio.php` |
| Operario | `operario.php` |
| Administrador | `admin/index.php` |

### 1.4. Recuperación de contraseña

Si el usuario olvida su contraseña, puede pulsar **¿Has olvidado tu contraseña?** en el modal de login. Se le solicitará el correo electrónico y recibirá un mensaje con instrucciones.

### 1.5. Cierre de sesión

El botón **Cerrar sesión** está disponible en el menú de usuario de la barra de navegación, presente en todas las páginas autenticadas. Al pulsarlo se destruye la sesión y se redirige al inicio.

---

## 2. Manual del rol Cliente

### 2.1. Catálogo y compras

El cliente accede a la tienda mediante el enlace **Tienda** del menú o desde los productos destacados de la página de inicio.

#### 2.1.1. Visualizar el catálogo

En `tienda.php` se muestra el listado completo de productos disponibles (con stock > 0 y marcados como activos). Cada tarjeta incluye:

- Imagen del producto.
- *Badge* con la variedad de aceituna (Picual, Arbequina, Hojiblanca, etc.).
- Nombre y descripción.
- Precio.
- Botón **Añadir al carrito**.

#### 2.1.2. Añadir productos al carrito

Al pulsar **Añadir al carrito** se realiza una petición asíncrona al servidor que:

1. Verifica que el producto sigue activo y con stock disponible.
2. Suma una unidad al carrito de la sesión.
3. Actualiza el contador de productos en la barra de navegación.

Si el stock es insuficiente, el sistema muestra un mensaje del tipo *"No hay suficiente stock disponible. Stock actual: N"*.

#### 2.1.3. Gestión del carrito

Al pulsar el icono de carrito de la barra de navegación se despliega un panel lateral (*offcanvas*) con:

- Lista de productos añadidos.
- Botones `+` y `−` para ajustar la cantidad.
- Subtotal por línea y total general.
- Botón **Eliminar** por producto.
- Botón **Tramitar compra**.

#### 2.1.4. Proceso de compra

Al pulsar **Tramitar compra**, el sistema:

1. Recalcula los precios consultando la base de datos (no se confía en los datos del navegador).
2. Comprueba el stock disponible en tiempo real.
3. Crea una transacción atómica que registra el pedido, las líneas y descuenta el stock.
4. Redirige a la pantalla de confirmación `exito.php` con el número de pedido.

> **Nota:** En la versión actual del proyecto el pago se considera realizado directamente en la cooperativa (no hay pasarela bancaria integrada). El estado del pedido pasa a `pagado` automáticamente.

#### 2.1.5. Descargar la factura

Desde la pantalla de confirmación o desde el detalle del pedido, el cliente puede pulsar **Descargar factura**. El sistema genera un PDF con:

- Datos fiscales de la cooperativa.
- Datos del cliente.
- Detalle de productos con cantidad, precio unitario y subtotal.
- Cálculo del IVA reducido (10 %).
- Total final.

Solo el propietario del pedido (o un administrador) puede descargar la factura: si un cliente intenta acceder a una factura ajena modificando la URL, recibirá el mensaje *"Pedido no encontrado o no tienes permisos para verlo"*.

### 2.2. Reservar visita guiada

El cliente puede reservar una visita a la almazara desde el botón **Reservar visita** de la página principal. El formulario solicita:

- Nombre completo y email.
- Teléfono (opcional).
- Número de personas (1-10).
- Fecha (mínimo 24 h de antelación; los lunes están deshabilitados, ya que la almazara cierra al público).
- Turno (10:00, 12:00, 17:00 o 19:00).
- Tipo de experiencia (visita completa, sólo almazara, sólo cata).
- Comentarios opcionales (restricciones alimentarias, idioma, etc.).

Tras enviar la reserva, el sistema muestra un código de referencia y envía un correo de confirmación.

---

## 3. Manual del rol Socio

El socio dispone de todas las funcionalidades del cliente (compras, reservas, descarga de facturas) **más** un panel privado con herramientas profesionales.

### 3.1. Panel del socio

Al iniciar sesión, el socio aterriza en `panel_socio.php`, donde se le muestra:

- **Bienvenida personalizada** con el nombre del socio.
- **Mini-estadísticas** propias: total de entregas realizadas, kilos de aceituna aportados y litros de aceite estimados.
- **Widget agro-meteorológico** con la previsión de los próximos días para Herrera del Duque (consume el API de Open-Meteo) y una **recomendación de recolección** según las condiciones.
- **Calendario agrícola** con las tareas recomendadas para el mes en curso (poda, abonado, tratamiento, etc.).
- **Últimas noticias** internas publicadas por la cooperativa.
- **Tarjetas de acceso rápido** a las herramientas: Calculadora de rendimiento, Votaciones, Mis entregas y Tienda.

### 3.2. Calculadora de rendimiento del olivar

Disponible en `calculadora.php`. Esta herramienta estima la producción potencial del olivar del socio en función de tres parámetros:

- **Número de olivos** (1 - 100.000).
- **Tipo de cultivo** (secano = factor 0,8 / regadío = factor 1,2).
- **Edad del olivar** (joven, adulto o centenario, cada uno con un coeficiente).

El cálculo se realiza en tiempo real (sin recargar la página) y devuelve:

- Kilos de aceituna estimados.
- Litros de aceite virgen extra (aplicando la densidad de 0,916 kg/L).
- Valor económico aproximado.

### 3.3. Mis entregas

En `mis_entregas.php` el socio consulta el histórico completo de sus aportaciones a la almazara. La pantalla incluye:

- **Filtro por campaña** (solo se muestran las campañas en las que el socio ha participado).
- **Tabla de entregas** con fecha, kilos brutos, rendimiento (%), litros estimados y observaciones.
- **Liquidación estimada**: kilos × precio_por_kilo de la campaña correspondiente.
- Botón **Descargar albarán** (PDF) para cada entrega.

### 3.4. Votaciones

Mediante el enlace **Votaciones** del menú, el socio accede a `votaciones.php`, donde:

- Se listan las votaciones **abiertas** y **cerradas**.
- Para cada votación abierta puede leer la descripción, ver las opciones disponibles y emitir su voto.
- El sistema garantiza la unicidad mediante una clave primaria compuesta `(id_votacion, id_socio)`: un socio sólo puede emitir un voto por proceso. Cualquier intento de votar de nuevo será rechazado por el motor de base de datos.
- Una vez cerrada la votación, los resultados se publican en la propia plataforma.

> **Importante:** El voto es vinculante y no puede modificarse una vez emitido.

### 3.5. Compra de productos como socio

Los socios pueden realizar compras igual que cualquier cliente, accediendo al catálogo desde el enlace **Tienda** o desde el panel.

---

## 4. Manual del rol Operario

El operario dispone de un panel propio (`operario.php`) orientado a la gestión diaria en la almazara: registro de entregas in-situ, seguimiento de stock interno y consulta de tareas asignadas. Su acceso está restringido por el control de roles del sistema.

> El detalle exhaustivo de las funcionalidades de operario depende de los procesos internos de la cooperativa y se documentará en una guía complementaria entregada al personal contratado.

---

## 5. Manual del rol Administrador

El administrador dispone del panel más completo de la aplicación, accesible desde `admin/index.php` tras el login. Se compone de los siguientes módulos:

### 5.1. Dashboard de inicio

`admin/index.php` ofrece una vista global de la cooperativa con:

- **KPIs principales**: total facturado, total de pedidos, kilos de aceituna recibidos, litros producidos, número de socios activos.
- **Gráficas interactivas** (Chart.js):
  - Doughnut con las unidades vendidas por variedad.
  - Barras con kilos de aceituna por mes.
  - Comparativa entre campañas oleícolas.
- **Formulario rápido de registro de entregas** (selección de socio, kilos, rendimiento, fecha y observaciones).
- **Filtros laterales** que permiten consultar las entregas de un socio concreto, los pedidos de un cliente concreto o las entregas de una campaña.

### 5.2. Gestión de campañas oleícolas

`admin/campanas.php` permite:

- **Crear** una campaña con código en formato `AAAA/BBBB` (donde el segundo año es el siguiente al primero), fechas de inicio y fin, precio por kilo de aceituna y notas.
- **Editar** el precio o las fechas de una campaña existente.
- **Cerrar / reabrir** una campaña (cambio de estado).
- **Borrar** una campaña, siempre que no tenga entregas asociadas (en cuyo caso el sistema bloquea la operación con un mensaje claro).

Las nuevas entregas se asocian automáticamente a la campaña activa que cubre la fecha de la entrega.

### 5.3. Gestión de usuarios y roles

`admin/usuarios.php` muestra la lista completa de usuarios (incluidos los desactivados) ordenados por rol y nombre. El administrador puede:

- **Cambiar el rol** de cualquier usuario mediante un flujo de confirmación con explicación del impacto:
  - Promover a `socio` abre el acceso a la calculadora, votaciones, panel de socio y entregas.
  - Promover a `operario` permite gestionar tareas internas de almazara.
  - Promover a `admin` otorga control total sobre la plataforma.
- **Activar / desactivar** una cuenta sin borrarla (*soft-delete*).
- **Ver las entregas** asociadas a un socio (filtra el dashboard).
- **Ver los pedidos** asociados a un cliente (filtra el dashboard).

> **Restricciones de seguridad:**
> - Un administrador **no puede modificar su propio rol** (anti-lockout).
> - Para promocionar a `socio`, `operario` o `admin`, el usuario debe tener cumplimentados **DNI y apellidos**.
> - Cada cambio de rol queda registrado en el log del sistema.

### 5.4. Gestión de stock

`admin/stock.php` permite:

- **Reponer stock** de un producto (entrada).
- **Realizar ajustes manuales** (mermas, devoluciones).
- **Configurar el umbral de aviso** por producto.

Cada operación queda registrada en la tabla `movimientos_stock` con su tipo, cantidad, stock anterior, stock posterior, motivo y autor, garantizando una pista de auditoría completa. Las operaciones críticas se ejecutan dentro de transacciones para asegurar la coherencia entre el saldo y el log.

### 5.5. Gestión de votaciones

`admin/votaciones.php` permite:

- **Crear una nueva votación** con título, descripción, fecha de inicio, fecha de fin y un mínimo de dos opciones.
- **Consultar el estado** de las votaciones existentes (abiertas / cerradas).
- **Cerrar manualmente** una votación antes de su fecha de fin.

Los **resultados detallados** se consultan desde `admin/votacion_resultados.php`, con número de votos por opción, porcentaje y participación total.

### 5.6. Gestión de visitas

`admin/visitas.php` permite consultar y gestionar las reservas de visita:

- Filtrar por estado (pendiente, confirmada, cancelada).
- Confirmar o cancelar reservas (envío automático de email al solicitante).
- Ver las próximas visitas en formato lista.

### 5.7. Gestión de noticias

`admin/noticias.php` permite publicar comunicados internos o públicos:

- Título, resumen, contenido completo, categoría e imagen destacada.
- Visibilidad: pública (cualquier visitante) o socios (solo usuarios con sesión iniciada y rol `socio` o superior).
- Marca de "destacado" para fijar una noticia en la parte superior del listado.

### 5.8. Calendario agrícola

`admin/calendario.php` permite editar las tareas recomendadas mes a mes que se muestran al socio en su panel. Cada tarea incluye título, descripción, consejo (*tip*), icono y prioridad.

### 5.9. Resultados de votaciones

`admin/votacion_resultados.php` muestra el desglose de cualquier votación con:

- Número de votantes y porcentaje de participación sobre el censo.
- Resultado por opción con barra visual.
- Listado de socios que han votado (sin revelar la opción elegida, manteniendo el secreto del voto).

---

## 6. Preguntas frecuentes (FAQ)

**¿Necesito instalar algo para usar la plataforma?**
No. Funciona en cualquier navegador moderno (Chrome, Firefox, Edge, Safari). Además, puede instalarse como aplicación en el móvil o el escritorio gracias a la tecnología PWA: pulsa el icono de "Instalar" que aparece en la barra de direcciones.

**He olvidado mi contraseña, ¿qué hago?**
Pulsa **¿Has olvidado tu contraseña?** en el modal de login e introduce tu email. Recibirás un mensaje con instrucciones.

**¿Puedo cambiar de rol por mí mismo?**
No. Los cambios de rol solo los puede realizar un administrador. Si necesitas que tu cuenta pase de Cliente a Socio, ponte en contacto con la cooperativa.

**¿Puedo modificar mi voto?**
No. Una vez emitido, el voto queda registrado y es vinculante. Esta restricción es inherente al sistema y no puede ser saltada por ningún usuario, ni siquiera por el administrador.

**¿Por qué los lunes no puedo reservar visita?**
La almazara cierra al público los lunes. La validación se aplica tanto en el navegador como en el servidor.

**¿Puedo descargar la factura de un pedido antiguo?**
Sí, siempre que sea tu propio pedido. Desde el detalle del pedido encontrarás el botón **Descargar factura**. Si intentas acceder a una factura ajena, el sistema lo impedirá.

**¿La aplicación se puede usar en el móvil?**
Sí. La interfaz es totalmente responsiva y se adapta a cualquier tamaño de pantalla. Además, al ser una PWA, puedes instalarla como aplicación nativa.

**¿Mis datos están seguros?**
Las contraseñas se almacenan cifradas con bcrypt (algoritmo recomendado por OWASP). Las comunicaciones se realizan mediante consultas preparadas (PDO) que protegen frente a inyección SQL, y los formularios sensibles incluyen tokens CSRF para evitar peticiones fraudulentas. En producción, todas las comunicaciones se cifran con HTTPS.

**¿Cómo me doy de baja?**
Las bajas se gestionan a través del administrador de la cooperativa. Las cuentas no se eliminan físicamente, sino que se desactivan (*soft-delete*) para preservar el histórico de pedidos y entregas.

---

*Anexo de usuario al PFC. Documento dirigido a los usuarios finales de la plataforma según su rol.*
