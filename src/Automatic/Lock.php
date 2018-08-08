<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\Json\JsonFile;

class Lock
{
    /**
     * Instance of JsonFile.
     *
     * @var \Composer\Json\JsonFile
     */
    private $json;

    /**
     * Array of all lock file data.
     *
     * @var array
     */
    private $lock = [];

    /**
     * Create a new Lock instance.
     *
     * @param string $lockFile
     */
    public function __construct(string $lockFile)
    {
        $this->json = new JsonFile($lockFile);

        if ($this->json->exists()) {
            $this->read();
        }
    }

    /**
     * Check if key exists in lock file.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->lock);
    }

    /**
     * Add a value to the lock file.
     *
     * @param string            $name
     * @param null|array|string $data
     *
     * @return void
     */
    public function add(string $name, $data): void
    {
        $this->lock[$name] = $data;
    }

    /**
     * Get package data found in the lock file.
     *
     * @param string $name
     *
     * @return null|array|string
     */
    public function get(string $name)
    {
        return $this->lock[$name] ?? null;
    }

    /**
     * Remove a package from lock file.
     *
     * @param string $name
     */
    public function remove(string $name): void
    {
        unset($this->lock[$name]);
    }

    /**
     * Write a lock file.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function write(): void
    {
        \ksort($this->lock);

        $this->json->write($this->lock);
    }

    /**
     * Read the lock file.
     *
     * @return array
     */
    public function read(): array
    {
        if (\count($this->lock) === 0) {
            $this->lock = $this->json->read();
        }

        return $this->lock;
    }

    /**
     * Clear the lock.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->lock = [];
    }
}
