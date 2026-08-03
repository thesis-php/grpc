<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\UnaryInterceptor;
use Thesis\Grpc\Metadata;

/**
 * @internal
 */
final readonly class UnaryInterceptorComposer
{
    /**
     * @param list<UnaryInterceptor> $interceptors
     */
    public function __construct(
        private array $interceptors,
    ) {}

    /**
     * @template In of object
     * @template Out of object
     * @param In $request
     * @param Invoke<In, Out> $invoke
     * @param callable(In, Invoke<In, Out>, Metadata, Cancellation): Out $invoker
     * @return Out
     */
    public function intercept(
        object $request,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $invoker,
    ): object {
        $handler = array_reduce(
            array_reverse($this->interceptors),
            static fn(callable $stack, UnaryInterceptor $interceptor) => static fn(
                object $request,
                Invoke $invoke,
                Metadata $md,
                Cancellation $cancellation,
            ) => $interceptor->interceptUnary(
                $request,
                $invoke,
                $md,
                $cancellation,
                $stack(...), // @phpstan-ignore argument.type
            ),
            $invoker,
        );

        /** @var Out */
        return $handler($request, $invoke, $md, $cancellation);
    }
}
