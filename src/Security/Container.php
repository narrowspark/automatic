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

namespace Narrowspark\Automatic\Security;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Security\Contract\Downloader as DownloaderContract;
use Narrowspark\Automatic\Security\Downloader\ComposerDownloader;
use Narrowspark\Automatic\Security\Downloader\CurlDownloader;
use Symfony\Component\Console\Input\InputInterface;
use function extension_loaded;

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
            'composer-extra' => static function (ContainerContract $container) {
                return $container->get(Composer::class)->getPackage()->getExtra();
            },
            DownloaderContract::class => static function () {
                if (extension_loaded('curl')) {
                    return new CurlDownloader();
                }

                return new ComposerDownloader();
            },
            'security_advisories' => static function (ContainerContract $container) {
                /** @var Audit $audit */
                $audit = $container->get(Audit::class);

                return $audit->getSecurityAdvisories($container->get(IOInterface::class));
            },
        ]);
    }
}
