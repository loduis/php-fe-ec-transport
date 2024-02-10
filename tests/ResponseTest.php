<?php

namespace FEEC\Tests;

use FEEC\ {
    Response
};
use FEEC\Response\Send;
use FEEC\Response\Status;
use SoapClient;
use XML\Tests\ {
    TestCase
};

use const FEEC\ENV_TEST;
use const FEEC\STATUS_AUTHORIZED;
use const FEEC\STATUS_NOT_FOUND;
use const FEEC\STATUS_RECEIVED;
use const FEEC\STATUS_REJECTED;

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
                  <comprobante><![CDATA[ COMPROBANTE ]]></comprobante>
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
        $this->assertEquals(STATUS_AUTHORIZED, $res->status);
        $this->assertTrue($res->isValid);
        $this->assertEquals('2024-02-06T09:18:53-05:00', $res->authorizedAt->format('Y-m-d\TH:i:sP'));
        $this->assertEquals('COMPROBANTE', $res->xml);
        $this->assertEmpty($res->notifications);
    }

    public function testShouldGivenANotFound()
    {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:autorizacionComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.autorizacion"><RespuestaAutorizacionComprobante><claveAccesoConsultada>0102202401180191152100110010030000000011234567811</claveAccesoConsultada><numeroComprobantes>0</numeroComprobantes><autorizaciones/></RespuestaAutorizacionComprobante></ns2:autorizacionComprobanteResponse></soap:Body></soap:Envelope>';

        $res = response($xml, 'autorizacionComprobante', [
            'claveAccesoComprobante' => '0102202401180191152100110010030000000011234567811'
        ]);
        $this->assertInstanceOf(Status::class, $res);
        $this->assertEquals('0102202401180191152100110010030000000011234567811', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertEquals(STATUS_NOT_FOUND, $res->status);
        $this->assertFalse($res->isValid);
        $this->assertNull($res->authorizedAt);
        $this->assertNull($res->xml);
        $this->assertEmpty($res->notifications);
    }

    public function testShouldGivenInvalidAccessKey()
    {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:autorizacionComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.autorizacion"><RespuestaAutorizacionComprobante><autorizaciones><autorizacion><estado>RECHAZADA</estado><mensajes><mensaje><identificador>80</identificador><mensaje>ERROR EN LA ESTRUCTURA DE LA CLAVE DE ACCESO</mensaje></mensaje></mensajes></autorizacion></autorizaciones></RespuestaAutorizacionComprobante></ns2:autorizacionComprobanteResponse></soap:Body></soap:Envelope>';

        $res = response($xml, 'autorizacionComprobante', [
            'claveAccesoComprobante' => '0102202401180191152100110010030000000011234567811'
        ]);
        $this->assertInstanceOf(Status::class, $res);
        $this->assertEquals('N/A', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertEquals(STATUS_REJECTED, $res->status);
        $this->assertFalse($res->isValid);
        $this->assertNull($res->authorizedAt);
        $this->assertNull($res->xml);

        $this->assertCount(1, $res->notifications);
        $entry = $res->notifications[0];
        $this->assertEquals(80, $entry->code);
        $this->assertEquals('ERROR', $entry->type);
        $this->assertEquals('ERROR EN LA ESTRUCTURA DE LA CLAVE DE ACCESO', $entry->message);
        $this->assertNull($entry->description);

    }

    //

    public function testShouldGivenNotAuthorized()
    {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:autorizacionComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.autorizacion"><RespuestaAutorizacionComprobante><claveAccesoConsultada>0102202401180191122100110010030000000211234567819</claveAccesoConsultada><numeroComprobantes>1</numeroComprobantes><autorizaciones><autorizacion><estado>NO AUTORIZADO</estado><fechaAutorizacion>2024-02-09T17:40:28-05:00</fechaAutorizacion><ambiente>PRUEBAS</ambiente><comprobante><![CDATA[ COMPROBANTE ]]></comprobante><mensajes><mensaje><identificador>39</identificador><mensaje>FIRMA INVALIDA</mensaje><informacionAdicional>La firma es invalida [Firma inválida (firma y/o certificados alterados)]</informacionAdicional><tipo>ERROR</tipo></mensaje></mensajes></autorizacion></autorizaciones></RespuestaAutorizacionComprobante></ns2:autorizacionComprobanteResponse></soap:Body></soap:Envelope>';

        $res = response($xml, 'autorizacionComprobante', [
            'claveAccesoComprobante' => '0102202401180191122100110010030000000211234567819'
        ]);

        $this->assertInstanceOf(Status::class, $res);
        $this->assertEquals('0102202401180191122100110010030000000211234567819', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertEquals(STATUS_REJECTED, $res->status);
        $this->assertFalse($res->isValid);
        $this->assertEquals('2024-02-09T17:40:28-05:00', $res->authorizedAt->format('Y-m-d\TH:i:sP'));
        $this->assertEquals('COMPROBANTE', $res->xml);
        $this->assertCount(1, $res->notifications);
        $entry = $res->notifications[0];
        $this->assertEquals(39, $entry->code);
        $this->assertEquals('ERROR', $entry->type);
        $this->assertEquals('FIRMA INVALIDA', $entry->message);
        $this->assertEquals('La firma es invalida [Firma inválida (firma y/o certificados alterados)]', $entry->description);
    }

    public function testShouldGivenNotAuthorizedNotSendCert()
    {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:autorizacionComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.autorizacion"><RespuestaAutorizacionComprobante><claveAccesoConsultada>0102202401180191122100110010030000000021234567815</claveAccesoConsultada><numeroComprobantes>1</numeroComprobantes><autorizaciones><autorizacion><estado>NO AUTORIZADO</estado><fechaAutorizacion>2024-02-10T07:34:47-05:00</fechaAutorizacion><ambiente>PRUEBAS</ambiente><comprobante><![CDATA[ COMPROBANTE ]]></comprobante><mensajes><mensaje><identificador>39</identificador><mensaje>FIRMA INVALIDA</mensaje><informacionAdicional>No se pudo convertir en Certificado X509</informacionAdicional><tipo>ERROR</tipo></mensaje></mensajes></autorizacion></autorizaciones></RespuestaAutorizacionComprobante></ns2:autorizacionComprobanteResponse></soap:Body></soap:Envelope>
        ';

        $res = response($xml, 'autorizacionComprobante', [
            'claveAccesoComprobante' => '0102202401180191122100110010030000000021234567815'
        ]);

        $this->assertInstanceOf(Status::class, $res);
        $this->assertEquals('0102202401180191122100110010030000000021234567815', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertEquals(STATUS_REJECTED, $res->status);
        $this->assertFalse($res->isValid);
        $this->assertEquals('2024-02-10T07:34:47-05:00', $res->authorizedAt->format('Y-m-d\TH:i:sP'));
        $this->assertEquals('COMPROBANTE', $res->xml);
        $this->assertCount(1, $res->notifications);
        $entry = $res->notifications[0];
        $this->assertEquals(39, $entry->code);
        $this->assertEquals('ERROR', $entry->type);
        $this->assertEquals('FIRMA INVALIDA', $entry->message);
        $this->assertEquals('No se pudo convertir en Certificado X509', $entry->description);
    }

    public function testShouldGivenASendBackAuthorized()
    {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:validarComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.recepcion"><RespuestaRecepcionComprobante><estado>DEVUELTA</estado><comprobantes><comprobante><claveAcceso>0102202401180191122100110010030000000011234567811</claveAcceso><mensajes><mensaje><identificador>43</identificador><mensaje>CLAVE ACCESO REGISTRADA</mensaje><tipo>ERROR</tipo></mensaje></mensajes></comprobante></comprobantes></RespuestaRecepcionComprobante></ns2:validarComprobanteResponse></soap:Body></soap:Envelope>';

        $res = response($xml, 'validarComprobante', [
            'xml' => 'COMPROBANTE'
        ]);

        $this->assertInstanceOf(Send::class, $res);
        $this->assertEquals('0102202401180191122100110010030000000011234567811', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertEquals(STATUS_REJECTED, $res->status);
        $this->assertTrue($res->isValid);
        $this->assertCount(1, $res->notifications);
        $entry = $res->notifications[0];
        $this->assertEquals(43, $entry->code);
        $this->assertEquals('ERROR', $entry->type);
        $this->assertEquals('CLAVE ACCESO REGISTRADA', $entry->message);
        $this->assertNull($entry->description);
    }

    public function testShouldGivenASendBackDuplicateNumber()
    {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:validarComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.recepcion"><RespuestaRecepcionComprobante><estado>DEVUELTA</estado><comprobantes><comprobante><claveAcceso>0102202401180191122100110010030000000011234567917</claveAcceso><mensajes><mensaje><identificador>45</identificador><mensaje>ERROR SECUENCIAL REGISTRADO</mensaje><tipo>ERROR</tipo></mensaje></mensajes></comprobante></comprobantes></RespuestaRecepcionComprobante></ns2:validarComprobanteResponse></soap:Body></soap:Envelope>';

        $res = response($xml, 'validarComprobante', [
            'xml' => 'COMPROBANTE'
        ]);

        $this->assertInstanceOf(Send::class, $res);
        $this->assertEquals('0102202401180191122100110010030000000011234567917', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertEquals(STATUS_REJECTED, $res->status);
        $this->assertTrue($res->isValid);
        $this->assertCount(1, $res->notifications);
        $entry = $res->notifications[0];
        $this->assertEquals(45, $entry->code);
        $this->assertEquals('ERROR', $entry->type);
        $this->assertEquals('ERROR SECUENCIAL REGISTRADO', $entry->message);
        $this->assertNull($entry->description);
    }

    public function testShouldGivenASendBackInvalidDocument()
    {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:validarComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.recepcion"><RespuestaRecepcionComprobante><comprobantes><comprobante><claveAcceso>N/A</claveAcceso><mensajes><mensaje><identificador>35</identificador><mensaje>ARCHIVO NO CUMPLE ESTRUCTURA XML</mensaje><informacionAdicional>ec.gob.sri.comprobantes.electronicos.api.excepcion.ConversionArchivoXMLException: Error al convertir el archivo xml</informacionAdicional><tipo>ERROR</tipo></mensaje></mensajes></comprobante></comprobantes></RespuestaRecepcionComprobante></ns2:validarComprobanteResponse></soap:Body></soap:Envelope>';

        $res = response($xml, 'validarComprobante', [
            'xml' => 'COMPROBANTE'
        ]);

        $this->assertInstanceOf(Send::class, $res);
        $this->assertEquals('N/A', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertEquals(STATUS_REJECTED, $res->status);
        $this->assertFalse($res->isValid);
        $this->assertCount(1, $res->notifications);
        $entry = $res->notifications[0];
        $this->assertEquals(35, $entry->code);
        $this->assertEquals('ERROR', $entry->type);
        $this->assertEquals('ARCHIVO NO CUMPLE ESTRUCTURA XML', $entry->message);
        $this->assertEquals('ec.gob.sri.comprobantes.electronicos.api.excepcion.ConversionArchivoXMLException: Error al convertir el archivo xml', $entry->description);
    }

    public function testShouldGivenASendAndRecibe()
    {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:validarComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.recepcion"><RespuestaRecepcionComprobante><estado>RECIBIDA</estado><comprobantes/></RespuestaRecepcionComprobante></ns2:validarComprobanteResponse></soap:Body></soap:Envelope>';

        $res = response($xml, 'validarComprobante', [
            'xml' => 'COMPROBANTE'
        ]);

        $this->assertInstanceOf(Send::class, $res);
        $this->assertEquals('N/A', $res->key);
        $this->assertEquals(ENV_TEST, $res->environment);
        $this->assertEquals(STATUS_RECEIVED, $res->status);
        $this->assertFalse($res->isValid);
        $this->assertEmpty($res->notifications);
    }
}

function response($xml, $action, $params = [])
{
    $client = new class($xml, null, [
        "trace" => 1,
        "exception" => 1,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => false,
            ]
        ]),
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
        'request' => $client->__getLastRequest(),
        'response' => $client->__getLastResponse(),
        'environment' => ENV_TEST
    ]);
}
