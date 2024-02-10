<?php

namespace FEEC\Response;

use stdClass;

use function FEEC\arr_obj;

abstract class Model
{
    public string $response;

    public string $request;

    public $notifications = [];

    public function __construct(stdClass $objRes, iterable $opts)
    {
        $this->request = $this->encode($opts['request']);
        $this->response = $this->encode($opts['response']);
        $this->init($objRes, $opts);
    }

    protected function addNotifications(stdClass $res)
    {
        $mensajes = $res->mensajes ? $res->mensajes : [];
        foreach ($mensajes as $mensaje) {
            $this->notifications[] = arr_obj([
                'code' => (int) $mensaje->identificador,
                'message' => $mensaje->mensaje,
                'description' => $mensaje->informacionAdicional ?? null,
                'type' => $mensaje->tipo ?? 'ERROR'
            ]);
        }
    }

    private function encode(string $content)
    {
        return base64_encode(gzdeflate($content, 9));
    }

    abstract protected function init(stdClass $res, iterable $opts = []);
}
