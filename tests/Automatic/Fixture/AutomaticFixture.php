<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Fixture;

use Narrowspark\Automatic\Automatic;

class AutomaticFixture extends Automatic
{
    public function setContainer($container): void
    {
        $this->container = $container;
    }
}
