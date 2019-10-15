<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Common\Traits;

use Composer\Composer;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;

trait GetComposerVersionTrait
{
    /**
     * Get the composer version.
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     *
     * @return string
     */
    private static function getComposerVersion(): string
    {
        \preg_match('/\d+.\d+.\d+/m', Composer::VERSION, $matches);

        if ($matches !== null) {
            return $matches[0];
        }

        \preg_match('/\d+.\d+.\d+/m', Composer::BRANCH_ALIAS_VERSION, $matches);

        if ($matches !== null) {
            return $matches[0];
        }

        throw new RuntimeException('No composer version found.');
    }
}
