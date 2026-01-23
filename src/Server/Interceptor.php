<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\ServerStream;

/**
 * @api
 * @phpstan-type Next = callable(Handle<*>, ServerStream<*, *>): void
 */
interface Interceptor
{
    /**
     * @param Handle<*> $handle
     * @param Next $next
     * @param ServerStream<*, *> $stream
     */
    public function intercept(
        Handle $handle,
        ServerStream $stream,
        callable $next,
    ): void;
}
