<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class CallableUnaryInterceptor implements UnaryInterceptor
{
    /**
     * @template In of object
     * @template Out of object
     * @param callable(In, Invoke<In, Out>, Metadata, Cancellation, callable(In, Invoke<In, Out>, Metadata, Cancellation): Out): Out $handler
     */
    public function __construct(
        private mixed $handler,
    ) {}

    #[\Override]
    public function interceptUnary(
        object $request,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $invoker,
    ): object {
        return ($this->handler)(
            $request,
            $invoke,
            $md,
            $cancellation,
            $invoker,
        );
    }
}
