<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handler;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class StreamHandler
{
    public function __construct(
        private StreamErrorHandler $errorHandler,
    ) {}

    /**
     * @template In of object
     * @template Out of object
     * @param Handler<In, Out> $handler
     * @param ServerStream<In, Out> $stream
     */
    public function handle(
        Handler $handler,
        ServerStream $stream,
        Metadata $md,
        Cancellation $cancellation,
    ): void {
        try {
            $handler->handle(new WrappedServerStream($stream), $md, $cancellation);
        } catch (\Throwable $e) {
            $md = $this->errorHandler->handle($e);
            $stream->trailers->join($md);
        } finally {
            $stream->close();
        }
    }
}
