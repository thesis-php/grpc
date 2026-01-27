<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 * @template TRequest of object
 * @template TResponse of object
 * @template-implements Handler<TRequest, TResponse>
 */
final readonly class ClientStreamHandler implements Handler
{
    /**
     * @param \Closure(ClientStreamChannel<TRequest, TResponse>, Metadata, Cancellation): void $handler
     */
    public function __construct(
        private \Closure $handler,
    ) {}

    #[\Override]
    public function handle(ServerStream $stream, Metadata $md, Cancellation $cancellation): void
    {
        ($this->handler)(new ClientStreamChannel($stream), $md, $cancellation);
    }
}
