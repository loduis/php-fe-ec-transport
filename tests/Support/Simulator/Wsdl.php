<?php

declare(strict_types=1);

namespace FEEC\Tests\Support\Simulator;

final class Wsdl
{
    public static function reception(string $location): string
    {
        return self::document(
            'RecepcionComprobantesOffline',
            'http://ec.gob.sri.ws.recepcion',
            'validarComprobante',
            '<xsd:element name="xml" type="xsd:base64Binary"/>',
            '<xsd:element name="RespuestaRecepcionComprobante" ' .
                'type="tns:RespuestaRecepcionComprobante" minOccurs="0"/>',
            self::receptionTypes(),
            $location
        );
    }

    public static function authorization(string $location): string
    {
        return self::document(
            'AutorizacionComprobantesOffline',
            'http://ec.gob.sri.ws.autorizacion',
            'autorizacionComprobante',
            '<xsd:element name="claveAccesoComprobante" type="xsd:string"/>',
            '<xsd:element name="RespuestaAutorizacionComprobante" ' .
                'type="tns:RespuestaAutorizacionComprobante" minOccurs="0"/>',
            self::authorizationTypes(),
            $location
        );
    }

    private static function document(
        string $service,
        string $namespace,
        string $operation,
        string $input,
        string $output,
        string $types,
        string $location
    ): string {
        $response = $operation . 'Response';

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<wsdl:definitions xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/" ' .
            'xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" ' .
            'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ' .
            'xmlns:tns="' . $namespace . '" targetNamespace="' .
            $namespace . '" name="' . $service . '">' .
            '<wsdl:types><xsd:schema targetNamespace="' . $namespace . '" ' .
            'elementFormDefault="unqualified"><xsd:element name="' .
            $operation . '"><xsd:complexType><xsd:sequence>' . $input .
            '</xsd:sequence></xsd:complexType></xsd:element>' .
            '<xsd:element name="' . $response . '"><xsd:complexType>' .
            '<xsd:sequence>' . $output . '</xsd:sequence></xsd:complexType>' .
            '</xsd:element>' . $types . '</xsd:schema></wsdl:types>' .
            '<wsdl:message name="' . $operation . '"><wsdl:part ' .
            'name="parameters" element="tns:' . $operation . '"/>' .
            '</wsdl:message><wsdl:message name="' . $response . '">' .
            '<wsdl:part name="parameters" element="tns:' . $response . '"/>' .
            '</wsdl:message><wsdl:portType name="' . $service . '">' .
            '<wsdl:operation name="' . $operation . '"><wsdl:input ' .
            'message="tns:' . $operation . '"/><wsdl:output message="tns:' .
            $response . '"/></wsdl:operation></wsdl:portType>' .
            '<wsdl:binding name="' . $service . 'Binding" type="tns:' .
            $service . '"><soap:binding style="document" transport="http://' .
            'schemas.xmlsoap.org/soap/http"/><wsdl:operation name="' .
            $operation . '"><soap:operation soapAction=""/><wsdl:input>' .
            '<soap:body use="literal"/></wsdl:input><wsdl:output>' .
            '<soap:body use="literal"/></wsdl:output></wsdl:operation>' .
            '</wsdl:binding><wsdl:service name="' . $service . '">' .
            '<wsdl:port name="' . $service . 'Port" binding="tns:' .
            $service . 'Binding"><soap:address location="' .
            htmlspecialchars($location, ENT_XML1 | ENT_QUOTES, 'UTF-8') .
            '"/></wsdl:port></wsdl:service></wsdl:definitions>';
    }

    private static function receptionTypes(): string
    {
        return '<xsd:complexType name="RespuestaRecepcionComprobante">' .
            '<xsd:sequence><xsd:element name="estado" type="xsd:string" ' .
            'minOccurs="0"/><xsd:element name="comprobantes" ' .
            'type="tns:Comprobantes" minOccurs="0"/></xsd:sequence>' .
            '</xsd:complexType><xsd:complexType name="Comprobantes">' .
            '<xsd:sequence><xsd:element name="comprobante" ' .
            'type="tns:Comprobante" minOccurs="0" maxOccurs="unbounded"/>' .
            '</xsd:sequence></xsd:complexType><xsd:complexType ' .
            'name="Comprobante"><xsd:sequence><xsd:element ' .
            'name="claveAcceso" type="xsd:string" minOccurs="0"/>' .
            '<xsd:element name="mensajes" type="tns:Mensajes" ' .
            'minOccurs="0"/></xsd:sequence></xsd:complexType>' .
            self::messageTypes();
    }

    private static function authorizationTypes(): string
    {
        return '<xsd:complexType name="RespuestaAutorizacionComprobante">' .
            '<xsd:sequence><xsd:element name="claveAccesoConsultada" ' .
            'type="xsd:string" minOccurs="0"/><xsd:element ' .
            'name="numeroComprobantes" type="xsd:int" minOccurs="0"/>' .
            '<xsd:element name="autorizaciones" type="tns:Autorizaciones" ' .
            'minOccurs="0"/></xsd:sequence></xsd:complexType>' .
            '<xsd:complexType name="Autorizaciones"><xsd:sequence>' .
            '<xsd:element name="autorizacion" type="tns:Autorizacion" ' .
            'minOccurs="0" maxOccurs="unbounded"/></xsd:sequence>' .
            '</xsd:complexType><xsd:complexType name="Autorizacion">' .
            '<xsd:sequence><xsd:element name="estado" type="xsd:string"/>' .
            '<xsd:element name="numeroAutorizacion" type="xsd:string" ' .
            'minOccurs="0"/><xsd:element name="fechaAutorizacion" ' .
            'type="xsd:dateTime" minOccurs="0"/><xsd:element name="ambiente" ' .
            'type="xsd:string" minOccurs="0"/><xsd:element ' .
            'name="comprobante" type="xsd:string" minOccurs="0"/>' .
            '<xsd:element name="mensajes" type="tns:Mensajes" ' .
            'minOccurs="0"/></xsd:sequence></xsd:complexType>' .
            self::messageTypes();
    }

    private static function messageTypes(): string
    {
        return '<xsd:complexType name="Mensajes"><xsd:sequence>' .
            '<xsd:element name="mensaje" type="tns:Mensaje" minOccurs="0" ' .
            'maxOccurs="unbounded"/></xsd:sequence></xsd:complexType>' .
            '<xsd:complexType name="Mensaje"><xsd:sequence>' .
            '<xsd:element name="identificador" type="xsd:int"/>' .
            '<xsd:element name="mensaje" type="xsd:string"/>' .
            '<xsd:element name="informacionAdicional" type="xsd:string" ' .
            'minOccurs="0"/><xsd:element name="tipo" type="xsd:string" ' .
            'minOccurs="0"/></xsd:sequence></xsd:complexType>';
    }
}
