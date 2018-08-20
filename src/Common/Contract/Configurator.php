<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Contract;

interface Configurator
{
    /**
     * @var string
     */
    public const TYPE = 'configurators';

    /**
     * Return the configurator key name.
     *
     * @return string
     */
    public static function getName(): string;

    /**
     * Configure the application after the package settings.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     *
     * @return void
     */
    public function configure(Package $package): void;

    /**
     * Unconfigure the application after the package settings.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     *
     * @return void
     */
    public function unconfigure(Package $package): void;
}
