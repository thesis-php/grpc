<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

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
     * @param callable(In, StreamInfo, Metadata, Cancellation, callable(In, StreamInfo, Metadata, Cancellation): Out): Out $handler
     */
    public function __construct(
        private mixed $handler,
    ) {}

    #[\Override]
    public function interceptUnary(
        object $request,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $handler,
    ): object {
        return ($this->handler)(
            $request,
            $info,
            $md,
            $cancellation,
            $handler,
        );
    }
}
