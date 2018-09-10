<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Configurator;

use Composer\Composer;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Script\ScriptEvents;
use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\QuestionFactory;

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
     * A automatic lock instance.
     *
     * @var \Narrowspark\Automatic\Lock
     */
    private $lock;

    /**
     * {@inheritdoc}
     */
    public function __construct(Composer $composer, IOInterface $io, array $options = [])
    {
        parent::__construct($composer, $io, $options);

        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        $this->json        = $json;
        $this->manipulator = $manipulator;
        $this->lock        = new Lock(Util::getAutomaticLockFile());
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

        if (\count($packageEvents) === 0) {
            return;
        }

        $allowedEvents = [];

        foreach ($this->allowedComposerEvents as $event) {
            if (isset($packageEvents[$event])) {
                $allowedEvents[$event] = (array) $packageEvents[$event];

                unset($packageEvents[$event]);
            }
        }

        $allowed = false;

        if (\count($allowedEvents) !== 0) {
            if (\is_array($this->lock->get(self::getName(), $package->getName()))) {
                $allowed = true;
            } else {
                $allowed = $this->io->askConfirmation(QuestionFactory::getPackageScriptsQuestion($package->getPrettyName()), false);
            }
        }

        if (\count($packageEvents) !== 0) {
            $this->io->write(\sprintf(
                '<warning>    Found not allowed composer events [%s] in [%s]</>' . \PHP_EOL,
                \implode(', ', \array_keys($packageEvents)),
                $package->getName()
            ));
        }

        if ($allowed) {
            $this->lock->addSub(self::getName(), $package->getName(), $allowedEvents);

            $this->manipulateAndWrite(\array_merge($this->getComposerScripts(), $allowedEvents));
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

        $this->manipulateAndWrite($composerScripts);
        $this->lock->remove(self::getName(), $package->getName());
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

        \file_put_contents($this->json->getPath(), $this->manipulator->getContents());
    }
}
