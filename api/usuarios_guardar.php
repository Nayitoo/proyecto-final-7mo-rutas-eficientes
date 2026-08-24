<?php
/** POST /api/usuarios_guardar.php  (solo jefe)
 *  Crear conductor:  { usuario, nombre, zona?, password? }
 *  Editar usuario:   { id, usuario, nombre, zona?, activo?, password? }
 *  La cuenta de administración es única: no se crea otra, solo se edita.
 *  Al crear sin contraseña, se genera una temporal y se devuelve. */
require_once __DIR__ . '/../config/comun.php';
requiere_rol(['jefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Método no permitido.', 405);

$in       = cuerpo();
$id       = isset($in['id']) ? (int)$in['id'] : 0;
$usuario  = trim((string)($in['usuario'] ?? ''));
$nombre   = trim((string)($in['nombre']  ?? ''));
$zona     = trim((string)($in['zona']    ?? ''));
$password = (string)($in['password'] ?? '');

if ($usuario === '' || $nombre === '') error('Completá nombre y usuario.');

if ($id > 0) {
    // ---- EDITAR ----
    $st = db()->prepare('SELECT id, usuario, activo, rol FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) error('No encontramos ese usuario.', 404);
    if ($u['rol'] === 'jefe') error('La cuenta del jefe no se edita desde acá.', 403);

    $activo = array_key_exists('activo', $in) ? ((int)!!$in['activo']) : (int)$u['activo'];
    $sets   = 'usuario=?, nombre=?, zona=?, activo=?';
    $params = [$usuario, $nombre, ($zona ?: null), $activo];
    if ($password !== '') {
        $sets .= ', password_hash=?, password_temporal=NULL, debe_cambiar=0';
        $params[] = password_hash($password, PASSWORD_BCRYPT);
    }
    $params[] = $id;
    try {
        db()->prepare("UPDATE usuarios SET $sets WHERE id=?")->execute($params);
    } catch (PDOException $e) {
        // 23000 = violación de restricción única (usuario duplicado)
        if ($e->getCode() === '23000') error('Ese usuario ya está en uso.', 409);
        error('No se pudo guardar el usuario.', 500);
    }
    responder(['ok' => true, 'id' => $id]);
}

// ---- CREAR (siempre conductor) ----
$temporal = ($password === '');
if ($temporal) $password = generar_password_temporal();
$hash = password_hash($password, PASSWORD_BCRYPT);
try {
    db()->prepare(
        "INSERT INTO usuarios
            (usuario, password_hash, nombre, rol, zona, activo, password_temporal, debe_cambiar)
         VALUES (?, ?, ?, 'conductor', ?, 1, ?, ?)"
    )->execute([
        $usuario,
        $hash,
        $nombre,
        ($zona ?: null),
        $temporal ? $password : null,   // solo guardo la clave en texto plano si es temporal
        $temporal ? 1 : 0,               // debe_cambiar = 1 si es temporal
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') error('Ese usuario ya está en uso.', 409);
    error('No se pudo crear el usuario.', 500);
}
responder(['ok' => true, 'id' => (int)db()->lastInsertId(), 'password_temporal' => $temporal ? $password : null]);
