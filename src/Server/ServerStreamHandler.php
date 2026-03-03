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
final readonly class ServerStreamHandler implements Handler
{
    /**
     * @param \Closure(TRequest, Metadata, Cancellation): iterable<array-key, TResponse> $handler
     */
    public function __construct(
        private \Closure $handler,
    ) {}

    #[\Override]
    public function handle(ServerStream $stream, Metadata $md, Cancellation $cancellation): void
    {
        $request = $stream->receive();
        $messages = ($this->handler)($request, $md, $cancellation);

        foreach ($messages as $message) {
            $stream->send($message);
        }

        $stream->close();
    }
}
