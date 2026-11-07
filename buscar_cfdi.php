<?php
// buscar_cfdi.php — consulta si ya existe un CFDI emitido con ese Folio + Date para el emisor.
header('Content-Type: application/json; charset=utf-8');
require_once 'auth.php';
require_once 'FacturamaService.php';
require_once 'IssuerRegistry.php';

$sistema_cliente = validarApiKey();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['estatus' => false, 'mensaje' => 'Cuerpo JSON inválido']);
    exit;
}

$clientId = trim((string)($input['client_id'] ?? ''));
$issuerId = filter_var($input['issuer_id'] ?? null, FILTER_VALIDATE_INT);
$folio    = trim((string)($input['folio'] ?? ''));
$date     = trim((string)($input['date'] ?? ''));
$consulta_rapida = filter_var($input['quick'] ?? false, FILTER_VALIDATE_BOOLEAN);

if($consulta_rapida) {
   
}

if ($clientId === '' || strlen($clientId) > 100 || !preg_match('/^[A-Za-z0-9._:-]+$/', $clientId)
    || !$issuerId || $issuerId < 1 || $folio === '' || (!$consulta_rapida && $date === '')) {
    http_response_code(400);
    echo json_encode(['estatus' => false, 'mensaje' => 'Envía client_id, issuer_id, folio y date.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $registry = new IssuerRegistry();
    $emisor = $registry->findForClient($sistema_cliente, $clientId, $issuerId);
    if (!$emisor) {
        http_response_code(403);
        echo json_encode(['estatus' => false, 'mensaje' => 'El emisor indicado no está asignado a este cliente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $res = (new FacturamaService())->buscarCfdis(['keyword' => $folio]);
    if (!$res['ok']) {
        http_response_code((int)($res['status'] ?: 502));
        echo json_encode(['estatus' => false, 'mensaje' => 'No se pudo consultar Facturama.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lista = is_array($res['data']) ? $res['data'] : [];
    $encontrado = null;
    foreach ($lista as $cfdi) {
        if (!is_array($cfdi)) continue;
        // 1) Folio exacto (keyword es búsqueda amplia)
        if ((string)($cfdi['Folio'] ?? '') !== $folio) continue;
        // 2) Misma Date que se guardó antes de timbrar
         if (!$consulta_rapida
         && substr((string)($cfdi['Date'] ?? ''), 0, 19) !== substr($date, 0, 19)) continue;
        // 3) Mismo emisor, si el listado trae ese dato (verifica el nombre del campo en sandbox)
        $rfcEmisor = $cfdi['IssuerRfc'] ?? ($cfdi['Issuer']['Rfc'] ?? null);
        if ($rfcEmisor !== null && strcasecmp((string)$rfcEmisor, (string)$emisor['issuer_rfc']) !== 0) continue;

        // Una factura ya cancelada en SAT no debe bloquear la cancelación de la venta
        // (verifica en sandbox el nombre/valor real del campo de estatus)
        if (strcasecmp((string)($cfdi['Status'] ?? ''), 'canceled') === 0) continue;


        $encontrado = [
            'Id' => $cfdi['Id'] ?? null,
            'TaxStamp' => ['Uuid' => $cfdi['Uuid'] ?? ($cfdi['TaxStamp']['Uuid'] ?? null)]
        ];
        break;
    }

    echo json_encode(['estatus' => true, 'data' => $encontrado], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Error al consultar CFDI por folio: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['estatus' => false, 'mensaje' => 'No fue posible procesar la consulta.'], JSON_UNESCAPED_UNICODE);
}