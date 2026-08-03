<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class CallableStreamInterceptor implements StreamInterceptor
{
    /**
     * @template In of object
     * @template Out of object
     * @param callable(Invoke<In, Out>, Metadata, Cancellation, callable(Invoke<In, Out>, Metadata, Cancellation): ClientStream<In, Out>): ClientStream<In, Out> $handler
     */
    public function __construct(
        private mixed $handler,
    ) {}

    #[\Override]
    public function interceptStream(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $newStream,
    ): ClientStream {
        return ($this->handler)(
            $invoke,
            $md,
            $cancellation,
            $newStream,
        );
    }
}
