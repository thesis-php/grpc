<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\LoadBalancer;

use Thesis\Grpc\Client\LoadBalancer;
use Thesis\Grpc\Client\LoadBalancerFactory;

/**
 * @api
 */
final readonly class PickFirstFactory implements LoadBalancerFactory
{
    #[\Override]
    public function name(): string
    {
        return 'pick_first';
    }

    #[\Override]
    public function create(array $endpoints): LoadBalancer
    {
        return new PickFirst($endpoints);
    }
}
