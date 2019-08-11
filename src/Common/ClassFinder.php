<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common;

use Closure;
use Narrowspark\Automatic\Common\Contract\Resettable as ResettableContract;
use Symfony\Component\Finder\Finder;

final class ClassFinder implements ResettableContract
{
    /**
     * List of traits.
     *
     * @var array|string[]
     */
    private $traits = [];

    /**
     * List of interfaces.
     *
     * @var array|string[]
     */
    private $interfaces = [];

    /**
     * List of abstract classes.
     *
     * @var array|string[]
     */
    private $abstractClasses = [];

    /**
     * List of classes.
     *
     * @var array|string[]
     */
    private $classes = [];

    /**
     * The composer vendor dir.
     *
     * @var string
     */
    private $vendorDir;

    /**
     * All given paths for psr4 and prs0.
     *
     * @var array<string, array>
     */
    private $paths;

    /**
     * List of excludes paths.
     *
     * @var array
     */
    private $excludes = [];

    /**
     * A symfony finder filter.
     *
     * @var \Closure
     */
    private $filter;

    /**
     * Create a new ClassLoader instance.
     *
     * @param string $vendorDir
     */
    public function __construct(string $vendorDir)
    {
        $this->paths = [
            'psr0'     => [],
            'psr4'     => [],
            'classmap' => [],
        ];
        $this->vendorDir = $vendorDir;
    }

    /**
     * Returns a list with found traits.
     *
     * @return array|string[]
     */
    public function getTraits(): array
    {
        return $this->traits;
    }

    /**
     * Returns a list with found interfaces.
     *
     * @return array|string[]
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * Returns a list with found abstract classes.
     *
     * @return array|string[]
     */
    public function getAbstractClasses(): array
    {
        return $this->abstractClasses;
    }

    /**
     * Returns a list with found classes.
     *
     * @return array|string[]
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * Exclude paths from finder.
     *
     * @param array $excludes
     *
     * @return self
     */
    public function setExcludes(array $excludes): self
    {
        $this->excludes = \array_map(static function ($value) {
            return \trim($value, '/');
        }, $excludes);

        return $this;
    }

    /**
     * Set a symfony finder filter.
     *
     * @param \Closure $filter
     *
     * @return self
     */
    public function setFilter(Closure $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Set the composer.json file autoload key values.
     *
     * @param string $packageName
     * @param array  $autoload
     *
     * @return self
     */
    public function setComposerAutoload(string $packageName, array $autoload): self
    {
        $this->reset();

        if (isset($autoload['psr-0'])) {
            $this->addPsr0($packageName, (array) $autoload['psr-0']);
        }

        if (isset($autoload['psr-4'])) {
            $this->addPsr4($packageName, (array) $autoload['psr-4']);
        }

        if (isset($autoload['classmap'])) {
            $this->addClassmap($packageName, (array) $autoload['classmap']);
        }

        if (isset($autoload['exclude-from-classmap'])) {
            $this->setExcludes((array) $autoload['exclude-from-classmap']);
        }

        return $this;
    }

    /**
     * Add composer psr0 paths.
     *
     * @param string $packageName
     * @param array  $paths
     *
     * @return self
     */
    public function addPsr0(string $packageName, array $paths): self
    {
        $this->paths['psr0'][$packageName] = $paths;

        return $this;
    }

    /**
     * Add composer psr4 paths.
     *
     * @param string $packageName
     * @param array  $paths
     *
     * @return self
     */
    public function addPsr4(string $packageName, array $paths): self
    {
        $this->paths['psr4'][$packageName] = $paths;

        return $this;
    }

    /**
     * Add composer classmap paths.
     *
     * @param string $packageName
     * @param array  $paths
     *
     * @return self
     */
    public function addClassmap(string $packageName, array $paths): self
    {
        $this->paths['classmap'][$packageName] = $paths;

        return $this;
    }

    /**
     * Find all the class, traits and interface names in a given directory.
     *
     * @return self
     */
    public function find(): self
    {
        $preparedPaths = \array_unique(
            \array_merge(
                $this->getPreparedPaths($this->paths['psr0']),
                $this->getPreparedPaths($this->paths['psr4']),
                $this->getPreparedPaths($this->paths['classmap'])
            ),
            \SORT_STRING
        );

        $finder = Finder::create()
            ->files()
            ->in($preparedPaths)
            ->exclude($this->excludes)
            ->name('*.php')
            ->sortByName();

        if ($this->filter !== null) {
            $finder->filter($this->filter);
        }

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $realPath  = (string) $file->getRealPath();

            $namespace = null;
            $tokens    = \token_get_all((string) \file_get_contents($realPath));

            foreach ($tokens as $key => $token) {
                if (\is_array($token)) {
                    if ($token[0] === \T_NAMESPACE) {
                        $namespace = self::getNamespace($key + 2, $tokens);
                    } elseif ($token[0] === \T_INTERFACE) {
                        $name = self::getName($key + 2, $tokens);

                        if ($name === null) {
                            continue 2;
                        }

                        $this->interfaces[\ltrim($namespace . '\\' . $name, '\\')] = $realPath;
                    } elseif ($token[0] === \T_TRAIT) {
                        $name = self::getName($key + 2, $tokens);

                        if ($name === null) {
                            continue 2;
                        }

                        $this->traits[\ltrim($namespace . '\\' . $name, '\\')] = $realPath;
                    } elseif ($token[0] === \T_ABSTRACT) {
                        $name = self::getName($key + 4, $tokens);

                        if ($name === null) {
                            continue 2;
                        }

                        $this->abstractClasses[\ltrim($namespace . '\\' . $name, '\\')] = $realPath;

                        continue 2;
                    } elseif ($token[0] === \T_CLASS) {
                        $name = self::getName($key + 2, $tokens);

                        if ($name === null) {
                            continue 2;
                        }

                        $this->classes[\ltrim($namespace . '\\' . $name, '\\')] = $realPath;
                    }
                }
            }

            unset($tokens);
            // PHP 7 memory manager will not release after token_get_all(), see https://bugs.php.net/70098
            \gc_mem_caches();
        }

        return $this;
    }

