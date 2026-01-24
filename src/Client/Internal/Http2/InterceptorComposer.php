<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Amp\Cancellation;
use Thesis\Grpc\Client\Interceptor;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

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
     * @param callable(Invoke, Metadata, Cancellation): ClientStream<*, *> $next
     * @return ClientStream<*, *>
     */
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): ClientStream {
        $handler = array_reduce(
            array_reverse($this->interceptors),
            static fn(callable $stack, Interceptor $interceptor) => static fn(
                Invoke $invoke,
                Metadata $md,
                Cancellation $cancellation,
            ) => $interceptor->intercept(
                $invoke,
                $md,
                $cancellation,
                new StackInterceptor($stack),
            ),
            $next,
        );

        return $handler($invoke, $md, $cancellation);
    }
}
