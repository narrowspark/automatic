<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

use Narrowspark\Discovery\Common\Contract\Package as PackageContract;

final class ServiceProviderConfigurator extends AbstractClassConfigurator
{
    /**
     * {@inheritdoc}
     */
    protected static $optionName = 'providers';

    /**
     * {@inheritdoc}
     */
    protected static $configureOutputMessage = 'Enabling the package as a Narrowspark service provider';

    /**
     * {@inheritdoc}
     */
    protected static $unconfigureOutputMessage = 'Disable the package as a Narrowspark service provider';

    /**
     * {@inheritdoc}
     */
    protected static $configFileName = 'serviceproviders';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'providers';
    }

    /**
     * {@inheritdoc}
     */
    protected function generateFileContent(PackageContract $package, string $filePath, array $classes, string $type): string
    {
        if (\file_exists($filePath)) {
            $content = \file_get_contents($filePath);

            \unlink($filePath);
        } else {
            $content = "<?php\ndeclare(strict_types=1);\n\nreturn [\n];\n";
        }

        if (\count($classes) !== 0) {
            $content = $this->doInsertStringBeforePosition(
                $content,
                $this->buildClassNamesContent($package, $classes, $type),
                \mb_strpos($content, '];')
            );
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     */
    protected function replaceContent($class, $content): string
    {
        return \str_replace('    ' . $class . ',', '', $content);
    }

    /**
     * Builds a array value with class names.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     * @param array                                          $classes
     * @param string                                         $type
     *
     * @return string
     */
    private function buildClassNamesContent(PackageContract $package, array $classes, string $type): string
    {
        $content = '';

        foreach ($classes as $class) {
            $content .= '    ' . $class . ",\n";

            $this->write(\sprintf('Enabling [%s] as a %s service provider.', $class, $type));
        }

        return $this->markData($package->getName(), $content);
    }
}
