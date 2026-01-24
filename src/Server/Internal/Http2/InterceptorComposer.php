<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
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
     * @param ServerStream<object, object> $stream
     * @param callable(Handle, Metadata, ServerStream<object, object>, Cancellation): void $next
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
                new StackInterceptor($stack),
            ),
            $next,
        );

        $handler($handle, $md, $stream, $cancellation);
    }
}
