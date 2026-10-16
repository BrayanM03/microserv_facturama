<?php
// facturar.php
header('Content-Type: application/json; charset=utf-8');
require_once 'auth.php';
require_once 'FacturamaService.php';
require_once 'IssuerRegistry.php';

// 1. Validar que quien llama es Pikero o Colefy
$sistema_cliente = validarApiKey();

// 2. Recibir el JSON enviado por tus sistemas
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['estatus' => false, 'mensaje' => 'Cuerpo JSON inválido']);
    exit;
}

$payload = $input['cfdi_payload'] ?? null;
$clientId = trim((string) ($input['client_id'] ?? ''));
$issuerId = filter_var($input['issuer_id'] ?? null, FILTER_VALIDATE_INT);

if (!is_array($payload) || $clientId === '' || strlen($clientId) > 100 || !preg_match('/^[A-Za-z0-9._:-]+$/', $clientId) || !$issuerId || $issuerId < 1) {
    http_response_code(400);
    echo json_encode([
        'estatus' => false,
        'mensaje' => 'Envía client_id, issuer_id y el nodo cfdi_payload.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $registry = new IssuerRegistry();
    $emisor = $registry->findForClient($sistema_cliente, $clientId, $issuerId);
    if (!$emisor) {
        http_response_code(403);
        echo json_encode([
            'estatus' => false,
            'mensaje' => 'El emisor indicado no está asignado a este cliente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload['Issuer'] = [
        'Rfc' => $emisor['issuer_rfc'],
        'Name' => $emisor['issuer_name'],
        'FiscalRegime' => $emisor['fiscal_regime']
    ];

    $facturama = new FacturamaService();
    $resultado = $facturama->crearCfdi($payload);
    if (!$resultado['estatus']) {
    http_response_code((int)($resultado['status'] ?: 502));
}
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Error al preparar emisión CFDI: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'estatus' => false,
        'mensaje' => 'No fue posible procesar la solicitud de facturación.'
    ], JSON_UNESCAPED_UNICODE);
}
