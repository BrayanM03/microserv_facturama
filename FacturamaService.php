<?php
// FacturamaService.php
class FacturamaService {
    private $baseUrl;
    private $usuario;
    private $password;

  public function __construct() {
          require_once __DIR__ . '/env.php';
    cargarEnv();

        $this->baseUrl = rtrim(getenv('FACTURAMA_API_URL') ?: 'https://www.api.facturama.com.mx', '/');
        $this->usuario = getenv('FACTURAMA_USER') ?: '';
        $this->password = getenv('FACTURAMA_PASSWORD') ?: '';
    }

    public function crearCfdi($payload) {
        $resultado = $this->request('POST', '/api-lite/4/cfdis', $payload);
        if ($resultado['ok']) {
            return ['estatus' => true, 'data' => $resultado['data']];
        }

        $data = $resultado['data'];
        return [
            'estatus' => false,
            'status' => $resultado['status'],
            'mensaje' => $data['Message'] ?? $data['message'] ?? 'Error al timbrar',
            'detalles' => $data,
            'payload' => $payload
        ];
    }

    public function buscarCfdis($filtros) {
        $filtros['type'] = 'issuedLite';
        $filtros['page'] = max(0, (int) ($filtros['page'] ?? 0));
        return $this->request('GET', '/cfdi?' . http_build_query($filtros));
    }

    public function listarCsds() {
        return $this->request('GET', '/api-lite/csds');
    }

    public function consultarCatalogo($catalogo, $keyword = '') {
        $parametros = [];
        if (trim($keyword) !== '') {
            $parametros['keyword'] = trim($keyword);
        }
        $query = $parametros ? '?' . http_build_query($parametros) : '';
        return $this->request('GET', '/api/catalogs/' . rawurlencode($catalogo) . $query);
    }

    public function guardarCsd($rfc, $datos, $actualizar = false) {
        $ruta = '/api-lite/csds' . ($actualizar ? '/' . rawurlencode($rfc) : '');
        return $this->request($actualizar ? 'PUT' : 'POST', $ruta, $datos);
    }

    public function eliminarCsd($rfc) {
        return $this->request('DELETE', '/api-lite/csds/' . rawurlencode($rfc));
    }

    public function obtenerCfdi($id) {
        return $this->request('GET', '/api-lite/cfdis/' . rawurlencode($id));
    }

    public function cancelarCfdi($id, $motivo, $uuidReemplazo = '') {
        $parametros = ['motive' => $motivo];
        if ($motivo === '01' && $uuidReemplazo !== '') {
            $parametros['uuidReplacement'] = $uuidReemplazo;
        }
        $ruta = '/api-lite/cfdis/' . rawurlencode($id) . '?' . http_build_query($parametros);
        return $this->request('DELETE', $ruta);
    }

    public function descargarCfdi($id, $formato) {
        return $this->request('GET', '/cfdi/' . rawurlencode($formato) . '/issuedLite/' . rawurlencode($id), null, true);
    }

    

public function enviarCfdiCorreo($id, $correo, $correo_emisor = '', $subject ='', $comentarios = '')
{
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return [
            'ok' => false,
            'status' => 400,
            'data' => ['Message' => 'El correo electrónico no es válido.']
        ];
    }

    $ruta = '/Cfdi?' . http_build_query([
        'CfdiType' => 'issuedLite',
        'CfdiId'   => $id,
        'Email'    => $correo,
        'Subject' => $subject ?? '',
        'Comments' => $comentarios ?? '',
        'issuerEmail' => $correo_emisor
    ]);

    return $this->request('POST', $ruta, '');
}



    private function request($metodo, $ruta, $payload = null, $respuestaBinaria = false) {
        if ($this->usuario === '' || $this->password === '') {
            return ['ok' => false, 'status' => 500, 'data' => ['Message' => 'Configura FACTURAMA_USER y FACTURAMA_PASSWORD en el entorno del servidor.']];
        }

        $ch = curl_init($this->baseUrl . $ruta);
        $cabeceras = [
            'Authorization: Basic ' . base64_encode($this->usuario . ':' . $this->password),
            'Accept: ' . ($respuestaBinaria ? '*/*' : 'application/json')
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        if ($payload !== null) {
            $cabeceras[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $cabeceras);

        $respuesta = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
       if ($httpCode === 403) {
    error_log(
        'Facturama 403: ' . substr((string) $respuesta, 0, 1000) . PHP_EOL,
        3,
        '/var/log/facturama-debug.log'
    );
}
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
        $error = curl_error($ch);

        if ($respuesta === false) {
            return ['ok' => false, 'status' => 502, 'data' => ['Message' => $error ?: 'No fue posible conectar con Facturama.']];
        }

        $data = $respuestaBinaria ? $respuesta : json_decode($respuesta, true);
        if (!$respuestaBinaria && $data === null && $respuesta !== '') {
            $data = ['Message' => $respuesta];
        }

        return [
            'ok' => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'data' => $data,
            'contentType' => $contentType
        ];
    }
}