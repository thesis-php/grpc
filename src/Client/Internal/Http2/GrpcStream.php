<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Amp\ByteStream\ReadableIterableStream;
use Amp\Http\Client\HttpContent;
use Amp\Http\Client\StreamedContent;
use Amp\Pipeline;
use Revolt\EventLoop;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Internal\Protocol;

/**
 * @internal
 */
final readonly class GrpcStream
{
    public function __construct(
        private Encoder $encoder,
        private Compressor $compressor,
    ) {}

    /**
     * @template T of object
     * @param Pipeline\ConcurrentIterator<T> $in
     */
    public function encode(Pipeline\ConcurrentIterator $in): HttpContent
    {
        /** @var Pipeline\Queue<non-empty-string> $out */
        $out = new Pipeline\Queue();

        $encoder = $this->encoder;
        $compressor = $this->compressor;

        EventLoop::queue(static function () use (
            $encoder,
            $compressor,
            $in,
            $out,
        ): void {
            foreach ($in as $message) {
                $buffer = $compressed = $encoder->encode($message);

                if ($buffer !== '') {
                    $compressed = $compressor->compress($buffer);
                }

                $frame = Protocol\encodeFrame(new Protocol\Frame(
                    $compressed !== $buffer,
                    $compressed,
                ));

                $out->push($frame);
            }

            $out->complete();
        });

        return StreamedContent::fromStream(
            new ReadableIterableStream($out->iterate()),
        );
    }

    /**
     * @template T of object
     * @param \Traversable<string> $in
     * @param class-string<T> $classType
     * @return Pipeline\ConcurrentIterator<T>
     */
    public function decode(\Traversable $in, string $classType): Pipeline\ConcurrentIterator
    {
        /** @var Pipeline\Queue<T> $out */
        $out = new Pipeline\Queue();

        $encoder = $this->encoder;
        $compressor = $this->compressor;

        EventLoop::queue(static function () use (
            $encoder,
            $compressor,
            $in,
            $out,
            $classType,
        ): void {
            /** @var string $message */
            foreach ($in as $message) {
                if ($message === '') {
                    continue;
                }

                $frame = Protocol\decodeFrame($message);

                $buffer = $frame->buffer;

                if ($frame->compressed && $buffer !== '') {
                    $buffer = $compressor->decompress($buffer);
                }

                $out->push($encoder->decode($buffer, $classType));
            }

            $out->complete();
        });

        return $out->iterate();
    }
}
