<?php

declare(strict_types=1);

namespace Thesis\Grpc;

/**
 * @api
 * @template In of object
 * @template Out of object
 */
final readonly class Invoke
{
    /**
     * @param non-empty-string $method
     * @param class-string<Out> $type
     */
    public function __construct(
        public string $method,
        public string $type,
    ) {}
}
