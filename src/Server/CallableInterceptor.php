<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 */
final readonly class CallableInterceptor implements Interceptor
{
    /**
     * @template In of object
     * @template Out of object
     * @param callable(Handle, Metadata, ServerStream<In, Out>, Cancellation, callable(Handle<In>, Metadata, ServerStream<In, Out>, Cancellation): void): void $handler
     */
    public function __construct(
        private mixed $handler,
    ) {}

    #[\Override]
    public function intercept(
        Handle $handle,
        Metadata $md,
        ServerStream $stream,
        Cancellation $cancellation,
        callable $next,
    ): void {
        ($this->handler)(
            $handle,
            $md,
            $stream,
            $cancellation,
            $next,
        );
    }
}
