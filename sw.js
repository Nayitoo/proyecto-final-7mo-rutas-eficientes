/**
 * Service worker de Aprilon — solo se ocupa de cachear los tiles del mapa
 * (OpenStreetMap) para que se puedan seguir viendo sin conexión. El resto
 * de los pedidos (index.html, api/*) van directo a la red: no queremos
 * servir datos de rutas/paradas viejos desde caché mientras haya señal.
 */
const CACHE_TILES = 'aprilon-tiles-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

function esTileDeMapa(url) {
  // tiles claros (OSM) en modo claro, tiles oscuros (CartoDB) en modo oscuro — ver tileLayerConfig() en index.html
  return /(^|\.)tile\.openstreetmap\.org\//.test(url) || /(^|\.)basemaps\.cartocdn\.com\//.test(url);
}

self.addEventListener('fetch', (event) => {
  const url = event.request.url;
  if (!esTileDeMapa(url)) return; // deja pasar todo lo demás sin tocarlo

  event.respondWith(
    caches.open(CACHE_TILES).then(async (cache) => {
      const cached = await cache.match(event.request);
      if (cached) return cached; // cache-first: si ya la vimos, ni pide de nuevo
      try {
        const resp = await fetch(event.request);
        // los tiles de OSM no mandan headers CORS, así que la respuesta llega
        // "opaca" (sin poder leer status) — igual se puede cachear y reusar.
        if (resp) cache.put(event.request, resp.clone());
        return resp;
      } catch (e) {
        return cached || Response.error();
      }
    })
  );
});