    /**
     * Returns a array of all found classes, interface and traits.
     *
     * @return array|array<string, string>
     */
    public function getAll(): array
    {
        return \array_merge(
            $this->interfaces,
            $this->traits,
            $this->abstractClasses,
            $this->classes
        );
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->interfaces      = [];
        $this->traits          = [];
        $this->abstractClasses = [];
        $this->classes         = [];
        $this->paths           = [
            'psr0'     => [],
            'psr4'     => [],
            'classmap' => [],
        ];
    }

    /**
     * Find the namespace in the tokens starting at a given key.
     *
     * @param int   $key
     * @param array $tokens
     *
     * @return null|string
     */
    private static function getNamespace(int $key, array $tokens): ?string
    {
        $namespace  = null;
        $tokenCount = \count($tokens);

        for ($i = $key; $i < $tokenCount; $i++) {
            if (self::isPartOfNamespace($tokens[$i])) {
                $namespace .= $tokens[$i][1];
            } elseif ($tokens[$i] === ';') {
                return $namespace;
            }
        }

        return null;
    }

    /**
     * Determine if the given token is part of the namespace.
     *
     * @param array|string $token
     *
     * @return bool
     */
    private static function isPartOfNamespace($token): bool
    {
        return \is_array($token) && ($token[0] === \T_STRING || $token[0] === \T_NS_SEPARATOR);
    }

    /**
     * Find the name in the tokens starting at a given key.
     *
     * @param int   $key
     * @param array $tokens
     *
     * @return null|string
     */
    private static function getName($key, array $tokens): ?string
    {
        $class      = null;
        $tokenCount = \count($tokens);

        for ($i = $key; $i < $tokenCount; $i++) {
            if (\is_array($tokens[$i])) {
                if ($tokens[$i][0] === \T_STRING) {
                    $class .= $tokens[$i][1];
                } elseif ($tokens[$i][0] === \T_WHITESPACE) {
                    return $class;
                }
            }
        }

        return null;
    }

    /**
     * Prepare psr0 and psr4 to full vendor package paths.
     *
     * @param array $paths
     *
     * @return array
     */
    private function getPreparedPaths(array $paths): array
    {
        $fullPaths = [];

        foreach ($paths as $name => $path) {
            if (\is_array($path)) {
                foreach ($path as $p) {
                    $fullPaths[] = \rtrim($this->vendorDir . \DIRECTORY_SEPARATOR . $name . \DIRECTORY_SEPARATOR . $p, '/');
                }
            } else {
                $fullPaths[] = \rtrim($this->vendorDir . \DIRECTORY_SEPARATOR . $name . \DIRECTORY_SEPARATOR . $path, '/');
            }
        }

        return $fullPaths;
    }
}
