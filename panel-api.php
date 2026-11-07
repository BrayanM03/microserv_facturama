<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Strict'
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
require_once __DIR__ . '/FacturamaService.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/IssuerRegistry.php';
cargarEnv();

function responderJson($status, $contenido) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
    exit;
}

function verificarCsrf() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        responderJson(403, ['error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.']);
    }
}

function sanitizarCsd($csd) {
    if (!is_array($csd)) {
        return $csd;
    }
    unset($csd['Certificate'], $csd['PrivateKey'], $csd['PrivateKeyPassword']);
    return $csd;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$entrada = json_decode(file_get_contents('php://input'), true);
$entrada = is_array($entrada) ? $entrada : [];
$accion = $entrada['action'] ?? $_POST['action'] ?? '';

if ($metodo !== 'POST') {
    responderJson(405, ['error' => 'Método no permitido.']);
}

verificarCsrf();

if ($accion === 'login') {
    $contrasena = getenv('FACTURAMA_UI_PASSWORD') ?: '';
    if ($contrasena === '') {
        responderJson(503, ['error' => 'Configura FACTURAMA_UI_PASSWORD en el servidor antes de iniciar sesión.']);
    }
    if (!hash_equals($contrasena, (string) ($entrada['password'] ?? ''))) {
        responderJson(401, ['error' => 'Contraseña incorrecta.']);
    }
    session_regenerate_id(true);
    $_SESSION['autenticado'] = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    responderJson(200, ['ok' => true, 'csrf' => $_SESSION['csrf']]);
}

if (empty($_SESSION['autenticado'])) {
    responderJson(401, ['error' => 'Inicia sesión para continuar.']);
}

if ($accion === 'logout') {
    $_SESSION = [];
    session_destroy();
    responderJson(200, ['ok' => true]);
}

$facturama = new FacturamaService();

if ($accion === 'invoices') {
    $filtros = [];
    foreach (['status', 'folio', 'rfcIssuer', 'rfc', 'taxEntityName', 'dateStart', 'dateEnd'] as $campo) {
        $valor = trim((string) ($entrada[$campo] ?? ''));
        if ($valor !== '') {
            $filtros[$campo] = $valor;
        }
    }
    $filtros['page'] = max(0, (int) ($entrada['page'] ?? 0));
    $resultado = $facturama->buscarCfdis($filtros);
    if (!$resultado['ok']) {
        responderJson($resultado['status'] ?: 502, ['error' => $resultado['data']['Message'] ?? 'No se pudieron consultar las facturas.']);
    }
    responderJson(200, ['data' => $resultado['data'], 'page' => $filtros['page']]);
}

if ($accion === 'issuers') {
    $resultado = $facturama->listarCsds();
    if (!$resultado['ok']) {
        responderJson($resultado['status'] ?: 502, ['error' => $resultado['data']['Message'] ?? 'No se pudieron consultar los emisores.']);
    }
    $emisores = is_array($resultado['data']) ? array_map('sanitizarCsd', $resultado['data']) : [];
    responderJson(200, ['data' => $emisores]);
}

if ($accion === 'assignments') {
    try {
        $registry = new IssuerRegistry();
        responderJson(200, [
            'data' => $registry->listAll(),
            'systems' => array_values(obtenerSistemasPermitidos())
        ]);
    } catch (Throwable $error) {
        error_log('Error al listar asignaciones: ' . $error->getMessage());
        responderJson(500, ['error' => 'No se pudieron consultar las asignaciones.']);
    }
}

if ($accion === 'saveAssignment') {
    $sistema = trim((string) ($entrada['system'] ?? ''));
    $clientId = trim((string) ($entrada['clientId'] ?? ''));
    $clientName = trim((string) ($entrada['clientName'] ?? ''));
    $rfc = strtoupper(trim((string) ($entrada['issuerRfc'] ?? '')));
    $issuerName = trim((string) ($entrada['issuerName'] ?? ''));
    $fiscalRegime = trim((string) ($entrada['fiscalRegime'] ?? ''));

    if (!in_array($sistema, array_values(obtenerSistemasPermitidos()), true)) {
        responderJson(422, ['error' => 'Selecciona un sistema registrado.']);
    }
    if ($clientId === '' || strlen($clientId) > 100 || !preg_match('/^[A-Za-z0-9._:-]+$/', $clientId)) {
        responderJson(422, ['error' => 'El client_id debe tener hasta 100 caracteres alfanuméricos o . _ : -.']);
    }
    if ($clientName === '' || strlen($clientName) > 160 || $issuerName === '' || strlen($issuerName) > 160) {
        responderJson(422, ['error' => 'Indica nombres de cliente y emisor de hasta 160 caracteres.']);
    }
    if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc) || !preg_match('/^[0-9]{3}$/', $fiscalRegime)) {
        responderJson(422, ['error' => 'El RFC o régimen fiscal no tiene un formato válido.']);
    }

    $csds = $facturama->listarCsds();
    if (!$csds['ok']) {
        responderJson($csds['status'] ?: 502, ['error' => $csds['data']['Message'] ?? 'No se pudo verificar el CSD en Facturama.']);
    }
    $existeCsd = false;
    foreach ((array) $csds['data'] as $csd) {
        if (strtoupper((string) ($csd['Rfc'] ?? $csd['RFC'] ?? '')) === $rfc) {
            $existeCsd = true;
            break;
        }
    }
    if (!$existeCsd) {
        responderJson(422, ['error' => 'Ese RFC no tiene un CSD registrado en Facturama.']);
    }

    try {
        $registry = new IssuerRegistry();
        $registry->save($sistema, $clientId, $clientName, $rfc, $issuerName, $fiscalRegime);
        responderJson(200, ['ok' => true, 'message' => 'Emisor asignado al cliente.']);
    } catch (Throwable $error) {
        error_log('Error al guardar asignación: ' . $error->getMessage());
        responderJson(500, ['error' => 'No se pudo guardar la asignación.']);
    }
}

