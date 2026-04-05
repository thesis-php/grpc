<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\CancelledException;
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
    public function encode(
        Pipeline\ConcurrentIterator $in,
        Cancellation $cancellation,
    ): Pipeline\ConcurrentIterator {
        /** @var Pipeline\Queue<non-empty-string> $out */
        $out = new Pipeline\Queue();

        $encoder = $this->encoder;
        $compressor = $this->compressor;

        EventLoop::queue(static function () use (
            $encoder,
            $compressor,
            $in,
            $out,
            $cancellation,
        ): void {
            try {
                while ($in->continue($cancellation)) {
                    $message = $in->getValue();

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
            } catch (Pipeline\DisposedException|CancelledException) {
            } catch (\Throwable $e) {
                $out->error($e);
            } finally {
                if (!$out->isComplete()) {
                    $out->complete();
                }
            }
        });

        return $out->iterate();
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return Pipeline\ConcurrentIterator<T>
     */
    public function decode(
        ReadableStream $in,
        string $type,
        Cancellation $cancellation,
    ): Pipeline\ConcurrentIterator {
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
            $cancellation,
        ): void {
            try {
                while (($message = $in->read($cancellation)) !== null) {
                    \assert($message !== '', 'gRPC frame must not be empty.');

                    $frame = Protocol\decodeFrame($message);

                    $buffer = $frame->buffer;

                    if ($frame->compressed && $buffer !== '') {
                        $buffer = $compressor->decompress($buffer);
                    }

                    $out->push($encoder->decode($buffer, $type));
                }
            } catch (Pipeline\DisposedException|CancelledException) {
            } catch (\Throwable $e) {
                $out->error($e);
            } finally {
                if (!$out->isComplete()) {
                    $out->complete();
                }
            }
        });

        return $out->iterate();
    }
}
