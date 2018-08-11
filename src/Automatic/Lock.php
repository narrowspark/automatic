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
     * @param string      $mainKey
     * @param null|string $name
     *
     * @return bool
     */
    public function has(string $mainKey, ?string $name = null): bool
    {
        $mainCheck = \array_key_exists($mainKey, $this->lock);

        if ($name === null) {
            return $mainCheck;
        }

        if ($mainCheck === true && \is_array($this->lock[$mainKey])) {
            return \array_key_exists($name, $this->lock[$mainKey]);
        }

        return false;
    }

    /**
     * Add a value to the lock file.
     *
     * @param string            $mainKey
     * @param null|array|string $data
     *
     * @return void
     */
    public function add(string $mainKey, $data): void
    {
        $this->lock[$mainKey] = $data;
    }

    /**
     * Add sub value to the lock file.
     *
     * @param string            $mainKey
     * @param string            $name
     * @param null|array|string $data
     *
     * @return void
     */
    public function addSub(string $mainKey, string $name, $data): void
    {
        if (! \array_key_exists($mainKey, $this->lock)) {
            $this->lock[$mainKey] = [];
        }

        $this->lock[$mainKey][$name] = $data;
    }

    /**
     * Get package data found in the lock file.
     *
     * @param string      $mainKey
     * @param null|string $name
     *
     * @return null|array|string
     */
    public function get(string $mainKey, ?string $name = null)
    {
        if (\array_key_exists($mainKey, $this->lock)) {
            if ($name === null) {
                return $this->lock[$mainKey];
            }

            if (\is_array($this->lock[$mainKey]) && \array_key_exists($name, $this->lock[$mainKey])) {
                return $this->lock[$mainKey][$name];
            }
        }

        return null;
    }

    /**
     * Remove a package from lock file.
     *
     * @param string      $mainKey
     * @param null|string $name
     */
    public function remove(string $mainKey, ?string $name = null): void
    {
        if ($name === null) {
            unset($this->lock[$mainKey]);
        }

        if (\array_key_exists($mainKey, $this->lock)) {
            unset($this->lock[$mainKey][$name]);
        }
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
