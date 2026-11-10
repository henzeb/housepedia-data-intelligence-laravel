<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class NetworkException extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(
        private RequestInterface $request,
        ConnectionException $previous,
    ) {
        parent::__construct($previous->getMessage(), 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