if ($accion === 'deleteAssignment') {
    $assignmentId = filter_var($entrada['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$assignmentId || $assignmentId < 1) {
        responderJson(422, ['error' => 'Identificador de asignación inválido.']);
    }
    try {
        $registry = new IssuerRegistry();
        if (!$registry->delete($assignmentId)) {
            responderJson(404, ['error' => 'La asignación ya no existe.']);
        }
        responderJson(200, ['ok' => true]);
    } catch (Throwable $error) {
        error_log('Error al eliminar asignación: ' . $error->getMessage());
        responderJson(500, ['error' => 'No se pudo eliminar la asignación.']);
    }
}

if ($accion === 'saveIssuer') {
    $rfc = strtoupper(trim((string) ($_POST['rfc'] ?? '')));
    $actualizar = ($_POST['update'] ?? '') === '1';
    $contrasena = (string) ($_POST['privateKeyPassword'] ?? '');
    if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc)) {
        responderJson(422, ['error' => 'El RFC no tiene un formato válido.']);
    }
    if ($contrasena === '' || empty($_FILES['certificate']['tmp_name']) || empty($_FILES['privateKey']['tmp_name'])) {
        responderJson(422, ['error' => 'Adjunta los archivos .cer y .key e indica la contraseña del CSD.']);
    }
    foreach (['certificate' => 'cer', 'privateKey' => 'key'] as $archivo => $extension) {
        $nombre = strtolower((string) ($_FILES[$archivo]['name'] ?? ''));
        if (pathinfo($nombre, PATHINFO_EXTENSION) !== $extension || (int) ($_FILES[$archivo]['size'] ?? 0) > 2 * 1024 * 1024) {
            responderJson(422, ['error' => 'El archivo ' . $archivo . ' debe ser .' . $extension . ' y pesar hasta 2 MB.']);
        }
    }

    $certificado = file_get_contents($_FILES['certificate']['tmp_name']);
    $llave = file_get_contents($_FILES['privateKey']['tmp_name']);
    if ($certificado === false || $llave === false) {
        responderJson(422, ['error' => 'No se pudieron leer los archivos seleccionados.']);
    }
    $datos = [
        'Rfc' => $rfc,
        'Certificate' => base64_encode($certificado),
        'PrivateKey' => base64_encode($llave),
        'PrivateKeyPassword' => $contrasena
    ];

    $resultado = $facturama->guardarCsd($rfc, $datos, $actualizar);
    if (!$resultado['ok']) {
        responderJson($resultado['status'] ?: 502, ['error' => $resultado['data']['Message'] ?? 'Facturama no pudo guardar el CSD.', 'resultado' => $resultado, 'datos'=> $datos]);
    }
    responderJson(200, ['ok' => true, 'message' => $actualizar ? 'CSD actualizado.' : 'CSD registrado.']);
}

