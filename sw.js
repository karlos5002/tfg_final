/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Service Worker (sw.js)
 * ============================================================================
 * TFG — Desarrollo de Aplicaciones Web
 * 
 * Un Service Worker es un script que el navegador ejecuta en segundo plano,
 * separado de la página web. Actúa como un "proxy" entre la app y la red,
 * interceptando las peticiones HTTP para servir contenido desde la caché
 * cuando no hay conexión (o para acelerar la carga).
 * 
 * Ciclo de vida del Service Worker:
 *   1. INSTALL  → Se descarga y se prepara la caché con los recursos estáticos
 *   2. ACTIVATE → Se limpia la caché antigua y se toma el control de la app
 *   3. FETCH    → Se interceptan las peticiones HTTP y se aplica la estrategia
 * 
 * Estrategia de caché implementada: "Cache First, Network Fallback"
 * ─────────────────────────────────────────────────────────────────
 *   1. Cuando el usuario pide un recurso, primero se busca en la caché local
 *   2. Si está en caché → se devuelve instantáneamente (sin red)
 *   3. Si NO está en caché → se pide a la red, se guarda en caché y se devuelve
 *   4. Si falla tanto la caché como la red → se muestra una página offline
 * 
 * ¿Por qué "Cache First"?
 *   - Los agricultores trabajan en zonas rurales con cobertura limitada
 *   - La web debe cargar rápido incluso sin conexión (offline-first)
 *   - Los recursos estáticos (CSS, JS, imágenes) rara vez cambian
 * 
 * IMPORTANTE: Este archivo DEBE estar en la raíz del proyecto (no en /js/)
 * para que su "scope" cubra todo el dominio. Si estuviera en /js/sw.js,
 * solo podría interceptar peticiones dentro de /js/.
 * ============================================================================
 */

// ─── CONFIGURACIÓN ─────────────────────────────────────────────────────────
// Nombre de la caché con versión. Al cambiar la versión (ej: v2, v3...),
// el Service Worker eliminará la caché antigua y descargará todo de nuevo.
// Esto es esencial para que los usuarios reciban actualizaciones.
const CACHE_NAME = 'almazara-cache-v3';

// Lista de recursos que se descargan y cachean durante la INSTALACIÓN.
// Estos forman el "app shell": el mínimo necesario para que la web funcione
// offline. Incluimos la página principal, los estilos y las imágenes clave.
const RECURSOS_PRECACHE = [
    // ── NOTA: NO cacheamos páginas PHP (.php, ./) porque su contenido
    // depende de la sesión del usuario (login, carrito, rol).
    // Si las cacheáramos, el SW devolvería la versión pre-login
    // después de iniciar sesión, rompiendo la autenticación. ──

    // ── Estilos propios ──
    './assets/css/estilos.css',

    // ── Bootstrap 5.3 (CSS y JS desde CDN) ──
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',

    // ── Imágenes principales del sitio ──
    './assets/img/aceite-premium.png',
    './assets/img/aceite-picual.png',
    './assets/img/aceite-hojiblanca.png',
    './assets/img/olivar-tradicion.png',

    // ── Iconos de la PWA ──
    './icon-192.png',
    './icon-512.png',
];


// ═══════════════════════════════════════════════════════════════════════════════
// EVENTO 1: INSTALL — Preparar la caché con los recursos estáticos
// ═══════════════════════════════════════════════════════════════════════════════
// Se ejecuta la PRIMERA vez que el navegador detecta este Service Worker
// (o cuando cambia su contenido). Aquí descargamos todos los recursos del
// "app shell" y los guardamos en la caché del navegador (Cache API).
//
// waitUntil(): le dice al navegador "no consideres la instalación completa
// hasta que todas estas descargas hayan terminado". Si falla alguna descarga,
// la instalación falla y el SW no se activa.
//
// self.skipWaiting(): fuerza al nuevo SW a activarse inmediatamente, sin
// esperar a que el usuario cierre todas las pestañas de la web.
// ═══════════════════════════════════════════════════════════════════════════════
self.addEventListener('install', (event) => {
    console.log('[Service Worker] 📦 Instalando y cacheando app shell...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                // addAll() descarga TODOS los recursos en paralelo
                // y los almacena en la caché con sus URLs como clave.
                // Si CUALQUIER recurso falla, toda la instalación falla.
                return cache.addAll(RECURSOS_PRECACHE);
            })
            .then(() => {
                console.log('[Service Worker] ✅ App shell cacheado correctamente');
                // Activar inmediatamente (no esperar a cerrar pestañas)
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[Service Worker] ❌ Error al cachear:', error);
            })
    );
});


