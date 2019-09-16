<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\PackageConfigurator;
use Narrowspark\Automatic\Test\Fixture\MockConfigurator;

/**
 * @internal
 *
 * @small
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
     * @param string $name
     * @param array  $config
     *
     * @throws \Exception
     *
     * @return Package
     */
    private function arrangePackageWithConfig(string $name, array $config): Package
    {
        $package = new Package($name, '1.0.0');
        $package->setConfig($config);

        return $package;
    }
}
