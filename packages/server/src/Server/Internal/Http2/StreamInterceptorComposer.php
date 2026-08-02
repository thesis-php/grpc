<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Amp\CancelledException;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\Server\StreamInterceptor;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class StreamInterceptorComposer
{
    /**
     * @param list<StreamInterceptor> $interceptors
     */
    public function __construct(
        private array $interceptors,
    ) {}

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
    ): void {
        $handler = array_reduce(
            array_reverse($this->interceptors),
            static fn(callable $stack, StreamInterceptor $interceptor) => static fn(
                ServerStream $stream,
                StreamInfo $info,
                Metadata $md,
                Cancellation $cancellation,
            ) => $interceptor->interceptStream(
                $stream,
                $info,
                $md,
                $cancellation,
                $stack(...), // @phpstan-ignore argument.type
            ),
            $next,
        );

        $handler($stream, $info, $md, $cancellation);
    }
}
