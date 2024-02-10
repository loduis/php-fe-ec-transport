<?php

namespace FEEC\Response;

use DateTime;
use stdClass;

use const FEEC\ENV_PROD;
use const FEEC\ENV_TEST;
use const FEEC\STATUS_AUTHORIZED;
use const FEEC\STATUS_NOT_AUTHORIZED;
use const FEEC\STATUS_NOT_FOUND;
use const FEEC\STATUS_REJECTED;

class Status extends Model
{
    public string $key;

    public bool $isValid = false;

    public int $environment;

    public ?DateTime $authorizedAt = null;

    public string $status;

    public ?string $xml = null;

    protected function init(stdClass $res, iterable $opts = [])
    {
        $this->key = $res->claveAccesoConsultada ?? 'N/A';
        $count = $res->numeroComprobantes ?? null;
        if ($count === null) {
            $auth = $res->autorizaciones->autorizacion;
            $this->status = $auth->estado === 'RECHAZADA' ? STATUS_REJECTED : $auth->estado;
            $this->isValid = $this->status === STATUS_AUTHORIZED;
            $this->environment = $opts['environment'];
            $this->addNotifications($auth);
        } elseif ($count == 0) {
            $this->status = STATUS_NOT_FOUND;
            $this->environment = $opts['environment'];
        } elseif ($count == 1) {
            $auth = $res->autorizaciones->autorizacion;
            if (isset($auth->numeroAutorizacion)) {
                $this->key = $auth->numeroAutorizacion;
            }
            $this->xml = trim($auth->comprobante);
            $this->authorizedAt = new DateTime($auth->fechaAutorizacion);
            $this->environment = $auth->ambiente === 'PRODUCCIÓN' ? ENV_PROD : ENV_TEST;
            $this->status = $auth->estado;
            if ($this->status === STATUS_NOT_AUTHORIZED) {
                $this->status = STATUS_REJECTED;
            }
            $this->isValid = $this->status === STATUS_AUTHORIZED;
            $this->addNotifications($auth);
        }
    }
}
