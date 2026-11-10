<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Testing;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * @phpstan-consistent-constructor
 */
class FakeResponse
{
    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        private array $body,
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string> $headers
     */
    public static function json(array $body, int $status = 200, array $headers = []): static
    {
        return new static($body, $status, $headers);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function error(
        int $status,
        ?string $code = null,
        ?string $detail = null,
        array $body = [],
        array $headers = [],
    ): static {
        return new static(
            array_filter(
                [
                    'status' => $status,
                    'title' => (new Response($status))->getReasonPhrase() ?: 'HTTP ' . $status,
                    'code' => $code,
                    'detail' => $detail,
                ],
                fn (mixed $value): bool => $value !== null,
            ) + $body,
            $status,
            $headers,
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function toPsrResponse(bool $withBody = true): ResponseInterface
    {
        $contentType = $this->status >= 400 ? 'application/problem+json' : 'application/json';

        return new Response(
            $this->status,
            $this->headers + ['Content-Type' => $contentType],
            $withBody ? json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) : '',
        );
    }
}
