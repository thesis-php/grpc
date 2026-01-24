<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Amp\Cancellation;
use Thesis\Grpc\Client\Interceptor;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;
use Thesis\Package;

/**
 * @internal
 */
final readonly class AppendControlMetadataInterceptor implements Interceptor
{
    /**
     * @param non-empty-string $encoding
     * @param non-empty-string $compression
     */
    public function __construct(
        private string $encoding,
        private string $compression,
    ) {}

    #[\Override]
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): ClientStream {
        $md = $md
            ->with('Content-Type', "application/grpc+{$this->encoding}")
            ->with('User-Agent', 'grpc-php-thesis/' . Package\version('thesis/grpc'))
            ->with('grpc-encoding', $this->compression)
            ->with('TE', 'trailers');

        return $next($invoke, $md, $cancellation);
    }
}
