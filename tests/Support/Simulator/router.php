<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

(new FEEC\Tests\Support\Simulator\Simulator())->run();
