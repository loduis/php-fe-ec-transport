<?php

namespace FEEC\Request;

use Electronic\Simulator\Loopback;
use RuntimeException;
use SoapClient;
use FEEC\Response;

use const FEEC\ENV_PROD;
use const FEEC\ENV_TEST;

const HOST_BASE = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/';

class Client
{
    protected const SERVICES = [
        'validarComprobante' => 'Recepcion',
        'autorizacionComprobante' => 'Autorizacion',
    ];

    public int $environment;

    private ?string $simulatorOrigin;

    public function __construct(
        int $environment,
        ?string $simulatorOrigin = null
    )
    {
        if ($simulatorOrigin !== null && $environment !== ENV_TEST) {
            throw new \UnexpectedValueException(
                'The simulator is only available in the test environment.'
            );
        }
        $this->environment = $environment;
        $this->simulatorOrigin = $simulatorOrigin;
    }

    public function send(string $method, array $params)
    {
        $url = $this->urlFromMethod($method);
        if ($this->simulatorOrigin !== null) {
            [$res, $request, $response] = (new SimulatorSoapClient(
                substr($url, 0, -strlen('?wsdl'))
            ))->send($method, $params);

            return Response::from($res, [
                'action' => $method,
                'request' => $request,
                'response' => $response,
                'environment' => $this->environment,
            ]);
        }
        if (!class_exists(SoapClient::class)) {
            throw new RuntimeException(
                'The SOAP extension is required for the SRI transport.'
            );
        }
        $soap = new SoapClient($url, [
            'trace' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                ]
            ])
        ]);

        $res = $soap->$method($params);
        $request = $soap->__getLastRequest();
        $response = $soap->__getLastResponse();

        return Response::from($res, [
            'action' => $method,
            'request' => $request,
            'response' => $response,
            'environment' => $this->environment
        ]);
    }

    private function urlFromMethod(string $name): string
    {
        $url = HOST_BASE;
        if ($this->environment === ENV_PROD) {
            $url = str_replace('/celcer.', '/cel.', $url);
        }

        $url .= static::SERVICES[$name] . 'ComprobantesOffline?wsdl';

        return $this->simulatorOrigin === null
            ? $url
            : Loopback::localize($url, $this->simulatorOrigin);
    }
}
