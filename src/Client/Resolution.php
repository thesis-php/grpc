<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

/**
 * @api
 */
final readonly class Resolution
{
    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    public function __construct(
        public array $endpoints,
    ) {}
}
