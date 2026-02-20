<?php

declare(strict_types=1);

namespace Thesis\Grpc\Status;

use Google\Protobuf;
use Google\Rpc;
use Thesis\Grpc\Metadata;
use Thesis\Protobuf\Decoder;
use Thesis\Protobuf\Encoder;

/**
 * @api
 */
final readonly class Context
{
    /**
     * @param list<object> $details
     */
    public function __construct(
        public Rpc\Code $code,
        public ?string $message = null,
        public array $details = [],
    ) {}
}

/**
 * @internal
 * @throws Encoder\EncodingError
 */
function serializeContext(Context $context, Encoder $protobuf): Metadata\Status
{
    if ($context->details === []) {
        return new Metadata\Status($context->code, $context->message);
    }

    $status = new Rpc\Status(
        $context->code->value,
        $context->message ?? '',
        array_map(
            static fn(object $detail) => Protobuf\encodeAny($detail, $protobuf),
            $context->details,
        ),
    );

    return new Metadata\Status(
        $context->code,
        $context->message,
        base64_encode($protobuf->encode($status)),
    );
}

/**
 * @internal
 * @throws Decoder\DecodingError
 */
function deserializeContext(Metadata $md, Decoder $protobuf): Context
{
    $status = Metadata\parseStatus($md);

    $details = [];

    if (($bin = $status->details) !== null) {
        $decoded = base64_decode($bin, true);
        if ($decoded !== false) {
            $details = array_map(
                static fn(Protobuf\Any $detail) => Protobuf\decodeAny($detail, $protobuf),
                $protobuf->decode($decoded, Rpc\Status::class)->details,
            );
        }
    }

    return new Context(
        $status->code,
        $status->message,
        $details,
    );
}
