<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

use Amp\ByteStream\ReadableBuffer;
use Amp\NullCancellation;
use Amp\Pipeline\Pipeline;
use Echos\Api\V1\EchoRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Compression\CompressionUnavailable;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Compression\DeflateCompressor;
use Thesis\Grpc\Compression\GzipCompressor;
use Thesis\Grpc\Compression\IdentityCompressor;
use Thesis\Grpc\Protobuf\ProtobufEncoder;

#[CoversClass(StreamCodec::class)]
final class StreamCodecTest extends TestCase
{
    /**
     * @param ?non-empty-string $encoding
     */
    #[DataProvider('provideDecodeCases')]
    public function testDecode(Compressor $sender, StreamCodec $codec, ?string $encoding): void
    {
        $messages = [
            new EchoRequest('first'),
            new EchoRequest('second'),
        ];

        $frames = implode('', iterator_to_array(
            new StreamCodec(ProtobufEncoder::default(), $sender)->encode(
                Pipeline::fromIterable($messages)->getIterator(),
                new NullCancellation(),
            ),
            preserve_keys: false,
        ));

        self::assertEquals($messages, iterator_to_array(
            $codec->decode(new ReadableBuffer($frames), EchoRequest::class, new NullCancellation(), $encoding),
            preserve_keys: false,
        ));
    }

    /**
     * @return iterable<string, array{Compressor, StreamCodec, ?non-empty-string}>
     */
    public static function provideDecodeCases(): iterable
    {
        yield 'default compressor' => [
            new GzipCompressor(),
            new StreamCodec(ProtobufEncoder::default(), new GzipCompressor()),
            null,
        ];

        yield 'own compressor by encoding' => [
            new GzipCompressor(),
            new StreamCodec(ProtobufEncoder::default(), new GzipCompressor()),
            'gzip',
        ];

        yield 'additional compressor by encoding' => [
            new DeflateCompressor(),
            new StreamCodec(ProtobufEncoder::default(), IdentityCompressor::Compressor, [new GzipCompressor(), new DeflateCompressor()]),
            'deflate',
        ];

        yield 'identity by encoding' => [
            IdentityCompressor::Compressor,
            new StreamCodec(ProtobufEncoder::default(), new GzipCompressor(), [IdentityCompressor::Compressor]),
            'identity',
        ];
    }

    public function testDecodeUnknownEncoding(): void
    {
        $codec = new StreamCodec(ProtobufEncoder::default(), IdentityCompressor::Compressor, [new GzipCompressor()]);

        $this->expectExceptionObject(new CompressionUnavailable('snappy'));
        $codec->decode(new ReadableBuffer(), EchoRequest::class, new NullCancellation(), 'snappy');
    }
}
