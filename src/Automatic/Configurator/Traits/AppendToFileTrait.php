<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Configurator\Traits;

use Symfony\Component\Filesystem\Exception\IOException;

trait AppendToFileTrait
{
    /**
     * Appends content to an existing file.
     *
     * @see \Symfony\Component\Filesystem\Filesystem::appendToFile()
     *
     * @param string $filename The file to which to append content
     * @param string $content  The content to append
     *
     * @throws IOException If the file is not writable
     *
     * @return void
     */
    public function appendToFile(string $filename, string $content): void
    {
        if (\method_exists($this->filesystem, 'appendToFile')) {
            $this->filesystem->appendToFile($filename, $content);

            return;
        }
        // @codeCoverageIgnoreStart
        $dir = \dirname($filename);

        if (! \is_dir($dir)) {
            $this->filesystem->mkdir($dir);
        }

        if (! \is_writable($dir)) {
            throw new IOException(\sprintf('Unable to write to the "%s" directory.', $dir), 0, null, $dir);
        }

        if (false === @\file_put_contents($filename, $content, \FILE_APPEND)) {
            throw new IOException(\sprintf('Failed to write file "%s".', $filename), 0, null, $filename);
        }
        // @codeCoverageIgnoreEnd
    }
}
