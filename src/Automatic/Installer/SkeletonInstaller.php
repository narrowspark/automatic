<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

class SkeletonInstaller extends AbstractInstaller
{
    /**
     * {@inheritdoc}
     */
    public const TYPE = 'automatic-skeleton';

    /**
     * {@inheritdoc}
     */
    public const LOCK_KEY = 'skeleton_generators';

    /**
     * {@inheritdoc}
     */
    protected const OVERWRITE_LOCK = true;
}
