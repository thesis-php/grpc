<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Pipeline;
use Revolt\EventLoop;
use Thesis\Grpc\Compression\CompressionUnavailable;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Internal\Protocol;

/**
 * @internal
 */
final readonly class StreamCodec
{
    /** @var array<non-empty-string, Compressor> */
    private array $compressors;

    /**
     * @param list<Compressor> $compressors to decompress messages with, selected by the peer's "grpc-encoding"
     */
    public function __construct(
        private Encoder $encoder,
        private Compressor $compressor,
        array $compressors = [],
    ) {
        $compressors = [$compressor, ...$compressors];

        $this->compressors = array_combine(
            array_map(static fn(Compressor $compressor) => $compressor->name(), $compressors),
            $compressors,
        );
    }

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
     * @param ?non-empty-string $encoding
     * @return Pipeline\ConcurrentIterator<T>
     * @throws CompressionUnavailable
     */
    public function decode(
        ReadableStream $in,
        string $type,
        Cancellation $cancellation,
        ?string $encoding = null,
    ): Pipeline\ConcurrentIterator {
        $compressor = $encoding === null
            ? $this->compressor
            : $this->compressors[$encoding] ?? throw new CompressionUnavailable($encoding);

        /** @var Pipeline\Queue<T> $out */
        $out = new Pipeline\Queue();

        $parser = new Protocol\Parser(
            $out->push(...),
            $type,
            $this->encoder,
            $compressor,
        );

        EventLoop::queue(static function () use (
            $parser,
            $in,
            $out,
            $cancellation,
        ): void {
            try {
                while (($chunk = $in->read($cancellation)) !== null) {
                    $parser->push($chunk);
                }
            } catch (Pipeline\DisposedException|CancelledException) { // @phpstan-ignore catch.neverThrown, catch.neverThrown
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
