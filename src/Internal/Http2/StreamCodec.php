<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

use Amp\Pipeline;
use Revolt\EventLoop;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Internal\Protocol;

/**
 * @internal
 */
final readonly class StreamCodec
{
    public function __construct(
        private Encoder $encoder,
        private Compressor $compressor,
    ) {}

    /**
     * @template T of object
     * @param Pipeline\ConcurrentIterator<T> $in
     * @return Pipeline\ConcurrentIterator<non-empty-string>
     */
    public function encode(Pipeline\ConcurrentIterator $in): Pipeline\ConcurrentIterator
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

        return $out->iterate();
    }

    /**
     * @template T of object
     * @param \Traversable<string> $in
     * @param class-string<T> $type
     * @return Pipeline\ConcurrentIterator<T>
     */
    public function decode(\Traversable $in, string $type): Pipeline\ConcurrentIterator
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
            $type,
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

                try {
                    $out->push($encoder->decode($buffer, $type));
                } catch (Pipeline\DisposedException) {
                    // If an exception occurs in the server’s interceptor stack or in the server’s request handler,
                    // causing us to stop reading the incoming stream, we lose control over the iterator of that queue.
                    // Therefore, it is normal to handle such an exception here.
                    return;
                }
            }

            $out->complete();
        });

        return $out->iterate();
    }
}
