<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Traits;

trait ExpandTargetDirTrait
{
    /**
     * @param string[] $options
     * @param string   $target
     *
     * @return string
     */
    public static function expandTargetDir(array $options, string $target): string
    {
        $found = \preg_match('{%(.+?)%}', $target, $matches);

        if ($found !== 1) {
            return $target;
        }

        $option = \str_replace('_', '-', \strtolower($matches[1]));

        if (! isset($options[$option])) {
            return $matches[0];
        }

        return \str_replace($matches[0], \rtrim($options[$option], '/'), $target);
    }
}
