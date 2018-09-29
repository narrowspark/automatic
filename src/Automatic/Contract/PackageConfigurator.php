<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Contract;

interface PackageConfigurator extends Configurator
{
    /**
     * @var string
     */
    public const TYPE = 'custom-configurators';
}
