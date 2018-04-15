<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Configurator;

use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Configurator;
use Narrowspark\Discovery\Configurator\Traits\GetSortedClassesTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

class GetSortedClassesTraitTest extends MockeryTestCase
{
    use GetSortedClassesTrait;

    public function testGetSortedClasses(): void
    {
        $package = $this->mock(PackageContract::class);
        $package->shouldReceive('getConfiguratorOptions')
            ->once()
            ->with('providers')
            ->andReturn([
                self::class         => ['global'],
                Configurator::class => ['global', 'test'],
            ]);

        $array = $this->getSortedClasses($package, 'providers');

        self::assertEquals(
            [
                'global' => [
                    self::class . '::class'         => '\\' . self::class . '::class',
                    Configurator::class . '::class' => '\\' . Configurator::class . '::class',
                ],
                'test' => [
                    Configurator::class . '::class' => '\\' . Configurator::class . '::class',
                ],
            ],
            $array
        );
    }
}
