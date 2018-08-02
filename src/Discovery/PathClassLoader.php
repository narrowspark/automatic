<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Symfony\Component\Finder\Finder;

final class PathClassLoader
{
    /**
     * List of traits.
     *
     * @var array
     */
    private $traits = [];

    /**
     * List of interfaces.
     *
     * @var array
     */
    private $interfaces = [];

    /**
     * List of abstract classes.
     *
     * @var array
     */
    private $abstractClasses = [];

    /**
     * List of classes.
     *
     * @var array
     */
    private $classes = [];

    /**
     * Find all the class, traits and interface names in a given directory.
     *
     * @param array|string $dirs
     *
     * @return void
     */
    public function find($dirs): void
    {
        $finder = Finder::create()
            ->files()
            ->sortByName()
            ->in($dirs)
            ->name('*.php');

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
                        $this->interfaces[$realPath] = \ltrim($namespace . '\\' . self::getName($key + 2, $tokens), '\\');
                    } elseif ($token[0] === \T_TRAIT) {
                        $this->traits[$realPath] = \ltrim($namespace . '\\' . self::getName($key + 2, $tokens), '\\');
                    } elseif ($token[0] === \T_ABSTRACT) {
                        $this->abstractClasses[$realPath] = \ltrim($namespace . '\\' . self::getName($key + 4, $tokens), '\\');

                        continue 2;
                    } elseif ($token[0] === \T_CLASS) {
                        $this->classes[$realPath] = \ltrim($namespace . '\\' . self::getName($key + 2, $tokens), '\\');
                    }
                }
            }

            // PHP 7 memory manager will not release after token_get_all(), see https://bugs.php.net/70098
            \gc_mem_caches();
        }
    }

    /**
     * Returns a list with found abstract classes.
     *
     * @return array
     */
    public function getAbstractClasses(): array
    {
        return $this->abstractClasses;
    }

    /**
     * Returns a list with found classes.
     *
     * @return array
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * Returns a list with found interfaces.
     *
     * @return array
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * Returns a list with found traits.
     *
     * @return array
     */
    public function getTraits(): array
    {
        return $this->traits;
    }

    /**
     * Includes all found abstract classes, classes, traits and interfaces.
     *
     * @return void
     */
    public function load(): void
    {
        $includes = \array_merge(
            [],
            \array_keys($this->interfaces),
            \array_keys($this->traits),
            \array_keys($this->abstractClasses),
            \array_keys($this->classes)
        );

        foreach ($includes as $path) {
            includeFile($path);
        }
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
}

/**
 * Scope isolated include.
 *
 * Prevents access to $this/self from included files.
 *
 * @param mixed $file
 */
function includeFile($file)
{
    include $file;
}
