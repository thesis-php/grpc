<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Internal\HttpScheme;
use Thesis\Grpc\Client\Invoke;

/**
 * @internal
 */
final readonly class UriFactory
{
    public function __construct(
        private HttpScheme $scheme,
    ) {}

    /**
     * @return non-empty-string
     */
    public function create(Address $address, Invoke $invoke): string
    {
        return \sprintf('%s://%s/%s', $this->scheme->value, $address->value, ltrim($invoke->method, '/'));
    }
}
