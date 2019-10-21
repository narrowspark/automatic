<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Common\Contract;

interface Container
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id identifier of the entry to look for
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException if no entry is found
     *
     * @return mixed
     */
    public function get(string $id);

    /**
     * Set a new entry to the container.
     *
     * @param string   $id
     * @param callable $callback
     *
     * @return void
     */
    public function set(string $id, callable $callback): void;

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id identifier of the entry to look for
     *
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * Returns all container entries.
     *
     * @return array
     */
    public function getAll(): array;
}
