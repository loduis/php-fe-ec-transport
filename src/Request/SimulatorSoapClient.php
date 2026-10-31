<?php

declare(strict_types=1);

namespace FEEC\Request;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use stdClass;

final class SimulatorSoapClient
{
    private const NAMESPACES = [
        'validarComprobante' => 'http://ec.gob.sri.ws.recepcion',
        'autorizacionComprobante' =>
            'http://ec.gob.sri.ws.autorizacion',
    ];

    public function __construct(private string $endpoint)
    {
    }

    public function send(string $operation, array $params): array
    {
        $namespace = self::NAMESPACES[$operation] ?? null;
        if ($namespace === null) {
            throw new RuntimeException('Unsupported SRI simulator operation.');
        }
        $request = $this->envelope($operation, $namespace, $params);
        $handle = curl_init($this->endpoint);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize local SOAP.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=UTF-8',
                'User-Agent: FEEC-Transport-Simulator/1.0',
            ],
            CURLOPT_POSTFIELDS => $request,
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response) || $status < 200 || $status >= 300) {
            $path = (string) (parse_url($this->endpoint, PHP_URL_PATH) ?: '/');
            throw new RuntimeException(
                'The local SOAP request failed for ' . $path . ': ' .
                    ($error ?: $status)
            );
        }

        return [$this->decode($response), $request, $response];
    }

    private function envelope(
        string $operation,
        string $namespace,
        array $params
    ): string {
        $values = '';
        foreach ($params as $name => $value) {
            $value = $name === 'xml'
                ? base64_encode((string) $value)
                : (string) $value;
            $values .= '<' . $name . '>' . $this->escape($value) .
                '</' . $name . '>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/' .
            'envelope/"><soap:Body><ns2:' . $operation . ' xmlns:ns2="' .
            $namespace . '">' . $values . '</ns2:' . $operation . '>' .
            '</soap:Body></soap:Envelope>';
    }

    private function decode(string $xml): stdClass
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The local SOAP response is invalid.');
        }
        $payload = (new DOMXPath($dom))->query(
            '/*[local-name()="Envelope"]/*[local-name()="Body"]' .
            '/*[1]/*[1]'
        )?->item(0);
        if (!$payload instanceof DOMElement) {
            throw new RuntimeException('The local SOAP payload is missing.');
        }

        return $this->object($payload);
    }

    private function object(DOMElement $element): stdClass
    {
        $result = new stdClass();
        foreach ($this->children($element) as $child) {
            if ($child->localName === 'mensajes') {
                $result->mensajes = array_map(
                    fn(DOMElement $message): stdClass =>
                        $this->object($message),
                    $this->children($child)
                );
                continue;
            }
            $children = $this->children($child);
            $value = $children ? $this->object($child) : trim(
                $child->textContent
            );
            if (!isset($result->{$child->localName})) {
                $result->{$child->localName} = $value;
                continue;
            }
            if (!is_array($result->{$child->localName})) {
                $result->{$child->localName} = [
                    $result->{$child->localName},
                ];
            }
            $result->{$child->localName}[] = $value;
        }

        return $result;
    }

    private function children(DOMElement $element): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
