<?php
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/FacturamaService.php';
require_once __DIR__ . '/IssuerRegistry.php';

function responderCfdiJson(int $status, array $contenido): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
    exit;
}

function obtenerEmisorCfdi(array $cfdi): string {
    return strtoupper(trim((string) ($cfdi['Issuer']['Rfc'] ?? $cfdi['Issuer']['RFC'] ?? $cfdi['IssuerRfc'] ?? '')));
}

$sistema = validarApiKey();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$entrada = $metodo === 'GET' ? $_GET : json_decode(file_get_contents('php://input'), true);

if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    responderCfdiJson(405, ['estatus' => false, 'mensaje' => 'Método no permitido.']);
}
if (!is_array($entrada)) {
    responderCfdiJson(400, ['estatus' => false, 'mensaje' => 'Cuerpo JSON inválido.']);
}

$clientId = trim((string) ($entrada['client_id'] ?? ''));
$issuerId = filter_var($entrada['issuer_id'] ?? null, FILTER_VALIDATE_INT);
$cfdiId = trim((string) ($entrada['id'] ?? ''));
if ($clientId === '' || strlen($clientId) > 100 || !preg_match('/^[A-Za-z0-9._:-]+$/', $clientId)
    || !$issuerId || $issuerId < 1 || $cfdiId === '' || strlen($cfdiId) > 150
    || !preg_match('/^[A-Za-z0-9._:-]+$/', $cfdiId)) {
    responderCfdiJson(422, ['estatus' => false, 'mensaje' => 'Envía client_id, issuer_id e id válidos.']);
}

try {
    $registry = new IssuerRegistry();
    $emisor = $registry->findForClient($sistema, $clientId, $issuerId);
    if (!$emisor) {
        responderCfdiJson(403, ['estatus' => false, 'mensaje' => 'El emisor indicado no está asignado a este cliente.']);
    }

    $facturama = new FacturamaService();
    $detalle = $facturama->obtenerCfdi($cfdiId);
    if (!$detalle['ok']) {
        $status = (int) ($detalle['status'] ?: 502);
        responderCfdiJson($status >= 400 && $status < 600 ? $status : 502, [
            'estatus' => false,
            'mensaje' => $detalle['data']['Message'] ?? 'No se pudo consultar el CFDI.'
        ]);
    }
    $rfcCfdi = obtenerEmisorCfdi((array) $detalle['data']);
    if ($rfcCfdi === '') {
        responderCfdiJson(502, ['estatus' => false, 'mensaje' => 'Facturama no devolvió el RFC emisor para validar el CFDI.']);
    }
    if (strcasecmp($rfcCfdi, (string) $emisor['issuer_rfc']) !== 0) {
        responderCfdiJson(403, ['estatus' => false, 'mensaje' => 'El CFDI no pertenece al emisor asignado a este cliente.']);
    }

    if ($metodo === 'GET') {
        $formato = strtolower(trim((string) ($entrada['formato'] ?? '')));
        if (!in_array($formato, ['pdf', 'xml'], true)) {
            responderCfdiJson(422, ['estatus' => false, 'mensaje' => 'El formato debe ser pdf o xml.']);
        }

        $resultado = $facturama->descargarCfdi($cfdiId, $formato);
        if (!$resultado['ok']) {
            $status = (int) ($resultado['status'] ?: 502);
            responderCfdiJson($status >= 400 && $status < 600 ? $status : 502, [
                'estatus' => false,
                'mensaje' => is_array($resultado['data']) ? ($resultado['data']['Message'] ?? 'No se pudo descargar el archivo.') : 'No se pudo descargar el archivo.'
            ]);
        }

        $respuesta = is_string($resultado['data']) ? json_decode(ltrim($resultado['data'], "\xEF\xBB\xBF"), true) : $resultado['data'];
        $contenido = is_array($respuesta) ? base64_decode((string) ($respuesta['Content'] ?? ''), true) : false;
        if ($contenido === false || $contenido === '') {
            responderCfdiJson(502, ['estatus' => false, 'mensaje' => 'La respuesta de Facturama no contiene un archivo válido.']);
        }
        if (($formato === 'pdf' && strncmp($contenido, '%PDF', 4) !== 0)
            || ($formato === 'xml' && !str_contains($contenido, '<?xml') && !str_contains($contenido, '<cfdi:'))) {
            responderCfdiJson(502, ['estatus' => false, 'mensaje' => 'El archivo recibido no tiene un formato válido.']);
        }

        $nombre = preg_replace('/[^A-Za-z0-9_-]/', '', $cfdiId) ?: 'cfdi';
        header('Content-Type: ' . ($formato === 'pdf' ? 'application/pdf' : 'application/xml; charset=utf-8'));
        header('Content-Disposition: attachment; filename="cfdi-' . $nombre . '.' . $formato . '"');
        header('Content-Length: ' . strlen($contenido));
        header('Cache-Control: no-store');
        echo $contenido;
        exit;
    }

    if (($entrada['action'] ?? '') !== 'email') {
        responderCfdiJson(422, ['estatus' => false, 'mensaje' => 'Para POST, action debe ser email.']);
    }
    $correo = trim((string) ($entrada['email'] ?? ''));
    if (strlen($correo) > 254 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responderCfdiJson(422, ['estatus' => false, 'mensaje' => 'Indica una dirección de correo válida.']);
    }

    $resultado = $facturama->enviarCfdiCorreo($cfdiId, $correo, (string) ($entrada['issuer_email'] ?? ''), (string) $entrada['subject'], (string) ($entrada['comments'] ?? ''));
   
    if (!$resultado['ok']) {
        $status = (int) ($resultado['status'] ?: 502);
        responderCfdiJson($status >= 400 && $status < 600 ? $status : 502, [
            'estatus' => false,
            'mensaje' => is_array($resultado['data']) ? ($resultado['data']['Message'] ?? 'No se pudo enviar el CFDI por correo.') : 'No se pudo enviar el CFDI por correo.'
        ]);
    }
    responderCfdiJson(200, ['estatus' => true, 'mensaje' => 'Solicitud de envío aceptada.', 'data' => $resultado['data']]);
} catch (Throwable $error) {
    error_log('Error al procesar descarga/envío de CFDI: ' . $error->getMessage());
    responderCfdiJson(500, ['estatus' => false, 'mensaje' => 'No fue posible procesar la solicitud.']);
}