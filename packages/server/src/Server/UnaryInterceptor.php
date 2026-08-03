<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
use Amp\CancelledException;
use Thesis\Grpc\InvokeError;
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
     * @param callable(In, StreamInfo, Metadata, Cancellation): Out $handler
     * @return Out
     * @throws InvokeError
     * @throws CancelledException
     */
    public function interceptUnary(
        object $request,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $handler,
    ): object;
}
