<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Amp\CancelledException;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handle;
use Thesis\Grpc\Server\Interceptor;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class InterceptorComposer
{
    /**
     * @param list<Interceptor> $interceptors
     */
    public function __construct(
        private array $interceptors,
    ) {}

    /**
     * @template In of object
     * @template Out of object
     * @param ServerStream<In, Out> $stream
     * @param callable(Handle<In>, Metadata, ServerStream<In, Out>, Cancellation): void $next
     * @throws InvokeError
     * @throws CancelledException
     */
    public function intercept(
        Handle $handle,
        Metadata $md,
        ServerStream $stream,
        Cancellation $cancellation,
        callable $next,
    ): void {
        $handler = array_reduce(
            array_reverse($this->interceptors),
            static fn(callable $stack, Interceptor $interceptor) => static fn(
                Handle $handle,
                Metadata $md,
                ServerStream $stream,
                Cancellation $cancellation,
            ) => $interceptor->intercept(
                $handle,
                $md,
                $stream,
                $cancellation,
                $stack(...), // @phpstan-ignore argument.type
            ),
            $next,
        );

        $handler($handle, $md, $stream, $cancellation);
    }
}
