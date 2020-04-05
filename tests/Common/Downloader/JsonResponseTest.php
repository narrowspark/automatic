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

namespace Narrowspark\Automatic\Tests\Common\Downloader;

use Narrowspark\Automatic\Common\Downloader\JsonResponse;
use Narrowspark\Automatic\Security\Audit;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Downloader\JsonResponse
 *
 * @small
 */
final class JsonResponseTest extends TestCase
{
    /** @var JsonResponse */
    private $jsonResponse;

    /** @var array<string, mixed> */
    private $content;

    /** @var array<int, string> */
    private $headers;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonResponse = new JsonResponse([]);
        $this->content = [
            'test' => 'foo',
        ];
        $this->headers = [
            'HTTP_FOO:foo',
            'User-Agent:' . Audit::getUserAgent(),
            'HTTP_BAR=TEST',
        ];
    }

    public function testStatusCode(): void
    {
        self::assertSame(200, $this->jsonResponse->getStatusCode());
    }

    public function testBodyWithEmptyContent(): void
    {
        self::assertSame([], $this->jsonResponse->getBody());
    }

    public function testBodyWithContent(): void
    {
        $jsonResponse = new JsonResponse($this->content);

        self::assertSame($this->content, $jsonResponse->getBody());
    }

    public function testJsonSerializeWithEmptyContentAndHeaders(): void
    {
        self::assertSame(['body' => [], 'headers' => []], $this->jsonResponse->jsonSerialize());
    }

    public function testJsonSerializeWithContentAndHeaders(): void
    {
        $jsonResponse = new JsonResponse($this->content, $this->headers);

        self::assertSame([
            'body' => $this->content,
            'headers' => [
                'user-agent' => [
                    Audit::getUserAgent(),
                ],
                'http_foo' => [
                    'foo',
                ],
            ],
        ], $jsonResponse->jsonSerialize());
    }

    public function testFromJsonWithContent(): void
    {
        $content = [
            'test' => 'foo',
        ];

        $data = [
            'body' => $content,
            'headers' => [
                'user-agent' => [
                    Audit::getUserAgent(),
                ],
                'http_foo' => [
                    'foo',
                ],
            ],
        ];

        $jsonResponse = JsonResponse::fromJson($data);

        self::assertSame($data, $jsonResponse->jsonSerialize());
    }

    public function testGetHeaders(): void
    {
        $jsonResponse = new JsonResponse($this->content, $this->headers);

        self::assertSame([Audit::getUserAgent()], $jsonResponse->getHeaders('user-agent'));
        self::assertSame([], $jsonResponse->getHeaders('user-agent1'));
    }

    public function testGetHeader(): void
    {
        $jsonResponse = new JsonResponse($this->content, $this->headers);

        self::assertSame(Audit::getUserAgent(), $jsonResponse->getHeader('user-agent'));
        self::assertSame('', $jsonResponse->getHeader('user-agent1'));
    }

    public function testGetOriginalHeaders(): void
    {
        self::assertSame([], $this->jsonResponse->getOriginalHeaders());

        $jsonResponse = new JsonResponse($this->content, $this->headers);

        self::assertSame($this->headers, $jsonResponse->getOriginalHeaders());
    }
}
