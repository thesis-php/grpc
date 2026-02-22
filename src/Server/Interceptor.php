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
     * @param ServerStream<In, Out> $stream
     * @param callable(ServerStream<In, Out>, StreamInfo, Metadata, Cancellation): void $next
     * @throws InvokeError
     * @throws CancelledException
     */
    public function intercept(
        ServerStream $stream,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): void;
}
