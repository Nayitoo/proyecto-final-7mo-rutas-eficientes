# Aprilon · Backend PHP

Backend del mockup **Aprilon**, en **PHP 8 + PDO + MariaDB/MySQL**,
pensado para correr en **XAMPP**. El mockup (`index.html`) está conectado
en vivo a este backend: login, gestión de usuarios, contraseñas y foto de perfil
funcionan contra la base de datos real.

---

## 1. Instalación (5 minutos)

1. **Copiá la carpeta `aprilon/` dentro de `htdocs` de XAMPP.**
   Por ejemplo: `C:\xampp\htdocs\aprilon-backend\`
2. **Copiá también `index.html` dentro de esa misma carpeta**, para que quede así:
   ```
   htdocs/aprilon/
   ├── index.html        ← el mockup
   ├── api/…
   ├── config/…
   ├── db/aprilon_db.sql
   └── uploads/…
   ```
   (Es importante que el mockup esté en el mismo origen que el backend
   para que las cookies de sesión funcionen sin configurar nada.)
3. **Importá la base de datos**: abrí HeidiSQL, conectate a `127.0.0.1` con usuario `root`
   sin contraseña, y hacé **Archivo → Ejecutar archivo SQL…** eligiendo
   `db/aprilon_db.sql`. Se crea la base `aprilon` con usuarios de ejemplo.
4. **Arrancá Apache y MySQL** desde el panel de XAMPP.
5. **Abrí el mockup en el navegador**:
   ```
   http://localhost/aprilon/
   ```
   ⚠️ Siempre por `http://localhost/…`, nunca con doble clic al archivo.

## 2. Credenciales de ejemplo

| Usuario      | Contraseña    | Rol           |
|--------------|---------------|---------------|
| `jefe`       | `aprilon2026` | jefe          |
| `ventas`     | `aprilon2026` | administrador (cuenta compartida) |
| `expedicion` | `aprilon2026` | conductor     |

## 3. Qué funciona conectado a la base

- ✅ **Login real** con `password_verify()` (bcrypt).
- ✅ **Sesión persistente**: si cerrás y volvés a abrir el mockup, seguís logueado.
- ✅ **Logout** cierra la sesión en el servidor.
- ✅ **Panel del jefe** lee usuarios y solicitudes de la DB.
- ✅ **Alta / edición / baja** de conductores desde la app.
- ✅ **Restablecer contraseña** desde la pantalla del jefe (con clave elegida o autogenerada).
- ✅ **"Olvidé mi contraseña"** desde el login: el usuario avisa al jefe, el jefe le pasa una clave temporal, el usuario elige la nueva.
- ✅ **Foto de perfil** subida y borrada contra `uploads/fotos/`.
- ✅ **Guards de rol**: un conductor recibe HTTP 403 al intentar endpoints del jefe.
- ✅ **Una sola cuenta de administrador** garantizada por la base (índice único).

## 4. Endpoints

Base: `…/aprilon/api/`

| Método | Endpoint | Rol | Descripción |
|---|---|---|---|
| POST | `login.php` | público | `{usuario, password}` |
| POST | `logout.php` | logueado | — |
| GET  | `sesion.php` | público | ¿hay sesión activa? |
| GET  | `usuarios_listar.php` | jefe | jefe + admin + conductores |
| POST | `usuarios_guardar.php` | jefe | crear/editar (`id` opcional para editar) |
| POST | `usuarios_eliminar.php` | jefe | solo conductores |
| POST | `password_restablecer.php` | jefe | `{id, password?, debe_cambiar?}` |
| POST | `password_cambiar.php` | logueado | `{password_actual, password_nueva}` |
| POST | `password_resetear.php` | público | `{usuario, password_temporal, password_nueva}` |
| POST | `solicitud_crear.php` | público | `{usuario}` (olvidé mi contraseña) |
| GET  | `solicitudes_listar.php` | jefe | solicitudes pendientes |
| POST | `solicitud_resolver.php` | jefe | marca resuelta |
| POST | `perfil_foto.php` | logueado | `multipart/form-data` campo `foto` |
| POST | `perfil_foto_quitar.php` | logueado | quita la foto de perfil |

## 5. Seguridad

- Contraseñas con **bcrypt** (`password_hash()` / `password_verify()`), nunca en texto plano.
- **Consultas preparadas** (PDO): sin inyección SQL.
- Guards de **rol** en cada endpoint; sesión con cookie `HttpOnly`.
- Carpeta `uploads/fotos/` con `.htaccess` que impide ejecutar scripts subidos.
- La regla de **una sola cuenta de administrador** está reforzada por la base
  (columna generada + índice único `uq_admin_unica`).

## 6. Configuración

Revisá `config/conexion.php`. Por defecto ya apunta al root de XAMPP:
```php
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
```

## 7. Estructura

```
aprilon/
├── config/
│   ├── conexion.php      Conexión PDO
│   └── comun.php         CORS, sesión, respuestas JSON, guards de rol
├── api/                  13 endpoints
├── db/
│   └── aprilon_db.sql    Esquema + datos de ejemplo
├── uploads/fotos/        Fotos de perfil (con .htaccess de protección)
└── README.md
```

## 8. Próximo paso (opcional)

La base cubre **usuarios**. Si querés persistir también **rutas, puntos e imprevistos**,
se agregan sus tablas y endpoints siguiendo el mismo patrón. Avisame y lo extiendo.
