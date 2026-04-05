<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Protocol;

use Thesis\Grpc\Compression;
use Thesis\Grpc\Encoding;

/**
 * @internal
 * @template T of object
 */
final class Parser
{
    private string $buffer = '';

    /**
     * @param \Closure(T): void $push
     * @param class-string<T> $type
     */
    public function __construct(
        private readonly \Closure $push,
        private readonly string $type,
        private readonly Encoding\Encoder $encoder,
        private readonly Compression\Compressor $compressor,
    ) {}

    /**
     * @throws Compression\DecompressionFailed
     * @throws Encoding\DecodingFailed
     */
    public function push(string $data): void
    {
        $this->buffer .= $data;

        while (\strlen($this->buffer) >= bodyOffset) {
            $messageLength = byteOrder->unpackUint32(
                /** @phpstan-ignore argument.type */
                substr($this->buffer, lengthOffset, 4),
            );

            $frameSize = bodyOffset + $messageLength;

            if (\strlen($this->buffer) < $frameSize) {
                break;
            }

            $frame = decodeFrame(substr($this->buffer, 0, $frameSize));
            $this->buffer = substr($this->buffer, $frameSize);

            $buffer = $frame->buffer;

            if ($frame->compressed && $buffer !== '') {
                $buffer = $this->compressor->decompress($buffer);
            }

            ($this->push)($this->encoder->decode($buffer, $this->type));
        }
    }
}
