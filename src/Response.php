<?php

namespace FEEC;

use stdClass;

const HANDLERS = [
    'autorizacionComprobante' => 'Status',
    'SendBillSync' => 'SendSync',
];

class Response
{
    public static function from(stdClass $res, array $request)
    {
        $action = $request['action'];
        $Handler = __CLASS__ . '\\' .  HANDLERS[$action];

        return new $Handler($res);
    }
}
