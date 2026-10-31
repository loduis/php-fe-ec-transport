<?php

declare(strict_types=1);

namespace FEEC\Tests\Support\Simulator;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Electronic\Simulator\Http\ServerRequest;
use Electronic\Simulator\Http\ServerResponse;
use Electronic\Simulator\Loopback;
use Electronic\Simulator\PayloadStore;
use Electronic\Simulator\StateStore;
use RuntimeException;
use Throwable;

final class Simulator
{
    private const BASE_PATH = '/comprobantes-electronicos-ws/';

    private const RECEPTION = 'RecepcionComprobantesOffline';

    private const AUTHORIZATION = 'AutorizacionComprobantesOffline';

    private StateStore $state;

    private PayloadStore $payloads;

    public function __construct(?StateStore $state = null)
    {
        $this->state = $state ?: new StateStore(
            getenv('FEEC_SIMULATOR_STATE') ?:
                sys_get_temp_dir() . '/feec-simulator.json',
            [self::class, 'defaults']
        );
        $this->payloads = new PayloadStore(
            $this->state->path() . '.payloads'
        );
    }

    public static function defaults(): array
    {
        return [
            'documents' => [],
            'scenario' => ['next_authorization' => 'authorized'],
            'operations' => [
                'reception' => 0,
                'authorization' => 0,
            ],
        ];
    }

    public function run(): void
    {
        try {
            $request = ServerRequest::fromGlobals();
            Loopback::assertClient($request->clientAddress());
            $this->route($request)->emit();
        } catch (Throwable $error) {
            $this->fault($error->getMessage())->emit();
        }
    }

    private function route(ServerRequest $request): ServerResponse
    {
        if ($request->path() === '/__simulator/state') {
            return $request->method() === 'GET'
                ? ServerResponse::json($this->state->read())
                : $this->methodNotAllowed();
        }
        if ($request->path() === '/__simulator/reset') {
            return $request->method() === 'POST'
                ? ServerResponse::json($this->reset())
                : $this->methodNotAllowed();
        }
        if ($request->path() === '/__simulator/scenario') {
            return $request->method() === 'POST'
                ? $this->scenario($request)
                : $this->methodNotAllowed();
        }
        if ($request->path() === '/__simulator/documents') {
            return $request->method() === 'POST'
                ? $this->updateDocument($request)
                : $this->methodNotAllowed();
        }

        $service = $this->service($request->path());
        if ($service === null) {
            return ServerResponse::text('Not Found', 404);
        }
        if ($request->method() === 'GET') {
            if (!array_key_exists('wsdl', $request->query())) {
                return ServerResponse::text('Not Found', 404);
            }
            $location = $this->location($request);
            $wsdl = $service === self::RECEPTION
                ? Wsdl::reception($location)
                : Wsdl::authorization($location);

            return ServerResponse::xml($wsdl);
        }
        if ($request->method() !== 'POST') {
            return $this->methodNotAllowed();
        }
        [$operation, $params] = $this->operation($request->content());

        return match ([$service, $operation]) {
            [self::RECEPTION, 'validarComprobante'] =>
                $this->receive($params),
            [self::AUTHORIZATION, 'autorizacionComprobante'] =>
                $this->authorize($params),
            default => $this->fault('Unsupported simulator operation.'),
        };
    }

    private function scenario(ServerRequest $request): ServerResponse
    {
        $scenario = trim((string) (
            $request->json()['next_authorization'] ?? ''
        ));
        if (!in_array($scenario, [
            'authorized',
            'rejected',
            'pending',
        ], true)) {
            return ServerResponse::json([
                'error' => 'next_authorization is invalid.',
            ], 422);
        }
        $state = $this->state->change(
            function (array &$state) use ($scenario): array {
                $state['scenario']['next_authorization'] = $scenario;

                return $state;
            }
        );

        return ServerResponse::json($state['scenario']);
    }

