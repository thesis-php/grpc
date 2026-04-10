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
     * @template In of object
     * @template Out of object
     * @param Invoke<In, Out> $invoke
     * @param callable(Invoke<In, Out>, Metadata, Cancellation): ClientStream<In, Out> $next
     * @return ClientStream<In, Out>
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
                $stack(...), // @phpstan-ignore argument.type
            ),
            $next,
        );

        /** @var ClientStream<In, Out> */
        return $handler($invoke, $md, $cancellation);
    }
}
