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

namespace Narrowspark\Automatic\Common;

use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use function array_key_exists;
use function sprintf;

abstract class AbstractContainer implements ContainerContract
{
    /**
     * The array of closures defining each entry of the container.
     *
     * @var array<string, callable>
     */
    protected $data = [];

    /**
     * The array of entries once they have been instantiated.
     *
     * @var array<string, mixed>
     */
    protected $objects = [];

    /**
     * Instantiate the container.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $id, callable $callback): void
    {
        $this->data[$id] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->objects)) {
            return $this->objects[$id];
        }

        if (! array_key_exists($id, $this->data)) {
            throw new InvalidArgumentException(sprintf('Identifier [%s] is not defined.', $id));
        }

        return $this->objects[$id] = $this->data[$id]($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->data;
    }
}
