<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Configurator;

use Composer\Composer;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Script\ScriptEvents;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\QuestionFactory;
use function array_flip;
use function array_keys;
use function array_merge;
use function count;
use function implode;
use function sprintf;

final class ComposerScriptsConfigurator extends AbstractConfigurator
{
    /** @var string */
    public const COMPOSER_EXTRA_KEY = 'composer-scripts';

    /** @var string */
    public const WHITELIST = 'whitelist';

    /** @var string */
    public const BLACKLIST = 'blacklist';

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
     * All allowed composer scripts.
     *
     * @var array
     */
    private $allowedComposerEvents = [
        ScriptEvents::POST_ARCHIVE_CMD,
        ScriptEvents::POST_AUTOLOAD_DUMP,
        ScriptEvents::POST_CREATE_PROJECT_CMD,
        ScriptEvents::POST_INSTALL_CMD,
        ScriptEvents::POST_ROOT_PACKAGE_INSTALL,
        ScriptEvents::POST_STATUS_CMD,
        ScriptEvents::POST_UPDATE_CMD,
        ScriptEvents::PRE_ARCHIVE_CMD,
        ScriptEvents::PRE_AUTOLOAD_DUMP,
        ScriptEvents::PRE_INSTALL_CMD,
        ScriptEvents::PRE_STATUS_CMD,
        ScriptEvents::PRE_UPDATE_CMD,
        InstallerEvents::POST_DEPENDENCIES_SOLVING,
        InstallerEvents::PRE_DEPENDENCIES_SOLVING,
        PackageEvents::POST_PACKAGE_INSTALL,
        PackageEvents::POST_PACKAGE_UNINSTALL,
        PackageEvents::POST_PACKAGE_UPDATE,
        PackageEvents::PRE_PACKAGE_INSTALL,
        PackageEvents::PRE_PACKAGE_UNINSTALL,
        PackageEvents::PRE_PACKAGE_UPDATE,
    ];

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
        return 'composer-scripts';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $packageEvents = (array) $package->getConfig(ConfiguratorContract::TYPE, self::getName());

        if (count($packageEvents) === 0) {
            return;
        }

        $composerContent = $this->json->read();

        if (isset($composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY][self::BLACKLIST])) {
            $blackList = array_flip($composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY][self::BLACKLIST]);

            if (isset($blackList[$package->getName()])) {
                $this->io->write(sprintf('Composer scripts for [%s] skipped, because it was found in the [%s]', $package->getPrettyName(), self::BLACKLIST));

                return;
            }
        }

        $allowedEvents = [];

        foreach ($this->allowedComposerEvents as $event) {
            if (isset($packageEvents[$event])) {
                $allowedEvents[$event] = (array) $packageEvents[$event];

                unset($packageEvents[$event]);
            }
        }

        $allowed = false;

        if (count($allowedEvents) !== 0) {
            if (isset($composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY][self::WHITELIST])) {
                $whiteList = array_flip($composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY][self::WHITELIST]);

                if (isset($whiteList[$package->getName()])) {
                    $allowed = true;
                }
            }

            if ($allowed === false) {
                $allowed = $this->io->askConfirmation(QuestionFactory::getPackageScriptsQuestion($package->getPrettyName()), false);
            }
        }

        if (count($packageEvents) !== 0) {
            $this->io->write(sprintf(
                '<warning>    Found not allowed composer events [%s] in [%s]</>' . "\n",
                implode(', ', array_keys($packageEvents)),
                $package->getName()
            ));
        }

        if ($allowed) {
            $this->manipulator->addSubNode(
                'extra',
                Automatic::COMPOSER_EXTRA_KEY,
                [
                    self::COMPOSER_EXTRA_KEY => array_merge(
                        $composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY] ?? [],
                        [self::WHITELIST => [$package->getName()]]
                    ),
                ]
            );

            $this->manipulateAndWrite(array_merge($this->getComposerScripts(), $allowedEvents));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $composerScripts = $this->getComposerScripts();

        foreach ((array) $package->getConfig(ConfiguratorContract::TYPE, self::getName()) as $key => $scripts) {
            foreach ((array) $scripts as $script) {
                if (isset($this->allowedComposerEvents[$key], $composerScripts[$key][$script])) {
                    unset($composerScripts[$key][$script]);
                }
            }
        }

        $composerContent = $this->json->read();

        if (isset($composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY][self::WHITELIST])) {
            $whiteList = array_flip($composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY][self::WHITELIST]);

            if (isset($whiteList[$package->getName()])) {
                unset($composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY][self::WHITELIST][$whiteList[$package->getName()]]);

                $this->manipulator->addSubNode(
                    'extra',
                    Automatic::COMPOSER_EXTRA_KEY,
                    [self::COMPOSER_EXTRA_KEY => $composerContent['extra'][Automatic::COMPOSER_EXTRA_KEY][self::COMPOSER_EXTRA_KEY]]
                );
            }
        }

        $this->manipulateAndWrite($composerScripts);
    }

    /**
     * Get root composer.json content and the auto-scripts section.
     *
     * @return array
     */
    private function getComposerScripts(): array
    {
        $jsonContents = $this->json->read();

        return $jsonContents['scripts'] ?? [];
    }

    /**
     * Manipulate the root composer.json with given scripts.
     *
     * @param array $scripts
     *
     * @return void
     */
    private function manipulateAndWrite(array $scripts): void
    {
        $this->manipulator->addMainKey('scripts', $scripts);

        $this->filesystem->dumpFile($this->json->getPath(), $this->manipulator->getContents());
    }
}
