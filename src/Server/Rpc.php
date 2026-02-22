<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

/**
 * @api
 */
final readonly class Rpc
{
    /**
     * @template In of object
     * @template Out of object
     * @param Handle<In> $handle
     * @param Handler<In, Out> $handler
     */
    public function __construct(
        public Handle $handle,
        public Handler $handler,
        public RpcType $type,
    ) {}
}
