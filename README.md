# Aprilon · Gestión de rutas de entrega

Aplicación web para **Aprilon Textiles**. Un administrador carga las direcciones
de entrega del día, el sistema calcula el orden óptimo de las paradas con Google
Maps, y el conductor sigue esa ruta desde el celular marcando cada entrega.

**No hay nada que instalar para usarla: se entra por el navegador.**

---

## 1. Cómo se usa

Abrí la aplicación y entrá con tu usuario. Funciona en la computadora y en el
celular, y no requiere instalar ninguna app.

| Entorno | Dirección | Notas |
|---|---|---|
| **Servidor del instituto (ILM)** | `http://192.168.101.92:8091/aprilon/` | Es el entorno en uso. Solo accesible desde la red del instituto |
| Producción | *pendiente de despliegue* | Ver la sección 9 |

### Cuentas

| Usuario | Rol | Qué puede hacer |
|---|---|---|
| `jefe` | Jefe | Administra las cuentas y las contraseñas del equipo |
| `ventas` | Administrador | Carga los puntos de entrega, calcula y publica las rutas |
| `expedicion` | Conductor | Sigue la ruta publicada, marca las paradas y reporta imprevistos |

La contraseña de las tres cuentas de demostración es `aprilon2026`.

> **Antes de un despliegue a producción real**, cambiá estas contraseñas: los
> hashes de `db/aprilon.sql` corresponden a una clave conocida y documentada.

---

## 2. Qué hace la aplicación

### Administrador
- **Carga de puntos** con autocompletado de direcciones (Google Places), o
  eligiendo de una agenda de lugares guardados.
- **Cálculo de la ruta óptima**: Google Routes reordena las paradas para
  minimizar el recorrido, teniendo en cuenta el tránsito.
- **Publicación de la ruta** para que el conductor la vea, y despublicación si
  hay que corregirla.
- **Programación a futuro**: se pueden dejar armadas varias rutas con fecha, no
  solo la del día.
- **Verificaciones** (opcional por parada): al publicar la ruta se genera una
  palabra al azar y se envía por email al encargado del negocio. El conductor
  tiene que pedírsela para poder cerrar esa entrega.
- **Historial** de rutas y **avisos de imprevistos** en tiempo real.

### Conductor
- Ve la ruta del día en un mapa, con la próxima parada destacada.
- **Seguimiento por GPS** y modo pantalla completa; el botón de navegación abre
  Google Maps con indicaciones por voz.
- Marca cada parada como completada (pidiendo la palabra de verificación si esa
  parada la requiere) y **reporta imprevistos** al administrador.

### Jefe
- Alta, baja y edición de cuentas; restablecimiento de contraseñas.
- Atiende los pedidos de "olvidé mi contraseña" que llegan desde el login.

---

## 3. Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8 + PDO, sin framework |
| Base de datos | MySQL / MariaDB |
| Frontend | HTML + CSS + JavaScript en un solo archivo (`index.html`), sin build step |
| Mapa | Leaflet con tiles de OpenStreetMap / CartoDB |
| Rutas y direcciones | Google Maps: Geocoding v4, Routes, Places |
| Email | SMTP propio (`config/mailer.php`), sin dependencias externas |
| Offline | Service Worker (`sw.js`) que cachea los tiles del mapa |

La API key de Google se usa **solo del lado del servidor**; nunca llega al
navegador. El Service Worker requiere HTTPS, así que el mapa offline funciona en
`localhost` y en un hosting con certificado, pero no sobre HTTP plano.

---

## 4. Estructura

```
aprilon/
├── index.html            Toda la interfaz (las 3 vistas por rol)
├── sw.js                 Service Worker: caché de tiles del mapa
├── config/
│   ├── comun.php         Sesión, guards de rol, respuestas JSON
│   ├── conexion*.php     Conexión PDO (una por entorno, fuera del repo)
│   ├── env.php           Lectura del .env
│   ├── google_routes.php Llamadas a Google (Routes, Geocoding, Places)
│   ├── mailer.php        Envío SMTP
│   └── verificaciones.php Palabras de verificación de entrega
├── api/                  31 endpoints JSON
├── db/                   Esquema SQL
└── uploads/fotos/        Fotos de perfil (con .htaccess de protección)
```

---

## 5. Base de datos

Tres archivos, que se importan en este orden:

