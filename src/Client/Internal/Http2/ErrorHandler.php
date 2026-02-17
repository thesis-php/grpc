<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Google\Protobuf;
use Google\Rpc\Code;
use Google\Rpc\Status;
use Thesis\Grpc\Client\CallError;
use Thesis\Grpc\Metadata;
use Thesis\Protobuf\Decoder;

/**
 * @internal
 */
final readonly class ErrorHandler
{
    public function __construct(
        private Decoder $decoder,
    ) {}

    public function handle(
        Metadata $headers,
        Metadata $trailers,
    ): ?CallError {
        $md = $headers->merge($trailers);

        $statusMetadata = $md->status();

        $code = $statusMetadata->code;
        $message = $statusMetadata->message;

        if ($code === Code::OK) {
            return null;
        }

        $details = [];

        if (($bin = $md->value('grpc-status-details-bin')) !== null) {
            $decoded = base64_decode($bin, true);
            if ($decoded !== false) {
                $status = $this->decoder->decode($decoded, Status::class);

                $code = Code::tryFrom($status->code) ?? Code::UNKNOWN;
                $message = $status->message !== '' ? $status->message : null;
                $details = array_map(
                    fn(Protobuf\Any $detail) => Protobuf\decodeAny($detail, $this->decoder),
                    $status->details,
                );
            }
        }

        return new CallError(
            $code,
            $message,
            $details,
        );
    }
}
