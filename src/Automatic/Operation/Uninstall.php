<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Operation;

use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\ScriptExecutor;

/**
 * @internal
 */
final class Uninstall extends AbstractOperation
{
    /**
     * {@inheritdoc}
     */
    public function supports(OperationInterface $operation): bool
    {
        return $operation instanceof UninstallOperation && $this->lock->has(Automatic::LOCK_PACKAGES, $operation->getPackage()->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(OperationInterface $operation): PackageContract
    {
        /** @var \Composer\DependencyResolver\Operation\UninstallOperation $operation */
        $name = $operation->getPackage()->getName();

        $package = Package::createFromLock($name, (array) $this->lock->get(Automatic::LOCK_PACKAGES, $name));
        $package->setOperation(PackageContract::UNINSTALL_OPERATION);

        return $package;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(PackageContract $package): void
    {
        $name = $package->getName();

        $classes = $this->findClassesInAutomaticFolder($package, $name);

        foreach ($classes as $class => $path) {
            if (! \class_exists($class)) {
                require_once $path;
            }
        }

        $this->configurator->unconfigure($package);

        $this->addPackageConfigurators($package);

        $this->packageConfigurator->unconfigure($package);

        $this->showWarningOnRemainingConfigurators($package, $this->packageConfigurator, $this->configurator);

        if ($package->hasConfig(ScriptExecutor::TYPE)) {
            $this->lock->remove(ScriptExecutor::TYPE, $name);
        }

        $this->lock->remove(Automatic::LOCK_PACKAGES, $name);

        $this->packageConfigurator->reset();
        $this->classFinder->reset();
    }
}
