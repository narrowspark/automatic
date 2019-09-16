<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test\Installer;

use Narrowspark\Automatic\Installer\ConfiguratorInstaller;

/**
 * @internal
 *
 * @small
 */
final class ConfiguratorInstallerTest extends AbstractInstallerTest
{
    /**
     * {@inheritdoc}
     */
    protected $installerClass = ConfiguratorInstaller::class;
}
