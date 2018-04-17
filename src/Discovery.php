<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
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
use Narrowspark\Discovery\Common\Contract\Discovery as DiscoveryContract;
use Narrowspark\Discovery\Common\Exception\InvalidArgumentException;

class Discovery implements PluginInterface, EventSubscriberInterface, DiscoveryContract
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
     * The composer operations.
     *
     * @var array
     */
    private $operations = [];

    /**
     * Get the discovery.lock file path.
     *
     * @return string
     */
    public static function getDiscoveryLockFile(): string
    {
        return \str_replace(
            'composer.json',
            'discovery.lock',
            Factory::getComposerFile()
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'auto-scripts'                        => 'executeAutoScripts',
            PackageEvents::POST_PACKAGE_INSTALL   => 'record',
            PackageEvents::POST_PACKAGE_UPDATE    => 'record',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'record',
            ScriptEvents::POST_INSTALL_CMD        => 'onPostInstall',
            ScriptEvents::POST_UPDATE_CMD         => 'onPostUpdate',
            ScriptEvents::POST_CREATE_PROJECT_CMD => 'onPostCreateProject',
        ];
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
        $this->configurator   = new Configurator($this->composer, $this->io, $this->projectOptions);
        $this->lock           = new Lock(self::getDiscoveryLockFile());

        $this->lock->add('_readme', [
            'This file locks the narrowspark information of your project to a known state',
            'This file is @generated automatically',
        ]);
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
     * Records composer operations.
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return void
     */
    public function record(PackageEvent $event): void
    {
        if (! $this->shouldRecordOperation($event)) {
            return;
        }

        $this->operations[] = $event->getOperation();
    }

    /**
     * Execute on composer create project event.
     *
     * @param \Composer\Script\Event $event
     *
     * @throws \Exception
     */
    public function onPostCreateProject(Event $event): void
    {
        $answer = $this->io->askAndValidate(
            self::getProjectQuestion(),
            [$this, 'validateProjectQuestionAnswerValue'],
            null,
            'f'
        );
        $mapping = [
            'f' => self::FULL_PROJECT,
            'c' => self::CONSOLE_PROJECT,
            'h' => self::HTTP_PROJECT,
        ];

        $json        = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(\file_get_contents($json->getPath()));
        // new projects are most of the time proprietary
        $manipulator->addMainKey('license', 'proprietary');

        // 'name' and 'description' are only required for public packages
        $manipulator->removeProperty('name');
        $manipulator->removeProperty('description');

        foreach ($this->projectOptions as $key => $value) {
            if ($key !== 'narrowspark') {
                $manipulator->addSubNode('extra', $key, $value);
            }
        }

        \file_put_contents($json->getPath(), $manipulator->getContents());

        $this->lock->add('project-type', $mapping[$answer]);
        $this->lock->write();

        $this->updateComposerLock();
    }

    /**
     * Execute on composer install event.
     *
     * @param \Composer\Script\Event $event
     *
     * @throws \Exception
     *
     * @return void
     */
    public function onPostInstall(Event $event): void
    {
        $this->onPostUpdate($event);
    }

    /**
     * Execute on composer update event.
     *
     * @param \Composer\Script\Event $event
     * @param array                  $operations
     *
     * @throws \Exception
     *
     * @return void
     */
    public function onPostUpdate(Event $event, array $operations = []): void
    {
        if (\count($operations) !== 0) {
            $this->operations = $operations;
        }

        $narrowsparkOptions = $this->projectOptions['narrowspark'];
        $packages           = (new OperationsResolver($this->operations, $this->vendorDir))->resolve();
        $allowInstall       = $narrowsparkOptions['allow-auto-install'] ?? false;

        $this->io->writeError(\sprintf(
            '<info>Narrowspark operations: %s package%s</info>',
            \count($packages),
            \count($packages) > 1 ? 's' : ''
        ));

        foreach ($packages as $package) {
            if (isset($narrowsparkOptions['dont-discover']) && \array_key_exists($package->getName(), $narrowsparkOptions['dont-discover'])) {
                $this->io->write(\sprintf('<info>Package "%s" was ignored.</info>', $package->getName()));

                return;
            }

            if ($package->getOperation() === 'install' && $allowInstall === false) {
                $answer = $this->io->askAndValidate(
                    self::getPackageQuestion($package->getUrl()),
                    [$this, 'validatePackageQuestionAnswerValue'],
                    null,
                    'n'
                );

                if ($answer === 'n') {
                    return;
                }

                if ($answer === 'a') {
                    $allowInstall = true;
                } elseif ($answer === 'p') {
                    $allowInstall = true;

                    $this->manipulateComposerJsonWithAllowAutoInstall();

                    $this->shouldUpdateComposerLock = true;
                }
            }

            $updateLock = true;

            if ($this->lock->has($package->getName())) {
                $updateLock = $package->getVersion() !== $this->lock->get($package->getName())['version'];
            }

            switch ($package->getOperation()) {
                case 'install' && ! $this->lock->has($package->getName()):
                case 'update' && $updateLock:
                    $this->io->writeError(\sprintf('  - Configuring %s', $package->getName()));

                    $this->configurator->configure($package);

                    $this->lock->add($package->getName(), $package->getOptions());

                    break;
                case 'uninstall' && $this->lock->has($package->getName()):
                    $this->io->writeError(\sprintf('  - Unconfiguring %s', $package->getName()));

                    $this->configurator->unconfigure($package);

                    $this->lock->remove($package->getName());
                    $this->lock->write();

                    break;
            }
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
     * Validate given input answer.
     *
     * @param null|string $value
     *
     * @throws \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     *
     * @return string
     */
    public function validatePackageQuestionAnswerValue(?string $value): string
    {
        if ($value === null) {
            return 'n';
        }

        $value = \mb_strtolower($value[0]);

        if (! \in_array($value, ['y', 'n', 'a', 'p'], true)) {
            throw new InvalidArgumentException('Invalid choice');
        }

        return $value;
    }

    /**
     * Validate given input answer.
     *
     * @param null|string $value
     *
     * @throws \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     *
     * @return string
     */
    public function validateProjectQuestionAnswerValue(?string $value): string
    {
        if ($value === null) {
            return 'f';
        }

        $value = \mb_strtolower($value[0]);

        if (! \in_array($value, ['f', 'h', 'c'], true)) {
            throw new InvalidArgumentException('Invalid choice');
        }

        return $value;
    }

    /**
     * Check which package should be recorded.
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return bool
     */
    private function shouldRecordOperation(PackageEvent $event): bool
    {
        $operation = $event->getOperation();

        if ($operation instanceof UpdateOperation) {
            $package = $operation->getTargetPackage();
        } else {
            $package = $operation->getPackage();
        }

        // when Composer runs with --no-dev, ignore uninstall operations on packages from require-dev
        if (! $event->isDevMode() && $operation instanceof UninstallOperation) {
            foreach ($event->getComposer()->getLocker()->getLockData()['packages-dev'] as $devPackage) {
                if ($package->getName() === $devPackage['name']) {
                    return false;
                }
            }
        }

        if (
            ($operation instanceof InstallOperation && ! $this->lock->has($package->getName())) ||
            $operation instanceof UninstallOperation
        ) {
            return true;
        }

        return false;
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
     * Add extra option "allow-auto-install" to composer.json.
     *
     * @return void
     */
    private function manipulateComposerJsonWithAllowAutoInstall(): void
    {
        $json        = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(\file_get_contents($json->getPath()));
        $manipulator->addSubNode('extra', 'narrowspark.allow-auto-install', true);

        \file_put_contents($json->getPath(), $manipulator->getContents());
    }

    /**
     * Returns the questions for package install.
     *
     * @param string $url
     *
     * @return string
     */
    private static function getPackageQuestion(string $url): string
    {
        return \sprintf('    Review the package from %s.
    Do you want to execute this package?
    [<comment>y</comment>] Yes
    [<comment>n</comment>] No
    [<comment>a</comment>] Yes for all packages, only for the current installation session
    [<comment>p</comment>] Yes permanently, never ask again for this project
    (defaults to <comment>n</comment>): ', $url);
    }

    /**
     * @return string
     */
    private static function getProjectQuestion(): string
    {
        return '    Please choose you project type.
    [<comment>f</comment>] Full Stack framework
    [<comment>h</comment>] Http framework
    [<comment>c</comment>] Console framework
    (defaults to <comment>f</comment>): ';
    }

    /**
     * Init default options.
     *
     * @return array
     */
    private function initProjectOptions(): array
    {
        return \array_merge(
            [
                'narrowspark' => [
                    'allow-auto-install' => false,
                    'dont-discover'      => [],
                ],
                'app-dir'            => 'app',
                'config-dir'         => 'config',
                'public-dir'         => 'public',
                'resources-dir'      => 'resources',
                'routes-dir'         => 'routes',
                'tests-dir'          => 'tests',
                'storage-dir'        => 'storage',
            ],
            $this->composer->getPackage()->getExtra()
        );
    }
}
