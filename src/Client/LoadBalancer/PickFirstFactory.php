<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\LoadBalancer;

use Random\Randomizer;
use Thesis\Grpc\Client\LoadBalancer;
use Thesis\Grpc\Client\LoadBalancerFactory;

/**
 * @api
 */
final readonly class PickFirstFactory implements LoadBalancerFactory
{
    public function __construct(
        private Randomizer $randomizer = new Randomizer(),
    ) {}

    #[\Override]
    public function name(): string
    {
        return 'pick_first';
    }

    #[\Override]
    public function create(array $endpoints): LoadBalancer
    {
        return new PickFirst($endpoints, $this->randomizer);
    }
}
