<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Testing;

use Psr\Http\Message\RequestInterface;
use RuntimeException;

class StrayRequestException extends RuntimeException
{
    public function __construct(
        private RequestInterface $request,
    ) {
        parent::__construct(
            sprintf('No Housepedia endpoint matches %s %s.', $request->getMethod(), $request->getUri()->getPath()),
        );
    }

    public function request(): RequestInterface
    {
        return $this->request;
    }
}
