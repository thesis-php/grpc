<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Invoke;
use Thesis\Grpc\Metadata;

/**
 * @internal
 */
final readonly class StackInterceptor
{
    /**
     * @param callable(Invoke, Metadata, Cancellation): ClientStream<*, *> $handler
     */
    public function __construct(
        private mixed $handler,
    ) {}

    /**
     * @return ClientStream<*, *>
     */
    public function __invoke(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
    ): ClientStream {
        return ($this->handler)($invoke, $md, $cancellation);
    }
}
