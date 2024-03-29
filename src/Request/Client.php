<?php

namespace FEEC\Request;

use SoapClient;
use FEEC\Response;

use const FEEC\ENV_PROD;

const HOST_BASE = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/';

class Client
{
    protected const SERVICES = [
        'validarComprobante' => 'Recepcion',
        'autorizacionComprobante' => 'Autorizacion',
    ];

    public int $environment;

    public function __construct(int $environment)
    {
        $this->environment = $environment;
    }

    public function send(string $method, array $params)
    {
        $url = $this->urlFromMethod($method);
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

        return $url . static::SERVICES[$name] . 'ComprobantesOffline?wsdl';
    }
}
