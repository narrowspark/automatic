<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Symfony\Component\Finder\Finder;

final class ClassFinder
{
    /**
     * Find all the class, traits and interface names in a given directory.
     *
     * @param string $directory
     *
     * @return array
     */
    public static function find(string $directory): array
    {
        $classes = [];
        $finder  = Finder::create()->files()->in($directory)->name('*.php');

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            $class    = self::findClassOrTraitOrInterface($realPath);

            // PHP 7 memory manager will not release after token_get_all(), see https://bugs.php.net/70098
            \gc_mem_caches();

            if ($class === null) {
                continue;
            }

            require $realPath;

            $classes[$realPath] = $class;
        }

        \asort($classes);

        return \array_filter($classes);
    }

    /**
     * Extract the class name or trait name from the file at the given path.
     *
     * @param string $path
     *
     * @return null|string
     */
    private static function findClassOrTraitOrInterface(string $path): ?string
    {
        $namespace = null;
        $tokens    = \token_get_all(\file_get_contents($path));

        foreach ($tokens as $key => $token) {
            if (\is_array($token)) {
                if (self::tokenIsNamespace($token)) {
                    $namespace = self::getNamespace($key + 2, $tokens);
                } elseif (self::tokenIsClassOrTraitOrInterface($token)) {
                    return \ltrim($namespace . '\\' . self::getClass($key + 2, $tokens), '\\');
                }
            }
        }

        return null;
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
     * Find the class in the tokens starting at a given key.
     *
     * @param int   $key
     * @param array $tokens
     *
     * @return null|string
     */
    private static function getClass($key, array $tokens): ?string
    {
        $class      = null;
        $tokenCount = \count($tokens);

        for ($i = $key; $i < $tokenCount; $i++) {
            if (self::isPartOfClass($tokens[$i])) {
                $class .= $tokens[$i][1];
            } elseif (self::isWhitespace($tokens[$i])) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Determine if the given token is a namespace keyword.
     *
     * @param array $token
     *
     * @return bool
     */
    private static function tokenIsNamespace(array $token): bool
    {
        return $token[0] === \T_NAMESPACE;
    }

    /**
     * Determine if the given token is part of the class.
     *
     * @param array|string $token
     *
     * @return bool
     */
    private static function isPartOfClass($token): bool
    {
        return \is_array($token) && $token[0] === \T_STRING;
    }

    /**
     * Determine if the given token is a class or interface keyword.
     *
     * @param array $token
     *
     * @return bool
     */
    private static function tokenIsClassOrTraitOrInterface(array $token): bool
    {
        return $token[0] === \T_CLASS || $token[0] === \T_INTERFACE || $token[0] === \T_TRAIT;
    }

    /**
     * Determine if the given token is whitespace.
     *
     * @param array|string $token
     *
     * @return bool
     */
    private static function isWhitespace($token): bool
    {
        return \is_array($token) && $token[0] === \T_WHITESPACE;
    }
}