| Archivo | Tablas |
|---|---|
| `db/aprilon.sql` | `usuarios`, `solicitudes_password` |
| `db/rutas.sql` | `puntos_guardados`, `rutas`, `ruta_paradas`, `verificaciones` |
| `db/incidentes.sql` | `incidentes` |

---

## 6. API

Todos los endpoints están bajo `…/aprilon/api/` y responden JSON.

### Sesión y cuenta
| Método | Endpoint | Rol |
|---|---|---|
| POST | `login.php` | público |
| POST | `logout.php` | público |
| GET | `sesion.php` | público |
| GET | `config_publico.php` | público |
| POST | `solicitud_crear.php` | público |
| POST | `password_resetear.php` | público |
| POST | `password_cambiar.php` | logueado |
| POST | `perfil_foto.php` / `perfil_foto_quitar.php` | logueado |

### Administración de usuarios
| Método | Endpoint | Rol |
|---|---|---|
| GET | `usuarios_listar.php` | jefe |
| POST | `usuarios_guardar.php` / `usuarios_eliminar.php` | jefe |
| POST | `password_restablecer.php` | jefe |
| GET | `solicitudes_listar.php` | jefe |
| POST | `solicitud_resolver.php` | jefe |

### Puntos y rutas
| Método | Endpoint | Rol |
|---|---|---|
| GET | `lugares_autocompletar.php` / `lugares_detalle.php` | admin |
| GET | `puntos_guardados_listar.php` | admin |
| POST | `puntos_guardados_guardar.php` / `puntos_guardados_eliminar.php` | admin |
| POST | `ruta_calcular.php` | admin |
| POST | `ruta_guardar.php` | admin |
| POST | `ruta_despublicar.php` | admin |
| POST | `rutas_borrar_hoy.php` | admin |
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
- **Consultas preparadas** con PDO en todos los endpoints: sin inyección SQL.
- **Guard de rol** en cada endpoint (`requiere_rol()`); un conductor recibe 403
  al pedir un endpoint del jefe.
- Sesión con cookie **HttpOnly**.
- `uploads/fotos/` tiene un `.htaccess` que **impide ejecutar scripts subidos**.
- La regla de **una sola cuenta de administrador** la refuerza la base, con una
  columna generada y el índice único `uq_admin_unica`.
- Credenciales y API keys viven en `.env` y en `config/conexion*.php`, ninguno
  de los dos versionado.

---

## 8. Configuración (`.env`)

Copiá `.env.example` a `.env` y completá:

| Variable | Para qué |
|---|---|
| `GOOGLE_MAPS_API_KEY` | Geocoding, Routes y Places |
| `APP_DEMO` | `true` muestra las cuentas de prueba en el login. **`false` en producción** |
| `SMTP_HOST` · `SMTP_PORT` · `SMTP_USER` · `SMTP_PASS` · `SMTP_FROM` | Envío de las palabras de verificación. Sin esto la palabra se genera igual, solo que no sale el email |

La conexión a la base se elige con una sola línea en `config/comun.php`, que
hace `require_once` del `config/conexion*.php` del entorno correspondiente.

---

## 9. Desplegar en un hosting

1. Subir el proyecto por SFTP.
2. Crear la base e importar los tres `.sql` de la sección 5.
3. Crear `config/conexion.php` con las credenciales del hosting y apuntar ahí el
   `require_once` de `config/comun.php`.
4. Crear el `.env` de la sección 8.
5. Dar permiso de escritura a `uploads/fotos/`.
6. **Proteger `config/` y `db/`**: el `web.config` incluido sirve para IIS. En
   Apache hace falta un `.htaccess` equivalente, o esas carpetas quedan
   descargables por URL.
7. Cambiar las contraseñas de las cuentas de demostración.

---

## 10. Apéndice · Levantar una copia local (XAMPP)

Solo hace falta para desarrollar o probar cambios. **Para usar la aplicación no
se instala nada**, se entra por la dirección de la sección 1.

1. Copiar el proyecto a `C:\xampp\htdocs\aprilon\`.
2. Importar los tres `.sql` de la sección 5 en la base `aprilon` (con HeidiSQL o
   phpMyAdmin, conectando a `127.0.0.1` con usuario `root` y sin contraseña).
3. Crear `config/conexion.local.php` apuntando a esa base, y dejar el
   `require_once` de `config/comun.php` señalándolo.
4. Arrancar Apache y MySQL desde el panel de XAMPP.
5. Abrir `http://localhost/aprilon/` — siempre por `http://`, nunca abriendo el
   `index.html` con doble clic, o las cookies de sesión no funcionan.
