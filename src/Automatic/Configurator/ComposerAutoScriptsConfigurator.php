<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Util;

final class ComposerAutoScriptsConfigurator extends AbstractConfigurator
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

        [$this->json, $this->manipulator] = Util::getComposerJsonFileAndManipulator();
    }

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'composer-auto-scripts';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $autoScripts = $this->getComposerAutoScripts();

        $autoScripts = \array_merge($autoScripts, (array) $package->getConfig(ConfiguratorContract::TYPE, self::getName()));

        $this->manipulateAndWrite($autoScripts);
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $autoScripts = $this->getComposerAutoScripts();

        foreach (\array_keys((array) $package->getConfig(ConfiguratorContract::TYPE, self::getName())) as $cmd) {
            unset($autoScripts[$cmd]);
        }

        $this->manipulateAndWrite($autoScripts);
    }

    /**
     * Get root composer.json content and the auto-scripts section.
     */
    private function getComposerAutoScripts(): array
    {
        $jsonContents = $this->json->read();

        return $jsonContents['scripts']['auto-scripts'] ?? [];
    }

    /**
     * Manipulate the root composer.json with given auto-scripts.
     */
    private function manipulateAndWrite(array $autoScripts): void
    {
        $this->manipulator->addSubNode('scripts', 'auto-scripts', $autoScripts);

        $this->filesystem->dumpFile($this->json->getPath(), $this->manipulator->getContents());
    }
}
