<?php
declare(strict_types=1); namespace Test;

use Narrowspark\Discovery\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;

class Configurator implements ConfiguratorContract
{
    /**     * {@inheritdoc}     */

    public static function getName(): string
    {
        return 'test';
    }


    /**     * {@inheritdoc}     */

    public function configure(PackageContract $package): void
    {
    }


    /**     * {@inheritdoc}     */

    public function unconfigure(PackageContract $package): void
    {
    }
}
