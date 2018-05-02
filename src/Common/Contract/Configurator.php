<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Common\Contract;

interface Configurator
{
    /**
     * Return the configurator key name.
     *
     * @return string
     */
    public static function getName(): string;

    /**
     * Configure the application after the package settings.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     *
     * @return void
     */
    public function configure(Package $package): void;

    /**
     * Unconfigure the application after the package settings.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     *
     * @return void
     */
    public function unconfigure(Package $package): void;
}
