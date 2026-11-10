<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpClientAdapter implements ClientInterface
{
    public function __construct(
        private Factory $http,
        private float $timeout,
        private float $connectTimeout,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $pending = $this->http
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withHeaders($this->headers($request));

        $body = (string)$request->getBody();

        if ($body !== '') {
            $pending = $pending->withBody($body, $request->getHeaderLine('Content-Type'));
        }

        try {
            return $pending->send($request->getMethod(), (string)$request->getUri())->toPsrResponse();
        } catch (ConnectionException $exception) {
            throw new NetworkException($request, $exception);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            if (!in_array(strtolower((string)$name), ['host', 'content-type'], true)) {
                $headers[(string)$name] = implode(', ', $values);
            }
        }

        return $headers;
    }
}
