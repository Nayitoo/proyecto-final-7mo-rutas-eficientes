<?php
/**
 * Carga variables desde .env (raíz del proyecto) a getenv()/$_ENV.
 * Sin dependencias externas. No falla si el archivo no existe.
 */
declare(strict_types=1);

function cargar_env(): void {
    static $cargado = false;
    if ($cargado) return;
    $cargado = true;

    $ruta = __DIR__ . '/../.env';
    if (!is_file($ruta)) return;

    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);
        putenv("$clave=$valor");
        $_ENV[$clave] = $valor;
    }
}

function env(string $clave, ?string $default = null): ?string {
    cargar_env();
    $v = getenv($clave);
    return $v !== false ? $v : $default;
}
