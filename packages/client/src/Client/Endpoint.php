<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

/**
 * @api
 */
final readonly class Endpoint
{
    public function __construct(
        public Address $address,
    ) {}

    public function equals(self $other): bool
    {
        return $this->address->value === $other->address->value;
    }
}
