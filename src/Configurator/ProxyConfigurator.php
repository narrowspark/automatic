<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Viserio\Component\StaticalProxy\StaticalProxy;

class ProxyConfigurator extends AbstractClassConfigurator
{
    /**
     * {@inheritdoc}
     */
    protected static $optionName = 'proxies';

    /**
     * {@inheritdoc}
     */
    protected static $configureOutputMessage = 'Enabling the package as a Narrowspark proxy';

    /**
     * {@inheritdoc}
     */
    protected static $unconfigureOutputMessage = 'Disable the package as a Narrowspark proxy';

    /**
     * {@inheritdoc}
     */
    protected static $configFileName = 'staticalproxy';

    /**
     * {@inheritdoc}
     */
    protected static $spaceMultiplication = 16;

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        if (! \class_exists(StaticalProxy::class)) {
            return;
        }

        parent::configure($package);
    }

    /**
     * {@inheritdoc}
     */
    protected function generateFileContent(PackageContract $package, string $filePath, array $classes, string $env): string
    {
        if (\file_exists($filePath)) {
            $content = \file_get_contents($filePath);

            \unlink($filePath);
        } else {
            $content = "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'viserio' => [\n        'staticalproxy' => [\n            'aliases' => [\n            ],\n        ],\n    ],\n];";
        }

        if (\count($classes) !== 0) {
            $startPositionOfAliasesArray = \mb_strpos($content, '\'aliases\' => [') + \mb_strlen('\'aliases\' => [');
            $endPositionOfAliasesArray   = \mb_strpos($content, '            ],', $startPositionOfAliasesArray);

            $content = $this->doInsertStringBeforePosition(
                $content,
                $this->buildClassNamesContent($package, $classes, $env),
                $endPositionOfAliasesArray
            );
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     */
    protected function replaceContent($class, $content): string
    {
        $className = \explode('\\', $class);
        $className = \end($className);
        $spaces    = \str_repeat(' ', static::$spaceMultiplication);

        return \str_replace($spaces . '\'' . \str_replace('::class', '', $className) . '\' => ' . $class . ",\n", '', $content);
    }

    /**
     * Builds a array value with class names.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     * @param array                                          $classes
     * @param string                                         $env
     *
     * @return string
     */
    private function buildClassNamesContent(PackageContract $package, array $classes, string $env): string
    {
        $content = '';
        $spaces  = \str_repeat(' ', static::$spaceMultiplication);

        foreach ($classes as $class) {
            $className = \explode('\\', $class);
            $className = \end($className);

            $content .= $spaces . '\'' . \str_replace('::class', '', $className) . '\' => ' . $class . ",\n";

            $this->write(\sprintf('Enabling [%s] as a %s proxy.', $class, $env));
        }

        return $this->markData($package->getName(), $content, 16);
    }
}
