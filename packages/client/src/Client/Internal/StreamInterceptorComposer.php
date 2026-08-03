<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\StreamInterceptor;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

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
     * @param Invoke<In, Out> $invoke
     * @param callable(Invoke<In, Out>, Metadata, Cancellation): ClientStream<In, Out> $newStream
     * @return ClientStream<In, Out>
     */
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $newStream,
    ): ClientStream {
        $handler = array_reduce(
            array_reverse($this->interceptors),
            static fn(callable $stack, StreamInterceptor $interceptor) => static fn(
                Invoke $invoke,
                Metadata $md,
                Cancellation $cancellation,
            ) => $interceptor->interceptStream(
                $invoke,
                $md,
                $cancellation,
                $stack(...), // @phpstan-ignore argument.type
            ),
            $newStream,
        );

        /** @var ClientStream<In, Out> */
        return $handler($invoke, $md, $cancellation);
    }
}
