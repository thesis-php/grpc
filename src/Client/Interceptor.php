<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @api
 * @phpstan-type Handler = callable(Invoke, Metadata, Cancellation): ClientStream<*, *>
 */
interface Interceptor
{
    /**
     * @param Handler $next
     * @return ClientStream<*, *>
     */
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): ClientStream;
}
