<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/IssuerRegistry.php';

function responderEmisores(int $status, array $contenido): void {
    http_response_code($status);
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
    exit; 
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    responderEmisores(405, ['estatus' => false, 'mensaje' => 'Método no permitido.']);
}

$sistema = validarApiKey();
$clientId = trim((string) ($_GET['client_id'] ?? ''));
if ($clientId === '' || strlen($clientId) > 100 || !preg_match('/^[A-Za-z0-9._:-]+$/', $clientId)) {
    responderEmisores(400, ['estatus' => false, 'mensaje' => 'Envía un client_id válido de Pikero.']);
}

try {
    $registry = new IssuerRegistry();
    $emisores = $registry->listForClient($sistema, $clientId);
    $data = array_map(static function ($emisor) {
        return [
            'issuer_id' => (int) $emisor['id'],
            'client_id' => $emisor['client_id'],
            'client_name' => $emisor['client_name'],
            'rfc' => $emisor['issuer_rfc'],
            'name' => $emisor['issuer_name'],
            'fiscal_regime' => $emisor['fiscal_regime']
        ];
    }, $emisores);
    responderEmisores(200, ['estatus' => true, 'data' => $data]);
} catch (Throwable $error) {
    error_log('Error al consultar emisores asignados: ' . $error->getMessage());
    responderEmisores(500, ['estatus' => false, 'mensaje' => 'No se pudieron consultar los emisores.']);
}