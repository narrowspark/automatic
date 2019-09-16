<?php

declare(strict_types=1);

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
