<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 * @phpstan-type Next = callable(Handle, Metadata, ServerStream<object, object>, Cancellation): void
 */
interface Interceptor
{
    /**
     * @param Next $next
     * @param ServerStream<object, object> $stream
     */
    public function intercept(
        Handle $handle,
        Metadata $md,
        ServerStream $stream,
        Cancellation $cancellation,
        callable $next,
    ): void;
}
