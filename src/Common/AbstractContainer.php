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

namespace Narrowspark\Automatic\Common;

use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;

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
     * @param callable[] $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    final public function set(string $id, callable $callback): void
    {
        $this->data[$id] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    final public function has(string $id): bool
    {
        return \array_key_exists($id, $this->data);
    }

    /**
     * {@inheritdoc}
     */
    final public function get(string $id)
    {
        if (\array_key_exists($id, $this->objects)) {
            return $this->objects[$id];
        }

        if (! \array_key_exists($id, $this->data)) {
            throw new InvalidArgumentException(\sprintf('Identifier [%s] is not defined.', $id));
        }

        return $this->objects[$id] = $this->data[$id]($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return callable[]
     */
    final public function getAll(): array
    {
        return $this->data;
    }
}
