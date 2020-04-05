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

namespace Narrowspark\Automatic\Tests\Common\Fixture\Finder;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Viserio\Component\View\ViewFactory;

final class StaticFunctionAndClasses
{
    public static function getInstanceIdentifier()
    {
        return ViewFactory::class;
    }

    /**
     * Create a response from string.
     */
    public static function createResponseView(string $template, array $args = []): ResponseInterface
    {
        $response = self::$container->get(ResponseFactoryInterface::class)->createResponse();
        $response = $response->withAddedHeader('Content-Type', 'text/html');

        $stream = self::$container->get(StreamFactoryInterface::class)->createStream();
        $stream->write((string) self::$container->get(ViewFactory::class)->create($template, $args));

        return $response->withBody($stream);
    }
}
