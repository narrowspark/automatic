<?php

declare(strict_types=1);

namespace Narrowspark\Automatic;

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

namespace Narrowspark\Automatic\Configurator;

function getcwd()
{
    return \sys_get_temp_dir();
}

namespace Narrowspark\Automatic\Common\Configurator;

function getcwd()
{
    return \sys_get_temp_dir();
}
