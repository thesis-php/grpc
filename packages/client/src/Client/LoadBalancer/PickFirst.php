<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\LoadBalancer;

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
        private array $endpoints,
    ) {
        $this->current = $endpoints[0];
    }

    #[\Override]
    public function refresh(array $endpoints): void
    {
        $this->endpoints = $endpoints;

        if (!array_any($endpoints, $this->current->equals(...))) {
            $this->current = $endpoints[0];
        }
    }

    #[\Override]
    public function pick(PickContext $context): Endpoint
    {
        foreach ([$this->current, ...$this->endpoints] as $endpoint) {
            if (!$context->excluded($endpoint)) {
                $this->current = $endpoint;
                break;
            }
        }

        return $this->current;
    }
}
