<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Amp\CancelledException;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handle;
use Thesis\Grpc\Server\Interceptor;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class SuppressExceptionInterceptor implements Interceptor
{
    #[\Override]
    public function intercept(
        Handle $handle,
        Metadata $md,
        ServerStream $stream,
        Cancellation $cancellation,
        callable $next,
    ): void {
        try {
            $next($handle, $md, $stream, $cancellation);
        } catch (CancelledException) {
            $stream
                ->trailers
                ->join(new Metadata()->withKey(Metadata\StatusCode::DEADLINE_EXCEEDED));
        } catch (\Throwable) {
            $stream
                ->trailers
                ->join(new Metadata()->withKey(Metadata\StatusCode::INTERNAL));
        } finally {
            $stream->close();
        }
    }
}
