<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
interface Interceptor
{
    /**
     * @template In of object
     * @template Out of object
     * @param Invoke<In, Out> $invoke
     * @param callable(Invoke<In, Out>, Metadata, Cancellation): ClientStream<In, Out> $next
     * @return ClientStream<In, Out>
     */
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): ClientStream;
}
