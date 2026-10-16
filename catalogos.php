<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/FacturamaService.php';

function responderCatalogo(int $status, array $contenido): void {
    http_response_code($status);
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    responderCatalogo(405, ['estatus' => false, 'mensaje' => 'Método no permitido.']);
}

validarApiKey();

$catalogosPermitidos = [
    'ProductsOrServices',
    'Units',
    'PostalCodes',
    'FiscalRegimens',
    'CfdiUses',
    'PaymentForms',
    'PaymentMethods'
];
$catalogo = trim((string) ($_GET['catalogo'] ?? ''));
$keyword = trim((string) ($_GET['keyword'] ?? ''));

if (!in_array($catalogo, $catalogosPermitidos, true)) {
    responderCatalogo(400, ['estatus' => false, 'mensaje' => 'El catálogo SAT solicitado no es válido.']);
}
if (strlen($keyword) > 100) {
    responderCatalogo(400, ['estatus' => false, 'mensaje' => 'El filtro keyword no puede superar 100 caracteres.']);
}

try {
    $facturama = new FacturamaService();
    $resultado = $facturama->consultarCatalogo($catalogo, $keyword);
    if (!$resultado['ok']) {
        error_log('Facturama rechazó la consulta de catálogo ' . $catalogo . ' con HTTP ' . $resultado['status']);
        responderCatalogo(502, ['estatus' => false, 'mensaje' => 'No se pudo consultar el catálogo SAT. Inténtalo de nuevo.', 'resultado'=>$resultado]);
    }

    $datos = $resultado['data'];
    if (is_array($datos) && array_key_exists('estatus', $datos) && $datos['estatus'] === false) {
        responderCatalogo(502, ['estatus' => false, 'mensaje' => 'No se pudo consultar el catálogo SAT. Inténtalo de nuevo.', 'resultado'=>$resultado]);
    }
    if (is_array($datos) && isset($datos['data']) && is_array($datos['data'])) {
        $datos = $datos['data'];
    }
    if (!is_array($datos)) {
        responderCatalogo(502, ['estatus' => false, 'mensaje' => 'La respuesta del catálogo SAT no es válida.']);
    }

    responderCatalogo(200, ['estatus' => true, 'data' => array_values($datos)]);
} catch (Throwable $error) {
    error_log('Error al consultar catálogo SAT: ' . $error->getMessage());
    responderCatalogo(502, ['estatus' => false, 'mensaje' => 'No se pudo consultar el catálogo SAT. Inténtalo de nuevo.']);
}