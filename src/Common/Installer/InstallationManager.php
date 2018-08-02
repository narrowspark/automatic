<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Installer;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\InstallationManager as BaseInstallationManager;
use Composer\Repository\RepositoryInterface;

class InstallationManager extends BaseInstallationManager
{
    /**
     * The composer operations.
     *
     * @var array
     */
    private $operations = [];

    /**
     * {@inheritdoc}
     */
    public function execute(RepositoryInterface $repo, OperationInterface $operation): void
    {
        if ($operation instanceof InstallOperation ||
            $operation instanceof UpdateOperation ||
            $operation instanceof UninstallOperation
        ) {
            $this->operations[] = $operation;
        }

        parent::execute($repo, $operation);
    }

    /**
     * @return array
     */
    public function getOperations(): array
    {
        return $this->operations;
    }
}
