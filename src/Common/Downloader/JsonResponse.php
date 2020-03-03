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

namespace Narrowspark\Automatic\Common\Downloader;

use JsonSerializable;

class JsonResponse implements JsonSerializable
{
    /** @var null|array */
    protected $body;

    /** @var array<int|string, string> */
    protected $origHeaders;

    /** @var array<int|string, array<int, string>> */
    protected $headers;

    /** @var int */
    protected $code;

    /**
     * @param array<string, mixed>      $body    The response as JSON
     * @param array<int|string, string> $headers
     */
    public function __construct(?array $body, array $headers = [], int $code = 200)
    {
        $this->body = $body;
        $this->origHeaders = $headers;
        $this->headers = $this->parseHeaders($headers);
        $this->code = $code;
    }

    /**
     * Gets the body of the message.
     *
     * @return null|array returns the body as a array if it exists or null for no content
     */
    public function getBody(): ?array
    {
        return $this->body;
    }

    /**
     * Returns the header array on given header name.
     *
     * @return array<int, int|string>
     */
    public function getHeaders(string $name): array
    {
        return $this->headers[\strtolower($name)] ?? [];
    }

    /**
     * Create a new Json Response from json array.
     *
     * @param array<string, mixed> $json
     *
     * @return self
     */
    public static function fromJson(array $json): JsonResponse
    {
        $response = new self($json['body']);
        $response->headers = $json['headers'];

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return ['body' => $this->body, 'headers' => $this->headers];
    }

    /**
     * Returns the first value from header array on given header name.
     */
    public function getHeader(string $name): string
    {
        return $this->headers[\strtolower($name)][0] ?? '';
    }

    /**
     * Returns the header before the parsing was done.
     */
    public function getOriginalHeaders(): array
    {
        return $this->origHeaders;
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int status code
     */
    public function getStatusCode(): int
    {
        return $this->code;
    }

    /**
     * @param array<int|string, string> $headers
     *
     * @return array<int|string, array<int, string>>
     */
    private function parseHeaders(array $headers): array
    {
        $values = [];

        foreach (\array_reverse($headers) as $header) {
            if (\preg_match('{^([^\:]+):\s*(.+?)\s*$}i', $header, $match) === 1) {
                $values[\strtolower($match[1])][] = $match[2];
            } elseif (\preg_match('{^HTTP/}i', $header) === 1) {
                break;
            }
        }

        return $values;
    }
}
