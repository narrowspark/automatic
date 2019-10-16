<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher\Test;

use Narrowspark\Automatic\Prefetcher\Plugin;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @small
 */
final class PluginTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /** @var \Narrowspark\Automatic\Prefetcher\Plugin */
    private $plugin;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->arrangeComposerClasses();

        $this->plugin = new Plugin();
    }
}
