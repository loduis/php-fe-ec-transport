<?php

namespace FEEC;

use Error;
use FEEC\Signer;
use Exception;
use FEEC\Request\Client;
use FEEC\Document\Contract as Document;

use function XML\Signature\x509;

class Request
{
    private Client $client;

    private Signer $signer;

    public function __construct(string $filename, string $password, int $environment = ENV_TEST)
    {
        $this->client = new Client($environment);

        $this->signer = new Signer([
            'certificate' => x509([
                'password' => $password,
                'filename' => $filename
            ])
        ]);
    }

    public function send(iterable $docs)
    {
        if ($docs instanceof Document) {
            return $this->request('validarComprobante', [
                'xml' => $this->prepare($docs)
            ]);
        }

        throw new Error('Need implement BATCH');
        /*
        if (!is_array($docs)) {
            $docs = [$docs];
        }

        $params = [];
        $action = count($docs) > 1 ? 'autorizacionComprobanteLote' : 'autorizacionComprobante';

        foreach ($docs as $doc) {
            $this->prepare($doc);
        }

        return $this->request($action, $params);
        */
    }

    public function find(string $accessKey)
    {
        return $this->request('autorizacionComprobante', [
          'claveAccesoComprobante' => $accessKey
        ]);
    }

    public function sign($doc)
    {
        return $this->signer->sign($doc);
    }

    protected function request(string $action, array $params = [])
    {
        if ($this->signer->isExpired()) {
            throw new Exception('The certificate has expired.');
        }

        return $this->client->send($action, $params);
    }

    protected function prepare(Document $doc): string
    {
        if ($this->client->environment != $doc->environment) {
            throw new Exception('The environment not match');
        }

        $dom = $doc->create(true);

        return $this->signer->sign($dom);
    }
}
