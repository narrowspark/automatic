<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Security\Contract\Container as ContainerContract;

/**
 * @internal
 */
final class Container implements ContainerContract
{
    /**
     * The array of closures defining each entry of the container.
     *
     * @var array<string, callable>
     */
    private $data;

    /**
     * The array of entries once they have been instantiated.
     *
     * @var array<string, mixed>
     */
    private $objects;

    /**
     * Instantiate the container.
     *
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->data = [
            Composer::class => static function () use ($composer) {
                return $composer;
            },
            IOInterface::class => static function () use ($io) {
                return $io;
            },
            'vendor-dir' => static function (Container $container) {
                return \rtrim($container->get(Config::class)->get('vendor-dir'), '/');
            },
            'composer-extra' => static function (Container $container) {
                return $container->get(Composer::class)->getPackage()->getExtra();
            },
        ];
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
    public function get(string $id)
    {
        if (isset($this->objects[$id])) {
            return $this->objects[$id];
        }

        if (! isset($this->data[$id])) {
            throw new InvalidArgumentException(\sprintf('Identifier [%s] is not defined.', $id));
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
