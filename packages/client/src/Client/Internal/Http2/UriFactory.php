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
    /**
     * @param ?non-empty-string $authority the ":authority" of every call; the endpoint address when null
     */
    public function __construct(
        private HttpScheme $scheme,
        private ?string $authority,
    ) {}

    /**
     * @return non-empty-string
     */
    public function create(Address $address, Invoke $invoke): string
    {
        return \sprintf('%s://%s/%s', $this->scheme->value, $this->authority ?? $address->value, ltrim($invoke->method, '/'));
    }
}
