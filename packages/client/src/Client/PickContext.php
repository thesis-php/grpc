<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Thesis\Grpc\Metadata;

/**
 * @api
 */
final class PickContext
{
    /**
     * @param non-empty-string $methodName
     * @param list<Endpoint> $excluded endpoints that already failed this call and should be skipped if possible
     */
    public function __construct(
        public readonly string $methodName,
        public readonly Metadata $metadata,
        private array $excluded = [],
    ) {}

    public function excluded(Endpoint $endpoint): bool
    {
        return array_any($this->excluded, $endpoint->equals(...));
    }

    /**
     * Records the endpoint the transport just picked so a subsequent retry skips it.
     */
    public function exclude(Endpoint $endpoint): void
    {
        $this->excluded[] = $endpoint;
    }
}
