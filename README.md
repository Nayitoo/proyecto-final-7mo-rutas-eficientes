# Rutas Eficientes · Gestión de rutas de reparto

Aplicación web para empresas que reparten su propia mercadería. Quien coordina
carga las direcciones del día, el servidor le pide a Google el **orden más corto
de las paradas con el tráfico del momento**, la ruta se publica en el teléfono
del repartidor y cada entrega se cierra con hora y, si corresponde, con una
**palabra de verificación** que el destinatario recibió por email.

**No hay nada que instalar para usarla: se entra por el navegador** (y, si se
quiere, se agrega como app desde el aviso de instalación).

Proyecto final de la Tecnicatura en Informática · 7.º Informática B · Instituto
Leonardo Murialdo · 2026.

---

## 1. Dónde está

| Qué | Dirección |
|---|---|
| **Sistema en producción** | <https://rutas-eficientes.alwaysdata.net/> |
| Presentación (landing) | <https://nayitoo.github.io/RE-doc/landing/> |
| Documentación (portada) | <https://nayitoo.github.io/RE-doc/> |
| Guía de inicio rápido y FAQ | <https://nayitoo.github.io/RE-doc/quickstart/> |
| Manual de usuario | <https://nayitoo.github.io/RE-doc/manual/> |
| Documentación técnica completa | <https://nayitoo.github.io/RE-doc/documentacion/> |
| **Recorrido del código y herramientas** (documento técnico ejecutivo) | <https://nayitoo.github.io/RE-doc/tecnicismo/> |
| Servidor del instituto (ILM, solo red interna) | `http://192.168.101.92:8091/aprilon/` |

> La documentación antes estaba en `nayitoo.github.io/rutas-eficientes-documentacion/`.
> Esas direcciones siguen funcionando: redirigen solas a `/RE-doc/`.

### Cuentas de demostración (producción)

| Usuario | Rol | Qué puede hacer |
|---|---|---|
| `jefe` | Jefe | Administra cuentas y contraseñas; ve estadísticas de km, combustible y peajes |
| `admin` | Administrador | Carga puntos, calcula y publica las rutas, atiende imprevistos |
| `usuario` | Conductor | Sigue la ruta, cierra cada parada y reporta imprevistos |

La contraseña común de demostración figura en la pantalla de ingreso.

---

## 2. Qué hace

### Administrador
- **Carga de puntos** con autocompletado de direcciones (Google Places) o desde
  la **agenda de clientes**; el primer punto es el origen de la ruta.
- **Ruta óptima**: Google Routes reordena las paradas con el tráfico en tiempo
  real; opción de evitar peajes, autopistas o transbordadores.
- **Vista previa y publicación**; se puede despublicar para corregir.
- **Calendario mensual** para elegir el día y programar rutas a futuro.
- **Verificaciones** (opcional por parada): al publicar, se genera una palabra
  al azar y se envía por email al encargado del negocio.
- **Historial** de rutas y **avisos de imprevistos** en el momento (sondeo cada
  20 s con aviso sonoro).

### Conductor
- Ruta del día con la próxima parada destacada, mapa y **seguimiento por GPS**.
- Navegación por voz abriendo Google Maps con las paradas ya cargadas.
- Cierre de cada parada (pidiendo la palabra si la parada la requiere) y
  **reporte de imprevistos**.

### Jefe
- Alta, baja y edición de cuentas; pedidos de "olvidé mi contraseña".
- **Estadísticas** por semana, mes, año o total: kilómetros recorridos,
  combustible estimado según la **configuración de la flota** y peajes.

### Para todos
- Diseño para celular y computadora, tema claro u oscuro, **instalable como
  app (PWA)** y mapa disponible sin conexión (caché de mosaicos).

---

## 3. Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8 + PDO, sin framework ni Composer |
| Base de datos | MySQL / MariaDB |
| Frontend | HTML + CSS + JavaScript en un solo archivo (`index.html`), sin build step |
| Mapa | Leaflet 1.9.4 con mosaicos de OpenStreetMap / CARTO |
| Rutas y direcciones | Google Maps Platform: Routes, Places y Geocoding v4 |
| Email | Cliente SMTP propio (`config/mailer.php`), sin dependencias |
| App instalable | `manifest.json` + Service Worker (`sw.js`) |
| Hosting | AlwaysData (producción, HTTPS) · servidor ILM (IIS) · XAMPP (local) |

