<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal;

use Amp\Cancellation;
use Amp\CancelledException;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\Server\UnaryInterceptor;

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
     * @param callable(In, StreamInfo, Metadata, Cancellation): Out $handler
     * @return Out
     * @throws InvokeError
     * @throws CancelledException
     */
    public function intercept(
        object $request,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $handler,
    ): object {
        $stack = array_reduce(
            array_reverse($this->interceptors),
            static fn(callable $next, UnaryInterceptor $interceptor) => static fn(
                object $request,
                StreamInfo $info,
                Metadata $md,
                Cancellation $cancellation,
            ) => $interceptor->interceptUnary(
                $request,
                $info,
                $md,
                $cancellation,
                $next, // @phpstan-ignore argument.type
            ),
            $handler,
        );

        /** @var Out */
        return $stack($request, $info, $md, $cancellation);
    }
}
