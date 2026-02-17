<?php

declare(strict_types=1);

namespace Thesis\Grpc\Protobuf;

use Thesis\Grpc\Encoding;
use Thesis\Protobuf;

/**
 * @api
 */
final readonly class ProtobufEncoder implements Encoding\Encoder
{
    public function __construct(
        private Protobuf\Encoder $encoder,
        private Protobuf\Decoder $decoder,
    ) {}

    public static function default(): self
    {
        return new self(
            Protobuf\Encoder\Builder::buildDefault(),
            Protobuf\Decoder\Builder::buildDefault(),
        );
    }

    #[\Override]
    public function name(): string
    {
        return 'proto';
    }

    #[\Override]
    public function encode(object $request): string
    {
        try {
            return $this->encoder->encode($request);
        } catch (Protobuf\Encoder\EncodingError $e) {
            throw new Encoding\EncodingFailed($e->getMessage(), $e->getCode(), $e);
        }
    }

    #[\Override]
    public function decode(string $buffer, string $classType): object
    {
        try {
            return $this->decoder->decode($buffer, $classType);
        } catch (Protobuf\Decoder\DecodingError $e) {
            throw new Encoding\DecodingFailed($e->getMessage(), $e->getCode(), $e);
        }
    }
}