    private function receive(array $params): ServerResponse
    {
        $xml = $this->documentXml((string) ($params['xml'] ?? ''));
        $key = $this->accessKey($xml);
        if ($key === null) {
            return $this->receptionResponse(
                'DEVUELTA',
                'N/A',
                35,
                'ARCHIVO NO CUMPLE ESTRUCTURA XML'
            );
        }
        $result = $this->state->change(
            function (array &$state) use ($key, $xml): array {
                $state['operations']['reception']++;
                if (isset($state['documents'][$key])) {
                    return ['duplicate' => true];
                }
                $status = $state['scenario']['next_authorization'] ??
                    'authorized';
                $state['scenario']['next_authorization'] = 'authorized';
                $state['documents'][$key] = [
                    'key' => $key,
                    'status' => $status,
                    'type' => $this->documentType($xml),
                    'content_hash' => hash('sha256', $xml),
                ];

                return ['duplicate' => false];
            }
        );
        if ($result['duplicate']) {
            return $this->receptionResponse(
                'DEVUELTA',
                $key,
                43,
                'CLAVE ACCESO REGISTRADA'
            );
        }
        $this->payloads->write($key, $xml);

        return $this->receptionResponse('RECIBIDA');
    }

    private function authorize(array $params): ServerResponse
    {
        $key = trim((string) (
            $params['claveAccesoComprobante'] ?? ''
        ));
        $document = $this->state->change(
            function (array &$state) use ($key) {
                $state['operations']['authorization']++;

                return $state['documents'][$key] ?? null;
            }
        );
        if (!is_array($document) || $document['status'] === 'pending') {
            return $this->authorizationNotFound($key);
        }
        $authorized = $document['status'] === 'authorized';
        $status = $authorized ? 'AUTORIZADO' : 'NO AUTORIZADO';
        $message = $authorized
            ? ''
            : '<mensajes><mensaje><identificador>70</identificador>' .
                '<mensaje>RECHAZO SOLICITADO POR EL SIMULADOR</mensaje>' .
                '<tipo>ERROR</tipo></mensaje></mensajes>';
        $xml = $this->payloads->read($key);
        if ($xml === null) {
            throw new RuntimeException(
                'The simulator document payload is missing.'
            );
        }
        $xml = $this->escape($xml);
        $body = '<claveAccesoConsultada>' . $this->escape($key) .
            '</claveAccesoConsultada><numeroComprobantes>1</numeroComprobantes>' .
            '<autorizaciones><autorizacion><estado>' . $status . '</estado>' .
            ($authorized
                ? '<numeroAutorizacion>' . $this->escape($key) .
                    '</numeroAutorizacion>'
                : '') .
            '<fechaAutorizacion>2026-01-01T00:00:00-05:00</fechaAutorizacion>' .
            '<ambiente>PRUEBAS</ambiente><comprobante>' . $xml .
            '</comprobante>' . $message . '</autorizacion></autorizaciones>';

        return $this->soap(
            'autorizacionComprobanteResponse',
            'http://ec.gob.sri.ws.autorizacion',
            '<RespuestaAutorizacionComprobante>' . $body .
                '</RespuestaAutorizacionComprobante>'
        );
    }

    private function updateDocument(ServerRequest $request): ServerResponse
    {
        $input = $request->json();
        $key = trim((string) ($input['key'] ?? ''));
        $status = trim((string) ($input['status'] ?? ''));
        if (!preg_match('/^[0-9]{49}$/', $key) || !in_array(
            $status,
            ['authorized', 'rejected', 'pending'],
            true
        )) {
            return ServerResponse::json([
                'error' => 'The simulator document update is invalid.',
            ], 422);
        }
        $document = $this->state->change(
            function (array &$state) use ($key, $status): ?array {
                if (!isset($state['documents'][$key])) {
                    return null;
                }
                $state['documents'][$key]['status'] = $status;

                return $state['documents'][$key];
            }
        );
        if ($document === null) {
            return ServerResponse::json([
                'error' => 'The simulator document was not found.',
            ], 404);
        }

        return ServerResponse::json($document);
    }

    private function reset(): array
    {
        $this->payloads->reset();

        return $this->state->reset();
    }

