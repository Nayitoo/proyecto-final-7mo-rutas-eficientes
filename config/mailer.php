<?php
/**
 * Cliente SMTP mínimo, sin dependencias (no hay Composer en este proyecto,
 * ver comentario equivalente en google_routes.php). Soporta STARTTLS + AUTH LOGIN,
 * suficiente para Gmail/Outlook/cualquier SMTP estándar.
 */
declare(strict_types=1);
require_once __DIR__ . '/env.php';

class MailerError extends RuntimeException {}

/** Manda un email de texto plano. Si no hay SMTP_HOST configurado en .env,
 *  o si falla la conexión/autenticación, no revienta nada: devuelve false y
 *  sigue — la palabra de verificación de todas formas queda guardada en la
 *  base, así que el resto del flujo funciona igual aunque el correo no salga.
 *  IMPORTANTE: no usar error_log() acá ni en ningún lugar que este archivo
 *  pueda alcanzar — en el servidor ILM (IIS + PHP-FastCGI) cualquier escritura
 *  a stderr (que es adonde termina yendo error_log() por default en ese
 *  entorno) hace que IIS tire toda la respuesta HTTP y devuelva un 500 propio,
 *  aunque el resto del código ya haya terminado bien. Se encontró probando
 *  esta misma feature — no es un problema teórico. */
function enviar_email(string $destino, string $asunto, string $cuerpo): bool {
    $host = env('SMTP_HOST', '');
    if ($host === '') return false;

    $port     = (int)env('SMTP_PORT', '587');
    $user     = env('SMTP_USER', '');
    $pass     = env('SMTP_PASS', '');
    $from     = env('SMTP_FROM', $user !== '' ? $user : 'no-responder@aprilon.local');
    $fromName = env('SMTP_FROM_NAME', 'Aprilon');

    try {
        smtp_enviar($host, $port, $user, $pass, $from, $fromName, $destino, $asunto, $cuerpo);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function smtp_enviar(
    string $host, int $port, string $user, string $pass,
    string $from, string $fromName, string $destino, string $asunto, string $cuerpo
): void {
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new MailerError("No se pudo conectar a $host:$port ($errstr)");

    $leer = function () use ($fp): string {
        $resp = '';
        while (($linea = fgets($fp, 515)) !== false) {
            $resp .= $linea;
            if (isset($linea[3]) && $linea[3] === ' ') break;
        }
        return $resp;
    };
    $escribir = function (string $cmd) use ($fp): void { fwrite($fp, $cmd . "\r\n"); };
    $esperar = function (string $esperado) use ($leer): string {
        $resp = $leer();
        if (strpos($resp, $esperado) !== 0) throw new MailerError("Respuesta SMTP inesperada: " . trim($resp));
        return $resp;
    };

    $esperar('220');
    $escribir('EHLO aprilon.local'); $esperar('250');

    if ($port === 587 || $port === 25) {
        $escribir('STARTTLS'); $esperar('220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new MailerError('No se pudo iniciar TLS con el servidor SMTP.');
        }
        $escribir('EHLO aprilon.local'); $esperar('250');
    }

    if ($user !== '') {
        $escribir('AUTH LOGIN'); $esperar('334');
        $escribir(base64_encode($user)); $esperar('334');
        $escribir(base64_encode($pass)); $esperar('235');
    }

    $escribir('MAIL FROM:<' . $from . '>'); $esperar('250');
    $escribir('RCPT TO:<' . $destino . '>'); $esperar('250');
    $escribir('DATA'); $esperar('354');

    $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    $headers =
        "From: $fromName <$from>\r\n" .
        "To: <$destino>\r\n" .
        "Subject: $asuntoCodificado\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        "MIME-Version: 1.0\r\n";
    $cuerpoEscapado = preg_replace('/^\./m', '..', $cuerpo); // "." solo en una línea termina el DATA en SMTP
    $escribir($headers . "\r\n" . $cuerpoEscapado . "\r\n.");
    $esperar('250');
    $escribir('QUIT');
    fclose($fp);
}
