<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic;

if (! \class_exists(FunctionMock::class)) {
    class FunctionMock
    {
        public static $isOpensslActive = true;
    }
}

if (! \function_exists('\Narrowspark\Automatic\extension_loaded')) {
    function extension_loaded(string $ext): bool
    {
        if ($ext === 'openssl') {
            return FunctionMock::$isOpensslActive;
        }

        return \extension_loaded($ext);
    }
}

namespace Narrowspark\Automatic\Configurator;

if (! \function_exists('\Narrowspark\Automatic\Configurator\getcwd')) {
    function getcwd()
    {
        return \sys_get_temp_dir();
    }
}

namespace Narrowspark\Automatic\Common\Configurator;

if (! \function_exists('\Narrowspark\Automatic\Common\Configurator\getcwd')) {
    function getcwd()
    {
        return \sys_get_temp_dir();
    }
}
