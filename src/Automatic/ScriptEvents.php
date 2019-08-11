<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

final class ScriptEvents
{
    /**
     * The AUTO_SCRIPTS event occurs after a package is installed or updated.
     *
     * The event listener method receives a Composer\Script\Event instance.
     *
     * @var string
     */
    public const AUTO_SCRIPTS = 'auto-scripts';
}
