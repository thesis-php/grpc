<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Protocol;

use Thesis\Endian;

/**
 * @internal
 */
final readonly class Frame
{
    public function __construct(
        public bool $compressed,
        #[\SensitiveParameter]
        public string $buffer,
    ) {}
}

const byteOrder = Endian\Order::Big;

/**
 * @internal
 * @return non-empty-string
 */
function encodeFrame(Frame $frame): string
{
    return byteOrder->packInt8((int) $frame->compressed)
        /** @phpstan-ignore argument.type */
        . byteOrder->packUint32(\strlen($frame->buffer))
        . $frame->buffer;
}

const compressedOffset = 0;
const lengthOffset = 1;
const bodyOffset = 5;

/**
 * @internal
 * @param non-empty-string $buffer
 */
function decodeFrame(#[\SensitiveParameter] string $buffer): Frame
{
    \assert(\strlen($buffer) >= bodyOffset, 'The buffer is not a valid gRPC frame.');

    $compressed = (bool) byteOrder->unpackInt8($buffer[compressedOffset]);

    $length = byteOrder->unpackUint32(
        /** @phpstan-ignore argument.type */
        substr($buffer, lengthOffset, 4),
    );

    return new Frame(
        $compressed,
        substr($buffer, bodyOffset, $length),
    );
}