    private function receptionResponse(
        string $status,
        ?string $key = null,
        ?int $code = null,
        ?string $message = null
    ): ServerResponse {
        $details = '';
        if ($key !== null && $code !== null && $message !== null) {
            $details = '<comprobantes><comprobante><claveAcceso>' .
                $this->escape($key) . '</claveAcceso><mensajes><mensaje>' .
                '<identificador>' . $code . '</identificador><mensaje>' .
                $this->escape($message) . '</mensaje><tipo>ERROR</tipo>' .
                '</mensaje></mensajes></comprobante></comprobantes>';
        } else {
            $details = '<comprobantes/>';
        }

        return $this->soap(
            'validarComprobanteResponse',
            'http://ec.gob.sri.ws.recepcion',
            '<RespuestaRecepcionComprobante><estado>' . $status . '</estado>' .
                $details . '</RespuestaRecepcionComprobante>'
        );
    }

    private function authorizationNotFound(string $key): ServerResponse
    {
        return $this->soap(
            'autorizacionComprobanteResponse',
            'http://ec.gob.sri.ws.autorizacion',
            '<RespuestaAutorizacionComprobante><claveAccesoConsultada>' .
                $this->escape($key) . '</claveAccesoConsultada>' .
                '<numeroComprobantes>0</numeroComprobantes>' .
                '<autorizaciones/></RespuestaAutorizacionComprobante>'
        );
    }

    private function operation(string $content): array
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($content, LIBXML_NONET)) {
            throw new RuntimeException('Invalid SOAP request.');
        }
        $xpath = new DOMXPath($dom);
        $operation = $xpath->query(
            '/*[local-name()="Envelope"]/*[local-name()="Body"]/*[1]'
        )?->item(0);
        if (!$operation instanceof DOMElement) {
            throw new RuntimeException('SOAP operation is required.');
        }
        $params = [];
        foreach ($operation->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $params[$node->localName] = trim($node->textContent);
            }
        }

        return [$operation->localName, $params];
    }

    private function documentXml(string $content): string
    {
        $decoded = base64_decode(trim($content), true);
        if (is_string($decoded) && str_starts_with(ltrim($decoded), '<')) {
            return $decoded;
        }

        return $content;
    }

    private function accessKey(string $xml): ?string
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET)) {
            return null;
        }
        $xpath = new DOMXPath($dom);
        $node = $xpath->query('//*[local-name()="claveAcceso"]')?->item(0);
        $key = $node ? trim($node->textContent) : '';

        return preg_match('/^[0-9]{49}$/', $key) ? $key : null;
    }

    private function documentType(string $xml): string
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET) || !$dom->documentElement) {
            return 'unknown';
        }

        return $dom->documentElement->localName;
    }

    private function service(string $path): ?string
    {
        return match ($path) {
            self::BASE_PATH . self::RECEPTION => self::RECEPTION,
            self::BASE_PATH . self::AUTHORIZATION => self::AUTHORIZATION,
            default => null,
        };
    }

    private function location(ServerRequest $request): string
    {
        $host = trim((string) $request->header('Host'));
        if (!preg_match(
            '/^(?:127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?$/',
            $host
        )) {
            throw new RuntimeException('Invalid local simulator host.');
        }

        return 'http://' . $host . $request->path();
    }

    private function soap(
        string $operation,
        string $namespace,
        string $body
    ): ServerResponse {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/' .
            'envelope/"><soap:Body><ns2:' . $operation . ' xmlns:ns2="' .
            $namespace . '">' . $body . '</ns2:' . $operation . '>' .
            '</soap:Body></soap:Envelope>';

        return ServerResponse::xml($xml, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }

    private function fault(string $message): ServerResponse
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/' .
            'envelope/"><soap:Body><soap:Fault><faultcode>soap:Server' .
            '</faultcode><faultstring>' . $this->escape($message) .
            '</faultstring></soap:Fault></soap:Body></soap:Envelope>';

        return ServerResponse::xml($xml, 500, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }

    private function methodNotAllowed(): ServerResponse
    {
        return ServerResponse::json(['error' => 'Method Not Allowed'], 405);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
