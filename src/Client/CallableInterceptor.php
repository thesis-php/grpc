<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Invoke;
use Thesis\Grpc\Metadata;

/**
 * @api
 * @phpstan-import-type Handler from Interceptor
 */
final readonly class CallableInterceptor implements Interceptor
{
    /**
     * @param callable(Invoke<*, *>, Metadata, Cancellation, Handler): ClientStream<*, *> $handler
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
