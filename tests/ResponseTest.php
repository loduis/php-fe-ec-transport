<?php

namespace FEEC\Tests;

use FEEC\ {
    Response
};
use FEEC\Response\Status;
use SoapClient;
use XML\Tests\ {
    TestCase
};

use const FEEC\ENV_TEST;

class ResponseTest extends TestCase
{
    public function testShouldGetStatusAuthorized()
    {
        $xml  = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Body>
          <ns2:autorizacionComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.autorizacion">
            <RespuestaAutorizacionComprobante>
              <claveAccesoConsultada>0502202401159198472200125920040000885468154978913</claveAccesoConsultada>
              <numeroComprobantes>1</numeroComprobantes>
              <autorizaciones>
                <autorizacion>
                  <estado>AUTORIZADO</estado>
                  <numeroAutorizacion>0502202401159198472200125920040000885468154978913</numeroAutorizacion>
                  <fechaAutorizacion>2024-02-06T09:18:53-05:00</fechaAutorizacion>
                  <ambiente>PRUEBAS</ambiente>
                  <comprobante><![CDATA[ XML AQUI]]></comprobante>
                  <mensajes />
                </autorizacion>
              </autorizaciones>
            </RespuestaAutorizacionComprobante>
          </ns2:autorizacionComprobanteResponse>
        </soap:Body>
        </soap:Envelope>';

        $res = response($xml, 'autorizacionComprobante', [
            'claveAccesoComprobante' => '0502202401159198472200125920040000885468154978913'
        ]);
        $this->assertInstanceOf(Status::class, $res);
        $this->assertEquals('0502202401159198472200125920040000885468154978913', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertTrue($res->isValid);
        $this->assertEquals('2024-02-06T09:18:53-05:00', $res->authorizedAt->format('Y-m-d\TH:i:sP'));
        $this->assertEquals('XML AQUI', $res->xml);
        $this->assertEmpty($res->notifications);
    }

}

function response($xml, $action, $params = [])
{
    $context = stream_context_create(array(
        'ssl' => array(
        'verify_peer' => false,
        )
    ));

    $client = new class($xml, null, [
        "trace" => 1,
        "exception" => 1,
        'stream_context' => $context,
        'uri'      => "http://test-uri/",
        'location' => "http://test-uri/",
    ]) extends SoapClient {
        private string $xml;
        public function __construct(string $xml, ?string $wsdl, array $options = [])
        {
            $this->xml = $xml;

            parent::__construct($wsdl, $options);
        }

        public function __doRequest($request, $location, $action, $version, $one_way = 0) {
            return $this->xml;
        }
    };

    $res = $client->$action($params);

    return Response::from($res, [
        'action' => $action,
        'params' => $params
    ]);
}
