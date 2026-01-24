<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handle;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class StackInterceptor
{
    /**
     * @param callable(Handle, Metadata, ServerStream<object, object>, Cancellation): void $handler
     */
    public function __construct(
        private mixed $handler,
    ) {}

    /**
     * @param ServerStream<object, object> $stream
     */
    public function __invoke(
        Handle $handle,
        Metadata $md,
        ServerStream $stream,
        Cancellation $cancellation,
    ): void {
        ($this->handler)($handle, $md, $stream, $cancellation);
    }
}
