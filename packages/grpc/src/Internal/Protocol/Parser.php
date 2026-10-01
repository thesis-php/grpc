<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Protocol;

use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Compression;
use Thesis\Grpc\Encoding;
use Thesis\Grpc\InvokeError;

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
     * @param positive-int $maxMessageSize
     */
    public function __construct(
        private readonly \Closure $push,
        private readonly string $type,
        private readonly Encoding\Encoder $encoder,
        private readonly Compression\Compressor $compressor,
        private readonly int $maxMessageSize,
    ) {}

    /**
     * @throws Compression\DecompressionFailed
     * @throws Encoding\DecodingFailed
     * @throws InvokeError
     */
    public function push(string $data): void
    {
        $this->buffer .= $data;

        while (\strlen($this->buffer) >= bodyOffset) {
            $messageLength = byteOrder->unpackUint32(
                /** @phpstan-ignore argument.type */
                substr($this->buffer, lengthOffset, 4),
            );

            if ($messageLength > $this->maxMessageSize) {
                throw new InvokeError(Code::RESOURCE_EXHAUSTED, "Received message larger than max ({$messageLength} vs. {$this->maxMessageSize})");
            }

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
