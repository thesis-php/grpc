<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

/**
 * @api
 */
final readonly class Service
{
    /**
     * @param non-empty-string $name
     * @param non-empty-list<Rpc> $handlers
     */
    public function __construct(
        public string $name,
        public array $handlers,
    ) {}
}
