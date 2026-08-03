<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Thesis\Grpc\RpcType;

/**
 * @api
 * @template-covariant In of object = object
 * @template-covariant Out of object = object
 */
final readonly class Invoke
{
    /**
     * @param non-empty-string $method
     * @param class-string<Out> $output
     */
    public function __construct(
        public string $method,
        public string $output,
        public RpcType $type,
    ) {}
}
