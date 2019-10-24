<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
