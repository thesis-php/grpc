<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Invoke;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class AppendMetadataInterceptor implements Interceptor
{
    public function __construct(
        private Metadata $md,
    ) {}

    #[\Override]
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): ClientStream {
        return $next($invoke, $md->merge($this->md), $cancellation);
    }
}
