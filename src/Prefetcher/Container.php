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

namespace Narrowspark\Automatic\Prefetcher;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use Narrowspark\Automatic\Prefetcher\Downloader\ParallelDownloader;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class Container extends AbstractContainer
{
    use GetGenericPropertyReaderTrait;

    /**
     * Instantiate the container.
     *
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $genericPropertyReader = $this->getGenericPropertyReader();

        parent::__construct([
            Composer::class => static function () use ($composer) {
                return $composer;
            },
            Config::class => static function (ContainerContract $container) {
                return $container->get(Composer::class)->getConfig();
            },
            IOInterface::class => static function () use ($io) {
                return $io;
            },
            InputInterface::class => static function (ContainerContract $container) use ($genericPropertyReader) {
                return $genericPropertyReader($container->get(IOInterface::class), 'input');
            },
            RemoteFilesystem::class => static function (ContainerContract $container) {
                return Factory::createRemoteFilesystem(
                    $container->get(IOInterface::class),
                    $container->get(Config::class)
                );
            },
            ParallelDownloader::class => static function (ContainerContract $container) {
                $rfs = $container->get(RemoteFilesystem::class);

                return new ParallelDownloader(
                    $container->get(IOInterface::class),
                    $container->get(Config::class),
                    $rfs->getOptions(),
                    $rfs->isTlsDisabled()
                );
            },
            Prefetcher::class => static function (ContainerContract $container) {
                return new Prefetcher(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get(InputInterface::class),
                    $container->get(ParallelDownloader::class)
                );
            },
            LegacyTagsManagerContract::class => static function (ContainerContract $container) {
                return new LegacyTagsManager($container->get(IOInterface::class));
            },
            'composer-extra' => static function (ContainerContract $container) {
                return $container->get(Composer::class)->getPackage()->getExtra();
            },
        ]);
    }
}