La clave de Google se usa **solo del lado del servidor**; nunca llega al
navegador.

---

## 4. Estructura

```
.
├── index.html             Toda la interfaz (vistas de jefe, administrador y conductor)
├── sw.js                  Service Worker: caché de mosaicos del mapa
├── manifest.json          Datos de la app instalable (PWA)
├── icons/                 Íconos de la app
├── api/                   34 endpoints JSON (sección 6)
├── config/
│   ├── comun.php          Sesión, guards de rol, respuestas JSON; elige la conexión
│   ├── conexion*.php      Conexión PDO, una por entorno (fuera del repo)
│   ├── env.php            Lectura del .env
│   ├── esquema.php        Completa el esquema en bases ya instaladas (idempotente)
│   ├── flota.php          Combustibles, consumo y precios para las estadísticas
│   ├── google_routes.php  Llamadas a Google (Routes, Places, Geocoding)
│   ├── mailer.php         Envío SMTP
│   ├── verificaciones.php Palabras de verificación de entrega
│   └── cacert.pem         Certificados raíz para las llamadas HTTPS a Google
├── db/                    Esquema SQL (sección 5)
├── uploads/fotos/         Fotos de perfil (con .htaccess que impide ejecutar scripts)
└── web.config             Protección de config/, db/ y .env en IIS (servidor ILM)
```

---

## 5. Base de datos

Tres archivos, que se importan **en este orden**:

| Archivo | Tablas |
|---|---|
| `db/rutas_eficientes.sql` | `usuarios`, `solicitudes_password` |
| `db/rutas.sql` | `configuracion`, `puntos_guardados`, `rutas`, `ruta_paradas`, `verificaciones` |
| `db/incidentes.sql` | `incidentes` |

Son 8 tablas InnoDB en `utf8mb4`. En una base ya instalada, `config/esquema.php`
agrega solo lo que falte (columnas de opciones de ruta y peajes, tabla
`configuracion`) sin borrar datos.

> `db/rutas_eficientes.sql` trae tres usuarios iniciales (`jefe`, `ventas`,
> `expedicion`) con una contraseña conocida que figura en el propio archivo.
> **Cambiala antes de usar una instalación nueva en serio.**

---

## 6. API

Todos los endpoints están en `api/` y responden JSON (`{"ok":true,…}` o
`{"ok":false,"error":"…"}` con el código HTTP correspondiente).

### Sesión y cuenta
| Método | Endpoint | Rol |
|---|---|---|
| POST | `login.php` / `logout.php` | público |
| GET | `sesion.php` / `config_publico.php` | público |
| POST | `solicitud_crear.php` / `password_resetear.php` | público ("olvidé mi contraseña") |
| POST | `password_cambiar.php` | logueado |
| POST | `perfil_foto.php` / `perfil_foto_quitar.php` | logueado |

### Usuarios y jefatura
| Método | Endpoint | Rol |
|---|---|---|
| GET | `usuarios_listar.php` | jefe |
| POST | `usuarios_guardar.php` / `usuarios_eliminar.php` | jefe |
| POST | `password_restablecer.php` | jefe |
| GET | `solicitudes_listar.php` | jefe |
| POST | `solicitud_resolver.php` | jefe |
| GET | `estadisticas.php?periodo=semana\|mes\|anio\|todo` | jefe |
| GET · POST | `config_flota.php` | jefe |
| POST | `ruta_peaje_guardar.php` | jefe |

### Puntos y rutas
| Método | Endpoint | Rol |
|---|---|---|
| GET | `lugares_autocompletar.php` / `lugares_detalle.php` | admin |
| GET | `puntos_guardados_listar.php` | admin |
| POST | `puntos_guardados_guardar.php` / `puntos_guardados_eliminar.php` | admin |
| POST | `ruta_calcular.php` | admin |
| POST | `ruta_guardar.php` | admin |
| POST | `ruta_despublicar.php` / `rutas_borrar_hoy.php` | admin |
| GET | `rutas_historial.php` | admin |
| GET | `rutas_listar.php` / `ruta_detalle.php` | logueado |
| POST | `ruta_parada_completar.php` | conductor |

