<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class CallableInterceptor implements Interceptor
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
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): ClientStream {
        return ($this->handler)(
            $invoke,
            $md,
            $cancellation,
            $next,
        );
    }
}
