<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
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
     * @param \Closure(ClientStreamChannel<TRequest, TResponse>, Cancellation): void $handler
     */
    public function __construct(
        private \Closure $handler,
    ) {}

    #[\Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        ($this->handler)(new ClientStreamChannel($stream), $cancellation);
    }
}
