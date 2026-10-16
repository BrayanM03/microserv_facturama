# Consola Facturama Multiemisor

Panel PHP para consultar y administrar los CFDI emitidos con Facturama API Lite Multiemisor. Incluye filtros y paginación, detalle de comprobantes, descarga XML/PDF, solicitudes de cancelación y administración de CSD por RFC.

## Requisitos

- PHP 8 o posterior con las extensiones `curl` y `json`.
- PDO con el controlador `pdo_sqlite`.
- Credenciales de API Facturama.
- Una contraseña independiente para acceder al panel.
- API keys distintas para Pikero y Colefy.

Las API keys que antes estaban de ejemplo dentro de `auth.php` ahora se leen de `PIKERO_API_KEY` y `COLEFY_API_KEY`. Configura esos valores antes de consumir `facturar.php` o `emisores.php`.

## Inicio local

Configura las variables indicadas en `.env.example` en un `.env` local o como variables del servidor. Pikero y Colefy deben tener API keys largas, únicas y privadas. La base SQLite se crea por defecto en `../facturama-private/issuers.sqlite`, fuera de esta carpeta; puedes definir otra ruta absoluta con `FACTURAMA_DB_PATH`.

Desde esta carpeta, inicia el servidor integrado de PHP:

```sh
php -S 127.0.0.1:8000
```

Abre `http://127.0.0.1:8000`. `FACTURAMA_API_URL` es opcional; por defecto apunta a producción (`https://www.api.facturama.com.mx`). Para Sandbox, configura `FACTURAMA_API_URL='https://apisandbox.facturama.com.mx'`.

No publiques el servidor integrado de PHP en Internet. En producción coloca `.env` y la base de datos fuera del document root, configura variables de entorno con el gestor de secretos del hosting, usa HTTPS y protege el panel con controles de acceso del servidor. `.gitignore` excluye `.env` y bases locales; `.htaccess` bloquea su descarga en Apache, pero no sustituye ubicarlos fuera del document root.

## Funciones disponibles

- Facturas: consulta Multiemisor `issuedLite`, páginas de 10 registros, filtros por emisor, receptor, razón social, folio, estado y fecha.
- Archivos: detalle de CFDI y descarga de XML/PDF.
- Cancelación: motivos 01 a 04; exige UUID sustituto para motivo 01 y solicita confirmación antes de enviar.
- Emisores: lista RFC y vigencia sin exponer certificados ni llaves privadas; permite registrar, actualizar y eliminar CSD.
- Asignaciones: vincula `client_id` estable de Pikero/Colefy con uno o más emisores CSD y sus datos fiscales. Solo permite asignar RFC que Facturama ya reporta con CSD.
- Catálogos SAT: expone ProductsOrServices, Units, PostalCodes, FiscalRegimens, CfdiUses, PaymentForms y PaymentMethods con búsqueda opcional.
- Emisión existente: `facturar.php` conserva el contrato de respuesta y ahora envía a API Lite v4 (`/api-lite/4/cfdis`).

Las llamadas se hacen desde PHP con autenticación Basic; el navegador nunca recibe las credenciales de Facturama. Los archivos CSD se procesan en memoria durante la solicitud y no se guardan en el proyecto.

## Contrato para Pikero y Colefy

Las dos rutas son server-to-server: la API key debe guardarse en el backend de Pikero/Colefy, nunca en el navegador. `client_id` debe ser el identificador estable de la empresa/tenant autenticada dentro de Pikero, no un valor arbitrario enviado por el usuario.

### Consultar emisores autorizados

```http
GET /emisores.php?client_id=empresa_123
Authorization: Bearer <PIKERO_API_KEY>
```

La respuesta devuelve `issuer_id`, `rfc`, `name` y `fiscal_regime` solo para las asignaciones de ese sistema y cliente. Pikero guarda el `issuer_id` elegido junto con la operación.

### Emitir CFDI

```http
POST /facturar.php
Authorization: Bearer <PIKERO_API_KEY>
Content-Type: application/json
```

```json
{
	"client_id": "empresa_123",
	"issuer_id": 14,
	"cfdi_payload": {
		"CfdiType": "I",
		"Receiver": {},
		"Items": []
	}
}
```

El microservicio valida que `issuer_id` pertenezca a `client_id` y al sistema autenticado, y completa el nodo `Issuer` con los datos guardados en la asignación. No es necesario ni recomendable que Pikero envíe o pueda cambiar el RFC emisor dentro del payload.

Las llamadas antiguas que solo enviaban `cfdi_payload` deben actualizarse: ahora se requieren `client_id` e `issuer_id` para impedir emisiones con RFC no asignados.

### Descargar CFDI o enviarlo por correo

Después de emitir o buscar una factura, Pikero/Colefy conserva el `Id` que devuelve Facturama. Las siguientes llamadas deben hacerse desde el backend del sistema; el endpoint valida que `issuer_id` siga asignado a `client_id` y que el RFC del CFDI corresponda a ese emisor.

Descargar el PDF (usa `formato=xml` para obtener el XML):

```http
GET /cfdi.php?client_id=empresa_123&issuer_id=14&id=<CFDI_ID>&formato=pdf
Authorization: Bearer <PIKERO_API_KEY>
```

La respuesta exitosa es el archivo binario con `Content-Disposition: attachment`. Guarda el cuerpo de la respuesta en un archivo; no intentes decodificarlo como JSON. Por ejemplo, el cliente HTTP de PHP debe escribir la respuesta a un archivo temporal o transmitirla al usuario descargándola desde el backend.

Solicitar que Facturama envíe el CFDI por correo:

```http
POST /cfdi.php
Authorization: Bearer <PIKERO_API_KEY>
Content-Type: application/json
```

```json
{
	"action": "email",
	"client_id": "empresa_123",
	"issuer_id": 14,
	"id": "<CFDI_ID>",
	"email": "facturacion@empresa.com"
}
```

La respuesta exitosa es JSON con `estatus: true`. La ruta de descarga admite `pdf` y `xml`; la operación de correo usa el envío de CFDI de Facturama. En ambos casos un `401` o `403` indica credenciales o asignación inválida, y un `422` indica parámetros incorrectos.

### Consultar catálogo SAT

```http
GET /catalogos.php?catalogo=ProductsOrServices&keyword=software
Authorization: Bearer <PIKERO_API_KEY>
```

El parámetro `catalogo` admite `ProductsOrServices`, `Units`, `PostalCodes`, `FiscalRegimens`, `CfdiUses`, `PaymentForms` o `PaymentMethods`; `keyword` es opcional y admite hasta 100 caracteres. La respuesta exitosa es `{ "estatus": true, "data": [...] }`. Pikero puede encapsular la llamada con el método `catalogoSAT()` proporcionado, usando su cliente HTTP backend existente.

### Preparar una asignación

En la consola abre **Emisores y CSD**, registra el CSD si aún no existe y luego abre **Asignaciones**. Registra el nombre exacto del sistema (`Pikero Ferreterías` o `Colefy Colegios`), el ID estable del cliente que usa esa app, su nombre visible, RFC emisor, razón social fiscal y régimen. El `issuer_id` de la API será asignado por este microservicio.

Antes de eliminar un CSD de Facturama, quita todas sus asignaciones desde la consola; así evitas dejar clientes habilitados para un emisor sin certificado.
