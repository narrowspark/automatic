<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher;

class FunctionMock
{
    public static $isOpensslActive = true;
}

function extension_loaded(string $ext): bool
{
    if ($ext === 'openssl') {
        return FunctionMock::$isOpensslActive;
    }

    return \extension_loaded($ext);
}
