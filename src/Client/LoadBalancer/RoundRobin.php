<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\LoadBalancer;

use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\LoadBalancer;

/**
 * @api
 */
final class RoundRobin implements LoadBalancer
{
    private int $cursor = 0;

    /** @var positive-int */
    private int $count;

    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    public function __construct(
        private array $endpoints,
    ) {
        $this->count = \count($endpoints);
    }

    #[\Override]
    public function refresh(array $endpoints): void
    {
        $this->endpoints = $endpoints;
        $this->count = \count($endpoints);
    }

    #[\Override]
    public function pick(): Endpoint
    {
        return $this->endpoints[$this->cursor++ % $this->count]; // @phpstan-ignore offsetAccess.notFound
    }
}
