<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\RpcType;

/**
 * @api
 */
final readonly class Rpc
{
    /**
     * @template In of object
     * @template Out of object
     * @param Handle<In> $handle
     * @param StreamHandler<In, Out>|UnaryHandler<In, Out> $handler a streaming handler, or a unary request → response handler
     */
    public function __construct(
        public Handle $handle,
        public StreamHandler|UnaryHandler $handler,
        public RpcType $type,
    ) {}
}
