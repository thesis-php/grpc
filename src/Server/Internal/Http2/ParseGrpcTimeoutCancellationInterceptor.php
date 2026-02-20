<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Amp\TimeoutCancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handle;
use Thesis\Grpc\Server\Interceptor;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class ParseGrpcTimeoutCancellationInterceptor implements Interceptor
{
    #[\Override]
    public function intercept(
        Handle $handle,
        Metadata $md,
        ServerStream $stream,
        Cancellation $cancellation,
        callable $next,
    ): void {
        $timeout = Metadata\parseTimeout($md);
        if ($timeout !== null) {
            $cancellation = new TimeoutCancellation($timeout->toSeconds());
        }

        $next($handle, $md, $stream, $cancellation);
    }
}
