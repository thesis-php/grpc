<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\StreamInterceptor;
use Thesis\Grpc\Client\UnaryInterceptor;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @internal
 */
final readonly class AppendControlMetadataInterceptor implements
    UnaryInterceptor,
    StreamInterceptor
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
    public function interceptUnary(
        object $request,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $invoker,
    ): object {
        return $invoker($request, $invoke, $this->decorate($md), $cancellation);
    }

    #[\Override]
    public function interceptStream(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $newStream,
    ): ClientStream {
        return $newStream($invoke, $this->decorate($md), $cancellation);
    }

    private function decorate(Metadata $md): Metadata
    {
        return $md
            ->withKey(new Metadata\ContentType($this->encoding))
            ->withKey(Metadata\UserAgent::Key)
            ->withKey(new Metadata\ContentEncoding($this->compression))
            ->with('TE', 'trailers');
    }
}
