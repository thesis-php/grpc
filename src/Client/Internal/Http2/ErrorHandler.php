<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Google\Rpc;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Status;
use Thesis\Protobuf\Decoder;

/**
 * @internal
 */
final readonly class ErrorHandler
{
    public function __construct(
        private Decoder $decoder,
    ) {}

    /**
     * @param ClientStream<*, *> $stream
     * @throws Decoder\DecodingError
     */
    public function obtain(ClientStream $stream): ?InvokeError
    {
        $context = Status\deserializeContext($stream->headers()->merge($stream->trailers()), $this->decoder);

        if ($context->code !== Rpc\Code::OK) {
            return new InvokeError(
                $context->code,
                $context->message,
                $context->details,
            );
        }

        return null;
    }
}
