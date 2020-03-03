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

namespace Narrowspark\Automatic\Contract;

use Composer\DependencyResolver\Operation\OperationInterface;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

interface Operation
{
    /**
     * Check if operation is supported.
     */
    public function supports(OperationInterface $operation): bool;

    /**
     * Resolve package from composer operation.
     */
    public function resolve(OperationInterface $operation): PackageContract;

    /**
     * Transform the project with configurators.
     */
    public function transform(PackageContract $package): void;
}
