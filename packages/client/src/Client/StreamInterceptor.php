<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
interface StreamInterceptor
{
    /**
     * @template In of object
     * @template Out of object
     * @param Invoke<In, Out> $invoke
     * @param callable(Invoke<In, Out>, Metadata, Cancellation): ClientStream<In, Out> $newStream
     * @return ClientStream<In, Out>
     */
    public function interceptStream(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $newStream,
    ): ClientStream;
}
