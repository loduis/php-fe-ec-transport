<?php

namespace FEEC\Response;

use DateTime;
use stdClass;
use const FEEC\ENV_PROD;
use const FEEC\ENV_TEST;
use const FEEC\STATUS_AUTHORIZED;

class Status
{
    public string $xml;

    public string $key;

    public bool $isValid;

    public int $environment;

    public DateTime $authorizedAt;

    public $notifications = [];

    public function __construct(stdClass $res)
    {
        $auth = $res->autorizaciones->autorizacion;
        $this->key = $auth->numeroAutorizacion;
        $this->xml = trim($auth->comprobante);
        $this->authorizedAt = new DateTime($auth->fechaAutorizacion);
        $this->environment = $auth->ambiente === 'PRODUCCIÓN' ? ENV_PROD : ENV_TEST;
        $this->isValid = $auth->estado === STATUS_AUTHORIZED;
        $this->notifications = $auth->mensajes ? $auth->mensajes : [];
    }
}
