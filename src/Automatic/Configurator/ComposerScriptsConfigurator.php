<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Util;

final class ComposerScriptsConfigurator extends AbstractConfigurator
{
    /**
     * A json instance.
     *
     * @var \Composer\Json\JsonFile
     */
    private $json;

    /**
     * A json manipulator instance.
     *
     * @var \Composer\Json\JsonManipulator
     */
    private $manipulator;

    /**
     * {@inheritdoc}
     */
    public function __construct(Composer $composer, IOInterface $io, array $options = [])
    {
        parent::__construct($composer, $io, $options);

        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        $this->json        = $json;
        $this->manipulator = $manipulator;
    }

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'composer-scripts';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $autoScripts = $this->getComposerContentAndAutoScripts();

        $autoScripts = \array_merge($autoScripts, $package->getConfig('composer-scripts'));

        $this->manipulateAndWrite($autoScripts);
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $autoScripts = $this->getComposerContentAndAutoScripts();

        foreach (\array_keys($package->getConfig('composer-scripts')) as $cmd) {
            unset($autoScripts[$cmd]);
        }

        $this->manipulateAndWrite($autoScripts);
    }

    /**
     * Get root composer.json content and the auto-scripts section.
     *
     * @return array
     */
    private function getComposerContentAndAutoScripts(): array
    {
        $jsonContents = $this->json->read();

        return $jsonContents['scripts']['auto-scripts'] ?? [];
    }

    /**
     * Manipulate the root composer.json with given auto-scripts.
     *
     * @param array $autoScripts
     */
    private function manipulateAndWrite(array $autoScripts): void
    {
        $this->manipulator->addSubNode('scripts', 'auto-scripts', $autoScripts);

        \file_put_contents($this->json->getPath(), $this->manipulator->getContents());
    }
}
