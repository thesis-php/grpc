<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

/**
 * @api
 * @template In of object
 */
final readonly class Handle
{
    /**
     * @param non-empty-string $method
     * @param class-string<In> $type
     */
    public function __construct(
        public string $method,
        public string $type,
    ) {}
}
