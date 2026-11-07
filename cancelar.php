<?php
// cancelar.php
header('Content-Type: application/json; charset=utf-8');
require_once 'auth.php';
require_once 'FacturamaService.php';
require_once 'IssuerRegistry.php';

$sistema_cliente = validarApiKey();

function responder(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    responder(400, ['estatus' => false, 'mensaje' => 'Cuerpo JSON inválido']);
}

$clientId = trim((string)($input['client_id'] ?? ''));
$issuerId = filter_var($input['issuer_id'] ?? null, FILTER_VALIDATE_INT);
$cfdiId   = trim((string)($input['id'] ?? ''));
$motivo   = trim((string)($input['motive'] ?? ''));
$uuidRep  = strtolower(trim((string)($input['uuid_replacement'] ?? '')));

if ($clientId === '' || strlen($clientId) > 100 || !preg_match('/^[A-Za-z0-9._:-]+$/', $clientId)
    || !$issuerId || $issuerId < 1
    || $cfdiId === '' || !preg_match('/^[A-Za-z0-9_-]{10,60}$/', $cfdiId)) {
    responder(400, ['estatus' => false, 'mensaje' => 'Envía client_id, issuer_id e id del CFDI válidos.']);
}
if (!in_array($motivo, ['01', '02', '03', '04'], true)) {
    responder(400, ['estatus' => false, 'mensaje' => 'Motivo de cancelación inválido (01, 02, 03 o 04).']);
}
if ($motivo === '01') {
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuidRep)) {
        responder(400, ['estatus' => false, 'mensaje' => 'El motivo 01 requiere uuid_replacement válido.']);
    }
} else {
    $uuidRep = '';
}

try {
    // 1. El emisor debe pertenecer al cliente que llama
    $emisor = (new IssuerRegistry())->findForClient($sistema_cliente, $clientId, $issuerId);
    if (!$emisor) {
        responder(403, ['estatus' => false, 'mensaje' => 'El emisor indicado no está asignado a este cliente.']);
    }

    $facturama = new FacturamaService();

    // 2. El CFDI debe pertenecer a ese emisor (todos comparten la misma cuenta de Facturama)
    $cfdi = $facturama->obtenerCfdi($cfdiId);
    if (!$cfdi['ok']) {
        responder(($cfdi['status'] ?: 502) === 404 ? 404 : 502,
            ['estatus' => false, 'mensaje' => 'No se pudo consultar el CFDI en Facturama.']);
    }
    $rfcCfdi = strtoupper((string)($cfdi['data']['Issuer']['Rfc'] ?? ''));
    if ($rfcCfdi === '' || $rfcCfdi !== strtoupper((string)$emisor['issuer_rfc'])) {
        responder(403, ['estatus' => false, 'mensaje' => 'El CFDI no pertenece al emisor indicado.']);
    }

    // 3. Cancelar
    $resultado = $facturama->cancelarCfdi($cfdiId, $motivo, $uuidRep);
    if (!$resultado['ok']) {
        $msg = $resultado['data']['Message'] ?? $resultado['data']['message'] ?? 'Error al cancelar el CFDI';
        responder((int)($resultado['status'] ?: 502), ['estatus' => false, 'mensaje' => $msg, 'detalles' => $resultado['data']]);
    }

    $satStatus = $resultado['data']['Status'] ?? '';
    if ($satStatus === 'active') {
        responder(409, ['estatus' => false, 'mensaje' => 'El CFDI no se puede cancelar: tiene documentos relacionados.', 'detalles' => $resultado['data']]);
    }

    responder(200, [
        'estatus'    => true,
        'sat_status' => $satStatus,   // canceled | pending
        'mensaje'    => $satStatus === 'pending'
            ? 'Solicitud enviada. El receptor tiene hasta 72 horas para aceptar la cancelación.'
            : 'Factura cancelada correctamente.',
        'data'       => $resultado['data']
    ]);
} catch (Throwable $e) {
    error_log('Error al cancelar CFDI: ' . $e->getMessage());
    responder(500, ['estatus' => false, 'mensaje' => 'No fue posible procesar la cancelación.']);
}