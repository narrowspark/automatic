<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test\Fixture\Finder;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Viserio\Component\View\ViewFactory;

class StaticFunctionAndClasses
{
    public static function getInstanceIdentifier()
    {
        return ViewFactory::class;
    }

    /**
     * Create a response from string.
     *
     * @param string $template
     * @param array  $args
     *
     * @return ResponseInterface
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
