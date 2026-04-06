<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

/**
 * @api
 */
final readonly class Address implements \Stringable
{
    /**
     * @param non-empty-string $value
     */
    public function __construct(
        public string $value,
    ) {}

    /**
     * @return non-empty-string
     */
    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