### Imprevistos
| Método | Endpoint | Rol |
|---|---|---|
| POST | `imprevisto_crear.php` | conductor |
| GET | `imprevistos_listar.php` | admin |
| POST | `imprevisto_marcar_leidos.php` | admin |

*"admin" incluye al jefe y al administrador.*

---

## 7. Seguridad

- Contraseñas con **bcrypt** (`password_hash()` / `password_verify()`).
- **Consultas preparadas** con PDO en todos los endpoints.
- **Guard de rol** en cada endpoint (`requiere_login()` → 401, `requiere_rol()` → 403).
- Sesión regenerada al ingresar (`session_regenerate_id`) con cookie
  **HttpOnly** y `SameSite=Lax`.
- `uploads/fotos/` acepta solo JPG, PNG o WEBP (comprobados con
  `getimagesize()`) y su `.htaccess` **impide ejecutar scripts subidos**.
- **Una sola cuenta de administrador**, garantizada por la base (columna
  generada + índice único `uq_admin_unica`).
- Credenciales y claves viven en `.env` y en `config/conexion*.php`, ninguno de
  los dos versionado (`.gitignore`).

---

## 8. Configuración (`.env`)

Copiá `.env.example` a `.env` y completá:

| Variable | Para qué |
|---|---|
| `GOOGLE_MAPS_API_KEY` | Routes, Places y Geocoding |
| `APP_DEMO` | `true` muestra las cuentas de prueba en el login. **`false` en producción** |
| `SMTP_HOST` · `SMTP_PORT` · `SMTP_USER` · `SMTP_PASS` · `SMTP_FROM` · `SMTP_FROM_NAME` | Envío de las palabras de verificación. Sin esto la palabra se genera igual, solo que no sale el email |

La conexión a la base se elige con **una sola línea** en `config/comun.php`,
que hace `require_once` del `config/conexion*.php` del entorno (local, ILM o
AlwaysData).

---

## 9. Desplegar en un hosting

1. Subir el proyecto (FTPS/SFTP).
2. Crear la base e importar los tres `.sql` de la sección 5, en orden.
3. Crear `config/conexion.php` con las credenciales del hosting y apuntar ahí
   el `require_once` de `config/comun.php`.
4. Crear el `.env` de la sección 8.
5. Dar permiso de escritura a `uploads/fotos/`.
6. **Proteger `config/`, `db/` y `.env`**: el `web.config` incluido sirve para
   IIS; en Apache (AlwaysData) hace falta un `.htaccess` equivalente en la raíz,
   o esos archivos quedan descargables por URL.
7. Cambiar las contraseñas de las cuentas iniciales.

### PHP 8.5
El sistema funciona con PHP 8.5. Se sacaron las llamadas a `curl_close()`
(obsoleta en 8.5; no hace nada desde PHP 8.0) porque el aviso de PHP se
imprimía antes del JSON y rompía las respuestas. **En producción conviene
`display_errors = Off`**, para que ningún aviso futuro llegue a las respuestas
(en AlwaysData: Entorno → PHP → `php.ini`).

---

## 10. Apéndice · Levantar una copia local (XAMPP)

Solo hace falta para desarrollar o probar cambios. **Para usar la aplicación no
se instala nada.**

1. Copiar el proyecto a `C:\xampp\htdocs\rutas-eficientes\`.
2. Crear la base `rutas_eficientes` e importar los tres `.sql` de la sección 5
   (HeidiSQL o phpMyAdmin, `127.0.0.1`, usuario `root` sin contraseña).
3. Crear `config/conexion.local.php` apuntando a esa base y dejar el
   `require_once` de `config/comun.php` señalándolo.
4. Arrancar Apache y MySQL desde el panel de XAMPP.
5. Abrir `http://localhost/rutas-eficientes/`: siempre por `http://`, nunca
   abriendo el `index.html` con doble clic, o las cookies de sesión no
   funcionan.

---

## 11. Flujo de trabajo

Una sola rama (`master`). Los cambios se hacen y prueban en local con las tres
cuentas; recién probados se suben a GitHub y al servidor. El detalle técnico
del código (partes del sistema, recorrido de punta a punta, herramientas y uso
de IA) está en <https://nayitoo.github.io/RE-doc/tecnicismo/>.
