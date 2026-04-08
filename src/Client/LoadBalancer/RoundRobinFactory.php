<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\LoadBalancer;

use Thesis\Grpc\Client\LoadBalancer;
use Thesis\Grpc\Client\LoadBalancerFactory;

/**
 * @api
 */
final readonly class RoundRobinFactory implements LoadBalancerFactory
{
    #[\Override]
    public function name(): string
    {
        return 'round_robin';
    }

    #[\Override]
    public function create(array $endpoints): LoadBalancer
    {
        return new RoundRobin($endpoints);
    }
}