if ($accion === 'deleteIssuer') {
    $rfc = strtoupper(trim((string) ($entrada['rfc'] ?? '')));
    try {
        $registry = new IssuerRegistry();
        foreach ($registry->listAll() as $asignacion) {
            if (strtoupper($asignacion['issuer_rfc']) === $rfc) {
                responderJson(409, ['error' => 'Quita primero las asignaciones de clientes que usan este emisor.']);
            }
        }
    } catch (Throwable $error) {
        error_log('Error al verificar asignaciones del CSD: ' . $error->getMessage());
        responderJson(500, ['error' => 'No se pudo verificar si el emisor está asignado.']);
    }
    $resultado = $facturama->eliminarCsd($rfc);
    if (!$resultado['ok']) {
        responderJson($resultado['status'] ?: 502, ['error' => $resultado['data']['Message'] ?? 'No se pudo eliminar el CSD.']);
    }
    responderJson(200, ['ok' => true]);
}

if ($accion === 'detail') {
    $id = trim((string) ($entrada['id'] ?? ''));
    if ($id === '') {
        responderJson(422, ['error' => 'Falta el identificador del CFDI.']);
    }
    $resultado = $facturama->obtenerCfdi($id);
    if (!$resultado['ok']) {
        responderJson($resultado['status'] ?: 502, ['error' => $resultado['data']['Message'] ?? 'No se pudo consultar el CFDI.']);
    }
    responderJson(200, ['data' => $resultado['data']]);
}

if ($accion === 'download') {
    $id = trim((string) ($entrada['id'] ?? ''));
    $formato = strtolower((string) ($entrada['format'] ?? ''));
    if ($id === '' || !in_array($formato, ['xml', 'pdf', 'html'], true)) {
        responderJson(422, ['error' => 'Formato o identificador inválido.']);
    }
    $resultado = $facturama->descargarCfdi($id, $formato);
    if (!$resultado['ok']) {
        responderJson($resultado['status'] ?: 502, ['error' => is_array($resultado['data']) ? ($resultado['data']['Message'] ?? 'No se pudo descargar el archivo.') : 'No se pudo descargar el archivo.']);
    }
   $crudo = $resultado['data'];

// 1. La respuesta llega como string JSON: extraer el campo Content
$json = is_string($crudo) ? json_decode(ltrim($crudo, "\xEF\xBB\xBF"), true) : $crudo;
$base64 = is_array($json) ? (string) ($json['Content'] ?? '') : '';

// 2. Decodificar Base64 (aplica a xml, pdf y html)
$contenido = $base64 !== '' ? base64_decode($base64, true) : false;

if ($contenido === false || $contenido === '') {
    responderJson(502, ['error' => 'La respuesta de Facturama no contiene un archivo válido.']);
}

// 3. Validación rápida por formato
if ($formato === 'pdf' && strncmp($contenido, '%PDF', 4) !== 0) {
    responderJson(502, ['error' => 'El archivo recibido no es un PDF válido.']);
}
if ($formato === 'xml' && !str_contains($contenido, '<?xml') && !str_contains($contenido, '<cfdi:')) {
    responderJson(502, ['error' => 'El archivo recibido no es un XML válido.']);
}

$tipos = [
    'xml'  => 'application/xml; charset=utf-8',
    'pdf'  => 'application/pdf',
    'html' => 'text/html; charset=utf-8',
];
header('Content-Type: ' . $tipos[$formato]);
header('Content-Disposition: attachment; filename="cfdi-' . preg_replace('/[^A-Za-z0-9_-]/', '', $id) . '.' . $formato . '"');
header('Content-Length: ' . strlen($contenido));
header('Cache-Control: no-store');
echo $contenido;
exit;
} 

if ($accion === 'cancel') {
    $id = trim((string) ($entrada['id'] ?? ''));
    $motivo = (string) ($entrada['motive'] ?? '');
    $uuid = strtoupper(trim((string) ($entrada['replacementUuid'] ?? '')));
    if ($id === '' || !in_array($motivo, ['01', '02', '03', '04'], true) || ($motivo === '01' && $uuid === '')) {
        responderJson(422, ['error' => 'Indica el CFDI, un motivo válido y UUID sustituto para el motivo 01.']);
    }
    $resultado = $facturama->cancelarCfdi($id, $motivo, $uuid);
    if (!$resultado['ok']) {
        responderJson($resultado['status'] ?: 502, ['error' => $resultado['data']['Message'] ?? 'No se pudo solicitar la cancelación.']);
    }
    responderJson(200, ['data' => $resultado['data']]);
}

responderJson(400, ['error' => 'Acción no reconocida.']);
