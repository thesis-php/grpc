<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 * @phpstan-import-type Next from Interceptor
 */
final readonly class CallableInterceptor implements Interceptor
{
    /**
     * @param callable(Handle, Metadata, ServerStream<object, object>, Cancellation, Next): void $handler
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
