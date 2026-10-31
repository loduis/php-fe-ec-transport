# Ecuador transport simulator

The simulator exposes local WSDL and SOAP endpoints for both reception and
authorization. It never contacts the fiscal service.

Start it with:

```bash
FEEC_SIMULATOR_STATE=/tmp/feec-simulator.json \
php -S 127.0.0.1:8092 tests/Support/Simulator/router.php
```

Configure the existing request in one place:

```php
$request = new FEEC\Request(
    $certificatePath,
    $certificatePassword,
    FEEC\ENV_TEST,
    'http://127.0.0.1:8092'
);
```

The origin is rejected outside the test environment. Both operation-specific
WSDL URLs are derived automatically. The first version covers received,
duplicate, pending, authorized, rejected, invalid XML and not-found scenarios.

Run the local end-to-end check with:

```bash
php tests/Support/Simulator/smoke.php
```
