<?php
// auth.php
require_once __DIR__ . '/env.php';

function obtenerSistemasPermitidos() {
    cargarEnv();
    $sistemas = [];
    foreach ([
        'PIKERO_API_KEY' => 'Pikero Ferreterías',
        'COLEFY_API_KEY' => 'Colefy Colegios'
    ] as $variable => $nombre) {
        $apiKey = getenv($variable) ?: '';
        if ($apiKey !== '') {
            $sistemas[$apiKey] = $nombre;
        }
    }
    return $sistemas;
}

function validarApiKey() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    foreach ($headers as $nombre => $valor) {
        if (strcasecmp($nombre, 'Authorization') === 0) {
            $authHeader = $valor;
            break;
        }
    }
    
    if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['estatus' => false, 'mensaje' => 'No se proporcionó un API Key válido.']);
        exit;
    }

    $apiKey = $matches[1];
    $sistemasPermitidos = obtenerSistemasPermitidos();
    if (!array_key_exists($apiKey, $sistemasPermitidos)) {
        http_response_code(403);
        echo json_encode(['estatus' => false, 'mensaje' => 'Acceso denegado. API Key no reconocido.']);
        exit;
    }

    return $sistemasPermitidos[$apiKey];
}