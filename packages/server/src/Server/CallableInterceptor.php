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
     * @param callable(ServerStream<In, Out>, StreamInfo, Metadata, Cancellation, callable(ServerStream<In, Out>, StreamInfo, Metadata, Cancellation): void): void $handler
     */
    public function __construct(
        private mixed $handler,
    ) {}

    #[\Override]
    public function intercept(
        ServerStream $stream,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): void {
        ($this->handler)(
            $stream,
            $info,
            $md,
            $cancellation,
            $next,
        );
    }
}
