<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use FEEC\Request\Client;

use const FEEC\ENV_PROD;
use const FEEC\ENV_TEST;
use const FEEC\STATUS_AUTHORIZED;
use const FEEC\STATUS_NOT_FOUND;
use const FEEC\STATUS_RECEIVED;
use const FEEC\STATUS_REJECTED;

$root = dirname(__DIR__, 3);
$port = freePort();
$suffix = getmypid() . '-' . bin2hex(random_bytes(3));
$statePath = sys_get_temp_dir() . '/feec-simulator-' . $suffix . '.json';
$payloadPath = $statePath . '.payloads';
$logPath = sys_get_temp_dir() . '/feec-simulator-' . $suffix . '.log';
$process = null;
$base = '/comprobantes-electronicos-ws/';
$reception = $base . 'RecepcionComprobantesOffline';
$authorization = $base . 'AutorizacionComprobantesOffline';

try {
    $process = server($root, $port, $statePath, $logPath);
    waitForServer($port);
    $origin = 'http://127.0.0.1:' . $port;

    $receptionWsdl = request($origin . $reception . '?wsdl');
    $authorizationWsdl = request($origin . $authorization . '?wsdl');
    check(validXml($receptionWsdl), 'reception WSDL');
    check(validXml($authorizationWsdl), 'authorization WSDL');
    check(str_contains($receptionWsdl, $origin), 'local WSDL address');

    $client = new Client(ENV_TEST, $origin);
    $method = new ReflectionMethod($client, 'urlFromMethod');
    $method->setAccessible(true);
    check(
        $method->invoke($client, 'validarComprobante') ===
            $origin . $reception . '?wsdl',
        'single-origin reception mapping'
    );
    check(
        $method->invoke($client, 'autorizacionComprobante') ===
            $origin . $authorization . '?wsdl',
        'single-origin authorization mapping'
    );
    expectFailure(
        fn() => new Client(ENV_PROD, $origin),
        'production simulator origin rejected'
    );

    $key = '0102202401180191122100110010030000000011234567811';
    $document = '<factura><infoTributaria><claveAcceso>' . $key .
        '</claveAcceso></infoTributaria></factura>';
    $received = $client->send('validarComprobante', ['xml' => $document]);
    check($received->status === STATUS_RECEIVED, 'document received');

    $authorized = $client->send('autorizacionComprobante', [
        'claveAccesoComprobante' => $key,
    ]);
    check($authorized->status === STATUS_AUTHORIZED, 'document authorized');
    check($authorized->isValid, 'authorized document is valid');
    check($authorized->xml === $document, 'authorized document payload');

    $updated = control($origin, '/__simulator/documents', 'POST', [
        'key' => $key,
        'status' => 'rejected',
    ]);
    check($updated['status'] === 'rejected', 'document state control');
    control($origin, '/__simulator/documents', 'POST', [
        'key' => $key,
        'status' => 'authorized',
    ]);

    $duplicate = $client->send('validarComprobante', ['xml' => $document]);
    check($duplicate->isValid, 'duplicate access key');
    check($duplicate->notifications[0]->code === 43, 'duplicate code');

    control($origin, '/__simulator/scenario', 'POST', [
        'next_authorization' => 'rejected',
    ]);
    $rejectedKey = '0102202401180191122100110010030000000021234567815';
    $rejectedDocument = '<factura><claveAcceso>' . $rejectedKey .
        '</claveAcceso></factura>';
    $client->send('validarComprobante', ['xml' => $rejectedDocument]);
    $rejected = $client->send('autorizacionComprobante', [
        'claveAccesoComprobante' => $rejectedKey,
    ]);
    check($rejected->status === STATUS_REJECTED, 'configured rejection');

    $missing = $client->send('autorizacionComprobante', [
        'claveAccesoComprobante' =>
            '0000000000000000000000000000000000000000000000000',
    ]);
    check($missing->status === STATUS_NOT_FOUND, 'missing document');

    $state = control($origin, '/__simulator/state');
    $serialized = json_encode($state, JSON_THROW_ON_ERROR);
    check(!str_contains($serialized, '<factura>'), 'document XML not stored');
    check(!str_contains($serialized, 'password'), 'password not stored');
    check($state['operations']['reception'] === 3, 'reception operation count');
    check($state['operations']['authorization'] === 3, 'authorization operation count');
    check(is_dir($payloadPath), 'private payload storage');
    control($origin, '/__simulator/reset', 'POST');
    clearstatcache(true, $payloadPath);
    check(!is_dir($payloadPath), 'private payload reset');

    echo "Ecuador simulator smoke passed.\n";
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    foreach ([$statePath, $logPath] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_dir($payloadPath)) {
        foreach (glob($payloadPath . '/*.payload') ?: [] as $path) {
            unlink($path);
        }
        rmdir($payloadPath);
    }
}

function request(
    string $url,
    string $method = 'GET',
    ?string $body = null,
    array $headers = []
): string {
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
    ]);
    $content = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if (!is_string($content) || $status < 200 || $status >= 300) {
        throw new RuntimeException('Simulator HTTP request failed.');
    }

    return $content;
}

function validXml(string $xml): bool
{
    $dom = new DOMDocument();

    return $dom->loadXML($xml, LIBXML_NONET);
}

function server(string $root, int $port, string $state, string $log)
{
    $command = sprintf(
        'FEEC_SIMULATOR_STATE=%s %s -S 127.0.0.1:%d %s',
        escapeshellarg($state),
        escapeshellarg(PHP_BINARY),
        $port,
        escapeshellarg($root . '/tests/Support/Simulator/router.php')
    );

    return proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['file', $log, 'a'],
        2 => ['file', $log, 'a'],
    ], $pipes, $root);
}

function freePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) {
        throw new RuntimeException($error, $errno);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);

    return (int) substr((string) $address, strrpos((string) $address, ':') + 1);
}

function waitForServer(int $port): void
{
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
        if (is_resource($socket)) {
            fclose($socket);
            return;
        }
        usleep(100000);
    }
    throw new RuntimeException('Simulator server did not start.');
}

function control(
    string $origin,
    string $path,
    string $method = 'GET',
    array $body = []
): array {
    $content = request(
        $origin . $path,
        $method,
        $body ? json_encode($body, JSON_THROW_ON_ERROR) : null,
        ['Content-Type: application/json']
    );

    return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
}

function expectFailure(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (UnexpectedValueException $error) {
        return;
    }
    throw new RuntimeException('Failed check: ' . $message);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $message);
    }
}
