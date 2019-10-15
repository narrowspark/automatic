<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Common;

use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;

abstract class Container implements ContainerContract
{
    /**
     * The array of closures defining each entry of the container.
     *
     * @var array<string, callable>
     */
    protected $data;

    /**
     * The array of entries once they have been instantiated.
     *
     * @var array<string, mixed>
     */
    protected $objects;

    /**
     * Array full of container implementing the \Narrowspark\Automatic\Common\Contract\Container.
     *
     * @var ContainerContract[]
     */
    protected $delegates = [];

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
        return $this->data[$id] || $this->hasInDelegate($id);
    }

    /**
     * {@inheritdoc}
     */
    public function delegate(ContainerContract $container): self
    {
        $this->delegates[] = $container;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        if (isset($this->objects[$id])) {
            return $this->objects[$id];
        }

        if (($resolved = $this->getFromDelegate($id)) !== null) {
            return $resolved;
        }

        if (! isset($this->data[$id]) && ! $this->hasInDelegate($id)) {
            throw new InvalidArgumentException(\sprintf('Identifier [%s] is not defined.', $id));
        }

        return $this->objects[$id] = ($this->data[$id] || $this->getFromDelegate($id))($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->data;
    }

    /**
     * Returns true if service is registered in one of the delegated backup containers.
     *
     * @param string $abstract
     *
     * @return bool
     */
    protected function hasInDelegate(string $abstract): bool
    {
        foreach ($this->delegates as $container) {
            if ($container->has($abstract)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempt to get a service from the stack of delegated backup containers.
     *
     * @param string $abstract
     *
     * @return mixed
     */
    protected function getFromDelegate(string $abstract)
    {
        foreach ($this->delegates as $container) {
            if ($container->has($abstract)) {
                return $container->get($abstract);
            }
        }

        return null;
    }
}
