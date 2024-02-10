<?php

namespace FEEC;

use stdClass;

const HANDLERS = [
    'autorizacionComprobante' => 'Status',
    'validarComprobante' => 'Send',
];

class Response
{
    public static function from(stdClass $res, iterable $opts)
    {
        $action = $opts['action'];
        $Handler = __CLASS__ . '\\' .  HANDLERS[$action];
        $key = 'Respuesta' . ucfirst($action);
        return new $Handler($res->$key ?? $res, $opts);
    }
}
