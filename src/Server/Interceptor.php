<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
use Amp\CancelledException;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 */
interface Interceptor
{
    /**
     * @template In of object
     * @template Out of object
     * @param callable(Handle<In>, Metadata, ServerStream<In, Out>, Cancellation): void $next
     * @param ServerStream<In, Out> $stream
     * @throws InvokeError
     * @throws CancelledException
     */
    public function intercept(
        Handle $handle,
        Metadata $md,
        ServerStream $stream,
        Cancellation $cancellation,
        callable $next,
    ): void;
}
