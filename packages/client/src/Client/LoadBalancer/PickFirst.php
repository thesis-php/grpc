<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\LoadBalancer;

use Random\Randomizer;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\LoadBalancer;
use Thesis\Grpc\Client\PickContext;

/**
 * @api
 */
final class PickFirst implements LoadBalancer
{
    private Endpoint $current;

    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    public function __construct(
        array $endpoints,
        private readonly Randomizer $randomizer,
    ) {
        $this->current = $this->doPick($endpoints);
    }

    #[\Override]
    public function refresh(array $endpoints): void
    {
        $this->current = $this->doPick($endpoints, $this->current);
    }

    #[\Override]
    public function pick(PickContext $context): Endpoint
    {
        return $this->current;
    }

    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    private function doPick(array $endpoints, ?Endpoint $current = null): Endpoint
    {
        /** @var non-empty-list<Endpoint> $endpoints */
        $endpoints = $this->randomizer->shuffleArray($endpoints);

        if ($current === null || !array_any($endpoints, $current->equals(...))) {
            $current = $endpoints[0];
        }

        return $current;
    }
}
