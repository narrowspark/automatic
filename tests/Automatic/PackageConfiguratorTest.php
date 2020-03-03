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

namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
use Exception;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\PackageConfigurator;
use Narrowspark\Automatic\Test\Fixture\MockConfigurator;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\PackageConfigurator
 *
 * @medium
 */
final class PackageConfiguratorTest extends AbstractConfiguratorTest
{
    public function testConfiguratorWithPackageConfigurator(): void
    {
        $package = $this->arrangePackageWithConfig(
            'test/test',
            [
                ConfiguratorContract::TYPE => [
                    'mock' => [
                        'test',
                    ],
                ],
            ]
        );

        $this->ioMock->shouldReceive('writeError')
            ->twice()
            ->with(['    - test'], true, IOInterface::VERBOSE);

        $this->configurator->add('mock', MockConfigurator::class);

        $this->configurator->configure($package);
        $this->configurator->unconfigure($package);
    }

    public function testConfiguratorOutWithPackageConfigurator(): void
    {
        $package = $this->arrangePackageWithConfig(
            'test/test',
            [
                ConfiguratorContract::TYPE => [
                    'mock' => [
                        'test',
                    ],
                ],
            ]
        );

        $this->ioMock->shouldReceive('writeError')
            ->never()
            ->with(['    - test'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);
        $this->configurator->unconfigure($package);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfiguratorClass(): string
    {
        return PackageConfigurator::class;
    }

    /**
     * @throws Exception
     */
    private function arrangePackageWithConfig(string $name, array $config): Package
    {
        $package = new Package($name, '1.0.0');
        $package->setConfig($config);

        return $package;
    }
}
