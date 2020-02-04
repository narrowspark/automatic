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

namespace Narrowspark\Automatic;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Configurator\ComposerAutoScriptsConfigurator;
use Narrowspark\Automatic\Configurator\ComposerScriptsConfigurator;
use Narrowspark\Automatic\Configurator\CopyFromPackageConfigurator;
use Narrowspark\Automatic\Configurator\EnvConfigurator;
use Narrowspark\Automatic\Configurator\GitIgnoreConfigurator;

final class Configurator extends AbstractConfigurator
{
    /**
     * All registered automatic configurators.
     *
     * @var array
     */
    protected $configurators = [
        'composer-auto-scripts' => ComposerAutoScriptsConfigurator::class,
        'composer-scripts' => ComposerScriptsConfigurator::class,
        'copy' => CopyFromPackageConfigurator::class,
        'env' => EnvConfigurator::class,
        'gitignore' => GitIgnoreConfigurator::class,
    ];

    /**
     * Cache found configurators from composer.json.
     *
     * @var array
     */
    private $cache = [];

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->configurators = [
            'composer-auto-scripts' => ComposerAutoScriptsConfigurator::class,
            'composer-scripts' => ComposerScriptsConfigurator::class,
            'copy' => CopyFromPackageConfigurator::class,
            'env' => EnvConfigurator::class,
            'gitignore' => GitIgnoreConfigurator::class,
        ];
        $this->cache = [];
    }

    /**
     * Get a configurator.
     */
    protected function get(string $key): ConfiguratorContract
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $class = $this->configurators[$key];

        return $this->cache[$key] = new $class($this->composer, $this->io, $this->options);
    }
}
