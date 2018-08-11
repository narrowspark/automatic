<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Contract;

interface Container
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id identifier of the entry to look for
     *
     * @return mixed
     */
    public function get(string $id);

    /**
     * Returns all container entries.
     *
     * @return array
     */
    public function getAll(): array;
}
