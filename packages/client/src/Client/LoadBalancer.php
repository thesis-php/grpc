<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

/**
 * @api
 * @see https://github.com/grpc/grpc/blob/master/doc/load-balancing.md
 */
interface LoadBalancer
{
    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    public function refresh(array $endpoints): void;

    public function pick(PickContext $context): Endpoint;
}
