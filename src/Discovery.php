<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Locker;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;

class Discovery implements PluginInterface, EventSubscriberInterface
{
    /**
     * A composer instance.
     *
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A lock instance.
     *
     * @var \Narrowspark\Discovery\Lock
     */
    private $lock;

    /**
     * A configurator instance.
     *
     * @var \Narrowspark\Discovery\Configurator
     */
    private $configurator;

    /**
     * A array of project options.
     *
     * @var array
     */
    private $projectOptions;

    /**
     * The composer vendor path.
     *
     * @var string
     */
    private $vendorDir;

    /**
     * Check if composer.lock should be updated.
     *
     * @var bool
     */
    private $shouldUpdateComposerLock = false;

    /**
     * Get the narrowspark.lock file path.
     *
     * @return string
     */
    public static function getNarrowsparkLockFile(): string
    {
        return \str_replace(
            'composer.json',
            'narrowspark.lock',
            Factory::getComposerFile()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io       = $io;

        $this->projectOptions = $this->initProjectOptions();
        $this->vendorDir      = $composer->getConfig()->get('vendor-dir');
        $this->configurator   = new Configurator($this->composer, $this->io, $this->initOptions());
        $this->lock           = new Lock(self::getNarrowsparkLockFile());

        $this->lock->add('_readme', [
            'This file locks the narrowspark information of your project to a known state',
            'This file is @generated automatically',
        ]);
        $this->lock->add('content-hash', \md5((string) \random_int(100, 999)));
    }

    /**
     * Get the Configurator instance.
     *
     * @return \Narrowspark\Discovery\Configurator
     */
    public function getConfigurator(): Configurator
    {
        return $this->configurator;
    }

    /**
     * Get the Lock instance.
     *
     * @return \Narrowspark\Discovery\Lock
     */
    public function getLock(): Lock
    {
        return $this->lock;
    }

    /**
     * Execute on composer create project event.
     *
     * @param \Composer\Script\Event $event
     *
     * @throws \Exception
     */
    public function configureProject(Event $event): void
    {
        $json = new JsonFile(Factory::getComposerFile());

        $manipulator = new JsonManipulator(\file_get_contents($json->getPath()));

        // new projects are most of the time proprietary
        $manipulator->addMainKey('license', 'proprietary');

        // 'name' and 'description' are only required for public packages
        $manipulator->removeProperty('name');
        $manipulator->removeProperty('description');

        \file_put_contents($json->getPath(), $manipulator->getContents());

        $this->updateComposerLock();
    }

    /**
     * Execute on composer install event.
     *
     * @param \Composer\Script\Event $event
     *
     * @return void
     */
    public function install(Event $event): void
    {
        $this->update($event);
    }

    /**
     * Execute on composer uninstall event.
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return void
     */
    public function uninstall(PackageEvent $event): void
    {
        $name = $event->getName();

        if (! $this->lock->has($name)) {
            return;
        }

        $this->io->writeError(\sprintf('  - Unconfiguring %s', $name));

        $package = new Package($name, $this->vendorDir, (array) $this->lock->get($name));

        $this->configurator->unconfigure($package);

        $this->lock->remove($name);
        $this->lock->write();
    }

    /**
     * Execute on composer update event.
     *
     * @param \Composer\Script\Event $event
     * @param array                  $operations
     *
     * @return void
     */
    public function update(Event $event, array $operations = []): void
    {
        if (! \file_exists(getcwd() . '/.env') && \file_exists(getcwd() . '/.env.dist')) {
            \copy(getcwd() . '/.env.dist', getcwd() . '/.env');
        }
    }

    /**
     * Execute on composer dump event.
     *
     * @param \Composer\Script\Event $event
     *
     * @throws \Exception
     *
     * @return void
     */
    public function dump(Event $event): void
    {
        $this->io->writeError(\sprintf('<info>%s operations</info>', \ucwords('narrowspark')));

        $allowInstall = false;

        foreach ($this->getInstalledPackagesExtraConfiguration() as $name => $packageConfig) {
            if (\array_key_exists($name, $this->projectOptions['dont-discover']['package'])) {
                $this->io->write(\sprintf('<info>Package "%s" was ignored.</info>', $name));

                continue;
            }

            if ($allowInstall === false && $this->projectOptions['allow_auto_install'] === false) {
                $answer = $this->io->askAndValidate(
                    $this->getPackageQuestion($packageConfig),
                    [$this, 'validateAnswerValue'],
                    null,
                    'n'
                );

                if ($answer === 'n') {
                    continue;
                } elseif ($answer === 'a') {
                    $allowInstall = true;
                } elseif ($answer === 'p') {
                    $allowInstall = true;

                    $this->manipulateComposerJsonWithAllowAutoInstall();

                    $this->shouldUpdateComposerLock = true;
                }
            }

            $package = new Package($name, $this->vendorDir, $packageConfig);

            $this->io->writeError(\sprintf('  - Configuring %s', $name));

            $this->configurator->configure($package);
        }

        $this->lock->write();

        if ($this->shouldUpdateComposerLock) {
            $this->updateComposerLock();
        }
    }

    /**
     * @param \Composer\Script\Event $event
     *
     * @return void
     */
    public function executeAutoScripts(Event $event): void
    {
        $event->stopPropagation();

        // force reloading scripts as we might have added and removed during this run
        $json = new JsonFile(Factory::getComposerFile());

        $jsonContents = $json->read();

        $executor = new ScriptExecutor($this->composer, $this->io, $this->projectOptions, new ProcessExecutor());

        foreach ($jsonContents['scripts']['auto-scripts'] as $cmd => $type) {
            $executor->execute($type, $cmd);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'auto-scripts'                        => 'executeAutoScripts',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'uninstall',
            ScriptEvents::POST_CREATE_PROJECT_CMD => 'configureProject',
            ScriptEvents::POST_INSTALL_CMD        => 'install',
            ScriptEvents::POST_UPDATE_CMD         => 'update',
            ScriptEvents::POST_AUTOLOAD_DUMP      => 'dump',
        ];
    }

    /**
     * Validate given input answer.
     *
     * @param null|string $value
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function validateAnswerValue(?string $value): string
    {
        if ($value === null) {
            return 'n';
        }

        $value = \mb_strtolower($value[0]);

        if (! \in_array($value, ['y', 'n', 'a', 'p'], true)) {
            throw new \InvalidArgumentException('Invalid choice');
        }

        return $value;
    }

    /**
     * Update composer.lock file the composer.json do change.
     *
     * @throws \Exception
     *
     * @return void
     */
    private function updateComposerLock(): void
    {
        $composerLockPath = \mb_substr(Factory::getComposerFile(), 0, -4) . 'lock';
        $composerJson     = \file_get_contents(Factory::getComposerFile());

        $lockFile = new JsonFile($composerLockPath, null, $this->io);
        $locker   = new Locker(
            $this->io,
            $lockFile,
            $this->composer->getRepositoryManager(),
            $this->composer->getInstallationManager(),
            $composerJson
        );

        $lockData                 = $locker->getLockData();
        $lockData['content-hash'] = Locker::getContentHash($composerJson);

        $lockFile->write($lockData);
    }

    /**
     * Get found narrowspark configurations from installed packages.
     *
     * @throws \Exception
     *
     * @return array
     */
    private function getInstalledPackagesExtraConfiguration(): array
    {
        $composerInstalledFilePath    = $this->vendorDir . '/composer/installed.json';
        $composerInstalledFileContent = \json_decode(\file_get_contents($composerInstalledFilePath), true);

        foreach ($composerInstalledFileContent as $package) {
            if (isset($package['extra']['narrowspark'])) {
                $this->lock->add(
                    $package['name'],
                    \array_merge(
                        [
                            'version' => $package['version'],
                            'url'     => $package['support']['source'] ?? ($package['homepage'] ?? 'url not found'),
                        ],
                        $package['extra']['narrowspark']
                    )
                );
            }
        }

        $this->lock->write();

        return $this->lock->read();
    }

    /**
     * Add extra option "allow_auto_install" to composer.json.
     *
     * @return void
     */
    private function manipulateComposerJsonWithAllowAutoInstall(): void
    {
        $json        = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(\file_get_contents($json->getPath()));
        $manipulator->addSubNode('extra', 'narrowspark.allow_auto_install', true);

        \file_put_contents($json->getPath(), $manipulator->getContents());
    }

    /**
     * @param array $packageConfig
     *
     * @return string
     */
    private function getPackageQuestion(array $packageConfig): string
    {
        return \sprintf('    Review the package at %s.
    Do you want to execute this package?
    [<comment>y</comment>] Yes
    [<comment>n</comment>] No
    [<comment>a</comment>] Yes for all packages, only for the current installation session
    [<comment>p</comment>] Yes permanently, never ask again for this project
    (defaults to <comment>n</comment>): ', $packageConfig['url']);
    }

    /**
     * Init default options.
     *
     * @return array
     */
    private function initProjectOptions(): array
    {
        $extra       = $this->composer->getPackage()->getExtra();
        $rootOptions = $extra['narrowspark'] ?? [];

        return \array_merge(
            [
                'allow_auto_install' => false,
                'dont-discover'      => [
                    'package' => [],
                ],
            ],
            $rootOptions
        );
    }

    /**
     * Init default extra options.
     *
     * @return array
     */
    private function initOptions(): array
    {
        return \array_merge(
            [
                'app-dir'       => 'app',
                'config-dir'    => 'config',
                'public-dir'    => 'public',
                'resources-dir' => 'resources',
                'root-dir'      => '',
                'routes-dir'    => 'routes',
                'tests-dir'     => 'tests',
                'storage-dir'   => 'storage',
            ],
            $this->composer->getPackage()->getExtra()
        );
    }
}
