<?php
/**
 * "Verificaciones" — palabra de confirmación de entrega por punto de la ruta.
 * El admin carga un email opcional al armar la ruta (dueño/encargado del
 * negocio); al publicar, se genera una palabra al azar, se guarda temporalmente
 * en `verificaciones` y se manda por email. El conductor tiene que pedirle esa
 * palabra a esa persona y escribirla en la app para poder completar la parada.
 */
declare(strict_types=1);
require_once __DIR__ . '/mailer.php';

function generar_palabra_verificacion(): string {
    $palabras = [
        'ATLAS','BRISA','CEDRO','DELTA','FARO','GALA','HALO','IRIS','JADE','KIWI',
        'LUNA','MAPLE','NIEVE','OASIS','PLUMA','RIO','SOL','TREN','URNA','VELA',
        'WIFI','YESO','ZAFIRO','COMETA','ROBLE','TIGRE',
    ];
    $palabra = $palabras[random_int(0, count($palabras) - 1)];
    return $palabra . random_int(10, 99);
}

/** Crea (reemplazando cualquier verificación previa de esa parada) y envía por
 *  email la palabra de verificación. Devuelve la palabra generada. */
function crear_verificacion_para_parada(int $rutaParadaId, string $email, string $nombreParada): string {
    $palabra = generar_palabra_verificacion();
    $pdo = db();
    $pdo->prepare('DELETE FROM verificaciones WHERE ruta_parada_id = ?')->execute([$rutaParadaId]);
    $pdo->prepare(
        'INSERT INTO verificaciones (ruta_parada_id, email, palabra) VALUES (?,?,?)'
    )->execute([$rutaParadaId, $email, $palabra]);

    enviar_email(
        $email,
        'Código de verificación de entrega - Aprilon',
        "Hola,\n\n" .
        "Un conductor de Aprilon va a pasar hoy por \"$nombreParada\" a hacer una entrega.\n" .
        "Cuando llegue, decile esta palabra para confirmar que la entrega es correcta:\n\n" .
        "    $palabra\n\n" .
        "Si no esperabas este mensaje, podés ignorarlo.\n"
    );

    return $palabra;
}

/** true si esa parada tiene una verificación pendiente (sin confirmar todavía). */
function parada_requiere_verificacion(int $rutaParadaId): bool {
    $st = db()->prepare('SELECT id FROM verificaciones WHERE ruta_parada_id = ? AND verificado_en IS NULL');
    $st->execute([$rutaParadaId]);
    return (bool)$st->fetch();
}

/** Valida la palabra que ingresó el conductor. Si es correcta, marca la
 *  verificación como resuelta y devuelve true; si no, suma un intento fallido
 *  y devuelve false. */
function validar_verificacion(int $rutaParadaId, string $palabraIngresada): bool {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM verificaciones WHERE ruta_parada_id = ? AND verificado_en IS NULL');
    $st->execute([$rutaParadaId]);
    $v = $st->fetch();
    if (!$v) return true; // no había verificación pendiente: no bloquea la entrega

    if (strcasecmp(trim($palabraIngresada), (string)$v['palabra']) === 0) {
        $pdo->prepare('UPDATE verificaciones SET verificado_en = NOW() WHERE id = ?')->execute([$v['id']]);
        return true;
    }
    $pdo->prepare('UPDATE verificaciones SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?')->execute([$v['id']]);
    return false;
}
