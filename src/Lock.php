<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Composer\Json\JsonFile;
use Composer\Package\Locker;

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
            $this->lock = $this->read();
        }

        $this->add('_content-hash', Locker::getContentHash(\file_get_contents($this->json->getPath())));
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
     * @param string       $name
     * @param array|string $data
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
        return $this->json->read() ?? [];
    }
}
