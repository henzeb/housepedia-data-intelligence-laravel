<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Testing;

use Psr\Http\Message\RequestInterface;

class RecordedRequest
{
    public function __construct(
        private Endpoint $endpoint,
        private RequestInterface $request,
    ) {
    }

    public function endpoint(): Endpoint
    {
        return $this->endpoint;
    }

    public function method(): string
    {
        return $this->request->getMethod();
    }

    public function url(): string
    {
        return (string)$this->request->getUri();
    }

    public function path(): string
    {
        return rawurldecode($this->request->getUri()->getPath());
    }

    public function query(?string $key = null): mixed
    {
        parse_str($this->request->getUri()->getQuery(), $query);

        return $key === null ? $query : ($query[$key] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function body(): array
    {
        $body = json_decode((string)$this->request->getBody(), true);

        return is_array($body) ? $body : [];
    }

    public function header(string $name): ?string
    {
        return $this->request->hasHeader($name) ? $this->request->getHeaderLine($name) : null;
    }

    public function toPsrRequest(): RequestInterface
    {
        return $this->request;
    }
}
