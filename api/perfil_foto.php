<?php
/** POST /api/perfil_foto.php  (usuario logueado · multipart/form-data)
 *  campo de archivo: "foto"  ->  guarda la imagen y actualiza foto_perfil.
 *  Devuelve la ruta relativa a la raíz del sitio (empezando con "/"), que es
 *  la más confiable en hosting (AlwaysData) sin importar desde qué página se llame. */
require_once __DIR__ . '/../config/comun.php';
$u = requiere_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    $cod = $_FILES['foto']['error'] ?? 'sin archivo';
    error('No recibimos ninguna imagen (código: ' . $cod . ').');
}

$f = $_FILES['foto'];
if ($f['size'] > 3 * 1024 * 1024) error('La imagen supera los 3 MB.');

$info = @getimagesize($f['tmp_name']);
if ($info === false) error('El archivo no es una imagen válida.');
$permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime = $info['mime'] ?? '';
if (!isset($permitidos[$mime])) error('Formato no permitido. Usá JPG, PNG o WEBP.');

$ext = $permitidos[$mime];
$dir = __DIR__ . '/../uploads/fotos';
if (!is_dir($dir)) {
    // 0775 para que el usuario del servidor web pueda escribir/leer también
    if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
        error('No se pudo crear la carpeta de fotos en el servidor.', 500);
    }
}
if (!is_writable($dir)) {
    error('La carpeta uploads/fotos no tiene permisos de escritura en el servidor.', 500);
}

$nombre  = 'u' . $u['id'] . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destino = $dir . '/' . $nombre;
if (!move_uploaded_file($f['tmp_name'], $destino)) error('No se pudo guardar la imagen.', 500);
// aseguramos que el archivo sea legible por el servidor web (Apache/PHP-FPM)
@chmod($destino, 0644);

// Ruta relativa a la raíz del sitio. Deducimos el subdirectorio donde vive la app
// (por si el proyecto no está en la raíz del dominio), a partir de la ubicación de este script.
$baseDir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))); // .../ (carpeta del proyecto)
$baseDir = rtrim($baseDir, '/');
$rutaWeb = ($baseDir === '' ? '' : $baseDir) . '/uploads/fotos/' . $nombre;   // ej: /uploads/fotos/xxx.png  ó  /aprilon/uploads/fotos/xxx.png

// En la BD guardamos la ruta relativa simple (sin el subdirectorio), portable entre entornos.
$rutaBd = 'uploads/fotos/' . $nombre;
db()->prepare('UPDATE usuarios SET foto_perfil = ? WHERE id = ?')->execute([$rutaBd, $u['id']]);
$_SESSION['usuario']['foto'] = $rutaBd;

// Al front le devolvemos la ruta absoluta desde la raíz + un cache-buster para que
// el navegador no muestre una versión vieja cacheada.
responder(['ok' => true, 'foto' => $rutaWeb . '?v=' . time()]);
