<?php

namespace FEEC\Response;

use stdClass;

use const FEEC\STATUS_FAILED;
use const FEEC\STATUS_REJECTED;

class Send extends Model
{
    public string $key;

    public bool $isValid = false;

    public bool $duplicate = false;

    public int $environment;

    public string $status;

    protected function init(stdClass $res, iterable $opts = [])
    {
        if (isset($res->RespuestaRecepcionComprobante)) {
            $res = $res->RespuestaRecepcionComprobante;
        }
        $comprobante = $res->comprobantes->comprobante ?? null;
        $this->key = $comprobante->claveAcceso ?? 'N/A';
        $this->environment = $opts['environment'];
        $this->status = $res->estado ?? STATUS_REJECTED;
        if ($this->status === STATUS_FAILED) {
            $this->status = STATUS_REJECTED;
        }
        if ($comprobante) {
            $this->addNotifications($comprobante);

            if ($this->status === STATUS_REJECTED) {
                foreach ($this->notifications as $notify) {
                    if ($notify->code === 43) {
                        $this->isValid = true;
                    } else if ($notify->code === 45) {
                        $this->duplicate = true;
                    }
                }
            }
        }
    }
}
