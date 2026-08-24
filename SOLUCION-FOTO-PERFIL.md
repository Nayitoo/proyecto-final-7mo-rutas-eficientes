# Solución: la foto de perfil no se veía en AlwaysData

## Qué pasaba
La foto se subía y la ruta quedaba guardada en la base de datos, pero la imagen
no se mostraba en la app. La causa era el archivo `uploads/fotos/.htaccess`, que
tenía la línea `php_flag engine off`. En AlwaysData (y cualquier hosting con
PHP-FPM) esa directiva **no está permitida** y hace que Apache devuelva un
error 500 en toda la carpeta `uploads/fotos/`. Entonces el navegador pedía la
imagen y recibía un error en vez del archivo → el avatar no cambiaba.

En XAMPP funcionaba porque ahí PHP corre como módulo de Apache y sí acepta `php_flag`.

## Qué se cambió
1. **`uploads/fotos/.htaccess`**: se reescribió sin `php_flag`, usando solo
   `FilesMatch` + `RemoveHandler` (compatible con PHP-FPM). Sigue impidiendo que
   se ejecuten scripts subidos, pero ya no rompe la carpeta.
2. **`api/perfil_foto.php`**: ahora devuelve la ruta de la imagen absoluta desde
   la raíz del sitio (`/uploads/fotos/...`) con un cache-buster (`?v=...`), y avisa
   con un mensaje claro si la carpeta no tiene permisos de escritura.
3. **`index.html`**: el avatar resuelve mejor la ruta de la foto (funciona tanto
   si la app está en la raíz del dominio como en un subdirectorio).

## Permisos de la carpeta (importante en AlwaysData)
Para que PHP pueda **escribir** las fotos, la carpeta `uploads/fotos/` necesita
permiso de escritura. Con FileZilla:

1. Click derecho sobre la carpeta `uploads` → **Permisos de archivo…**
2. Poné el valor numérico **755** (o **775** si 755 no alcanza).
3. Marcá **"Aplicar a directorios recursivamente"** y aceptá.

Si al subir una foto ves el mensaje *"La carpeta uploads/fotos no tiene permisos
de escritura"*, subí el permiso de esa carpeta a **775**.

## Cómo verificar que quedó bien
1. Entrá a la app y cambiá la foto de perfil de un usuario.
2. Abrí directamente en el navegador la URL de la imagen, por ejemplo:
   `https://tu-dominio/uploads/fotos/u1_xxxxx.png`
   Tenés que ver la imagen (no un error 500 ni 403).
3. Recargá la app: la foto tiene que seguir apareciendo.
