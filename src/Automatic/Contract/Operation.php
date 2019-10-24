<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Contract;

use Composer\DependencyResolver\Operation\OperationInterface;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

interface Operation
{
    /**
     * Check if operation is supported.
     *
     * @param \Composer\DependencyResolver\Operation\OperationInterface $operation
     *
     * @return bool
     */
    public function supports(OperationInterface $operation): bool;

    /**
     * Resolve package from composer operation.
     *
     * @param \Composer\DependencyResolver\Operation\OperationInterface $operation
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function resolve(OperationInterface $operation): PackageContract;

    /**
     * Transform the project with configurators.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     *
     * @return void
     */
    public function transform(PackageContract $package): void;
}
