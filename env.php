<?php
// env.php
if (!function_exists('cargarEnv')) {
    function cargarEnv(?string $envPath = null): void {
        static $cargado = false;
        if ($cargado) {
            return;
        }
        $envPath = $envPath ?? __DIR__ . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#') === 0 || strpos($line, '!') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim(trim($value), "\"'");
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
        $cargado = true;
    }
}