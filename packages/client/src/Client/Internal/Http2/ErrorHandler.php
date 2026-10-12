<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Amp\Http\Client\Response;
use Thesis\Google\Rpc;
use Thesis\Grpc\Internal\Http2;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
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
     * @throws Decoder\DecodingError
     */
    public function obtain(Response $response): ?InvokeError
    {
        $md = Http2\decodeMetadata($response->getHeaders())->merge(Http2\decodeMetadata($response->getTrailers()->await()->getHeaders()));

        // A response without a gRPC status, e.g. from a proxy, is mapped by its HTTP status as PROTOCOL-HTTP2.md requires.
        if ($md->value(Metadata\Status::STATUS_HEADER) === null && ($status = $response->getStatus()) !== 200) {
            return new InvokeError(
                match ($status) {
                    400 => Rpc\Code::INTERNAL,
                    401 => Rpc\Code::UNAUTHENTICATED,
                    403 => Rpc\Code::PERMISSION_DENIED,
                    404 => Rpc\Code::UNIMPLEMENTED,
                    429, 502, 503, 504 => Rpc\Code::UNAVAILABLE,
                    default => Rpc\Code::UNKNOWN,
                },
                "unexpected HTTP status code received from server: {$status}",
            );
        }

        $context = Status\deserializeContext($md, $this->decoder);

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
