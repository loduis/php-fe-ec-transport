<?php

namespace FEEC;

use FEEC\Signer;
use Exception;
use FEEC\Request\Client;

class Request
{
    private Client $client;

    public function __construct(int $environment)
    {
        $this->client = new Client($environment);
    }

    public function send(iterable $docs)
    {
        if (!is_array($docs)) {
            $docs = [$docs];
        }
        $params = [];
        $action = count($docs) > 1 ? 'autorizacionComprobanteLote' : 'autorizacionComprobante';

        foreach ($docs as $doc) {
            $this->prepare($doc);
        }

        return $this->request($action, $params);
    }

    public function find(string $trackId)
    {
        $method = 'autorizacionComprobante';

        return $this->request($method, [
          'claveAccesoComprobante' => $trackId
        ]);
    }

    public function sign($doc)
    {
        return (new Signer(['certificate' => $this->certificate]))->sign($doc);
    }

    protected function request(string $action, array $params = [])
    {
        if ($this->signer->isExpired()) {
            throw new Exception('The certificate has expired.');
        }

        return $this->client->send($action, $params);
    }

    protected function prepare($doc)
    {
        if ($this->client->environment != $doc->environment) {
            throw new Exception('The environment not match');
        }

        return [
            $doc,
        ];
    }
}
