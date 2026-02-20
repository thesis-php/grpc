<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Amp\Cancellation;
use Thesis\Grpc\Client\Interceptor;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

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
            ->withKey(new Metadata\ContentType($this->encoding))
            ->withKey(Metadata\UserAgent::Key)
            ->withKey(new Metadata\ContentEncoding($this->compression))
            ->with('TE', 'trailers');

        return $next($invoke, $md, $cancellation);
    }
}
