<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

class PolyfillManager
{
    /**
     * @var array
     */
    private static $extReplacer = [
        'mb_strlen'   => 'symfony/polyfill-mbstring',
        'iconv'       => 'symfony/polyfill-iconv',
        'ctype_alnum' => 'symfony/polyfill-ctype',
    ];

    /**
     * @var array
     */
    private static $phpReplacer = [
        '5.6' => 'symfony/polyfill-php56',
        '7.0' => 'symfony/polyfill-php70',
        '7.1' => 'symfony/polyfill-php71',
        '7.2' => 'symfony/polyfill-php72',
        '7.3' => 'symfony/polyfill-php73',
    ];

    public function __construct()
    {

    }

    public function replace(): void
    {
        $replacer = [];
    }
}
