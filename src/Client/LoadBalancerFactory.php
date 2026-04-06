<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

/**
 * @api
 */
interface LoadBalancerFactory
{
    /**
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    public function create(array $endpoints): LoadBalancer;
}
