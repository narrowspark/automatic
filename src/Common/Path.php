<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common;

final class Path
{
    /**
     * Path to the working directory.
     *
     * @var string
     */
    private $workingDirectory;

    /**
     * Create a new Path instance.
     *
     * @param string $workingDirectory
     */
    public function __construct(string $workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;
    }

    /**
     * Get the working directory path.
     *
     * @return string
     */
    public function getWorkingDir(): string
    {
        return $this->workingDirectory;
    }

    /**
     * @param string $absolutePath
     *
     * @return string
     */
    public function relativize(string $absolutePath): string
    {
        $relativePath = \str_replace($this->workingDirectory, '.', $absolutePath);

        return \is_dir($absolutePath) ? \rtrim($relativePath, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR : $relativePath;
    }

    /**
     * @param array $parts
     *
     * @return string
     */
    public function concatenate(array $parts): string
    {
        $first = \array_shift($parts);

        return \array_reduce($parts, function (string $initial, string $next): string {
            return \rtrim($initial, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . \ltrim($next, \DIRECTORY_SEPARATOR);
        }, $first);
    }
}