// ═══════════════════════════════════════════════════════════════════════════════
// EVENTO 2: ACTIVATE — Limpiar cachés antiguas
// ═══════════════════════════════════════════════════════════════════════════════
// Se ejecuta cuando el SW pasa de "waiting" a "active". Aquí limpiamos
// las cachés de versiones anteriores para liberar espacio en el dispositivo.
//
// ¿Por qué es necesario?
//   Si el usuario tenía "almazara-cache-v1" y subimos "almazara-cache-v2",
//   la v1 sigue ocupando espacio. Este código elimina TODAS las cachés
//   cuyo nombre no sea el CACHE_NAME actual.
//
// clients.claim(): toma el control de TODAS las pestañas abiertas
// inmediatamente, sin necesidad de que el usuario recargue la página.
// ═══════════════════════════════════════════════════════════════════════════════
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] 🔄 Activando y limpiando cachés antiguas...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                // Obtener todas las cachés del dominio y eliminar las que
                // no coincidan con la versión actual (CACHE_NAME)
                return Promise.all(
                    cacheNames
                        .filter((name) => name !== CACHE_NAME)
                        .map((name) => {
                            console.log('[Service Worker] 🗑️ Eliminando caché antigua:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log('[Service Worker] ✅ Activación completada');
                // Tomar el control de todas las pestañas abiertas
                return self.clients.claim();
            })
    );
});


// ═══════════════════════════════════════════════════════════════════════════════
// EVENTO 3: FETCH — Interceptar peticiones HTTP (Estrategia de caché)
// ═══════════════════════════════════════════════════════════════════════════════
// CADA VEZ que la página hace una petición HTTP (cargar una imagen, pedir un
// CSS, hacer un fetch a una API...), este evento la intercepta.
//
// Estrategia: "Cache First, Network Fallback"
// ───────────────────────────────────────────
//   Paso 1: ¿Está el recurso en la caché? → SÍ → Devolver desde caché (rápido)
//   Paso 2: ¿No está en caché? → Pedir a la red
//   Paso 3: ¿La red respondió bien? → Guardar en caché para la próxima vez
//   Paso 4: ¿Falló la red? → Intentar servir la página principal como fallback
//
// ¿Por qué esta estrategia?
//   - Es la más rápida para el usuario (caché local = 0ms de latencia de red)
//   - Perfecta para una web con contenido que cambia poco (catálogo, estilos)
//   - Los agricultores en el campo sin cobertura pueden seguir navegando
//
// NOTA: Solo cacheamos peticiones GET. Las peticiones POST (formularios,
// API del carrito) NUNCA se cachean porque modifican datos en el servidor.
// ═══════════════════════════════════════════════════════════════════════════════
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // ── Solo interceptamos peticiones GET ──
    // Las peticiones POST, PUT, DELETE no se cachean (modifican datos)
    if (request.method !== 'GET') {
        return;
    }

    // ── No cachear peticiones a páginas PHP ni APIs dinámicas ──
    // Las páginas PHP generan HTML dinámico que depende de la sesión
    // del usuario (login, carrito, rol). Cachearlas provocaría que
    // el SW devolviera contenido obsoleto después de un login/logout.
    // También tratamos como dinámica la "raíz" sin extensión, tanto si
    // estamos servidos desde / (Hostinger) como desde /TFG/ (XAMPP local).
    const url = new URL(request.url);
    const esRaizSinExtension =
        url.pathname === '/' ||
        url.pathname === '' ||
        url.pathname.endsWith('/TFG/') ||   // XAMPP local
        url.pathname.endsWith('/TFG');
    if (url.pathname.endsWith('.php') || esRaizSinExtension) {
        // Estrategia "Network First" para páginas PHP:
        // Siempre intentar la red primero para obtener contenido fresco.
        // Solo usar caché como último recurso si la red falla.
        event.respondWith(
            fetch(request)
                .catch(() => {
                    // Sin red → intentar devolver una versión cacheada
                    // como fallback (mejor que el error del navegador)
                    return caches.match(request);
                })
        );
        return;
    }

    // ── Para recursos estáticos (CSS, JS, imágenes): Cache First ──
    event.respondWith(
        // PASO 1: Buscar en la caché local
        caches.match(request)
            .then((cachedResponse) => {
                // Si encontramos el recurso en caché, lo devolvemos inmediatamente.
                // Esto es lo que hace la web "instantánea" sin conexión.
                if (cachedResponse) {
                    return cachedResponse;
                }

                // PASO 2: No está en caché → pedirlo a la red
                return fetch(request)
                    .then((networkResponse) => {
                        // Verificar que la respuesta de red es válida
                        // (status 200, tipo 'basic' para recursos propios u 'opaque' para CDNs)
                        if (!networkResponse || networkResponse.status !== 200) {
                            return networkResponse;
                        }

                        // PASO 3: Guardar una COPIA en caché para la próxima vez
                        // clone() es necesario porque Response es un stream que
                        // solo se puede leer una vez. Necesitamos dos copias:
                        // una para la caché y otra para devolver al navegador.
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(request, responseToCache);
                            });

                        return networkResponse;
                    })
                    .catch(() => {
                        // PASO 4: Falló la red Y no estaba en caché
                        // No podemos servir nada, el navegador mostrará su error
                        return new Response('Sin conexión', {
                            status: 503,
                            statusText: 'Service Unavailable'
                        });
                    });
            })
    );
});
