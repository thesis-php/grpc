<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
interface UnaryInterceptor
{
    /**
     * @template In of object
     * @template Out of object
     * @param In $request
     * @param Invoke<In, Out> $invoke
     * @param callable(In, Invoke<In, Out>, Metadata, Cancellation): Out $invoker
     * @return Out
     */
    public function interceptUnary(
        object $request,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $invoker,
    ): object;
}
