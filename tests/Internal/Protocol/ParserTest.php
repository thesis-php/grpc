<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Protocol;

use Echos\Api\V1\EchoRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Compression\GzipCompressor;
use Thesis\Grpc\Compression\IdentityCompressor;
use Thesis\Grpc\Protobuf\ProtobufEncoder;

#[CoversClass(Parser::class)]
final class ParserTest extends TestCase
{
    /**
     * @param list<string> $chunks
     * @param list<EchoRequest> $expected
     */
    #[DataProvider('providePushCases')]
    public function testPush(array $chunks, array $expected, Compressor $compressor): void
    {
        $frames = [];

        $parser = new Parser(
            static function (EchoRequest $request) use (&$frames): void {
                $frames[] = $request;
            },
            EchoRequest::class,
            ProtobufEncoder::default(),
            $compressor,
        );

        foreach ($chunks as $chunk) {
            $parser->push($chunk);
        }

        self::assertEquals($expected, $frames);
    }

    /**
     * @return iterable<string, array{list<string>, list<EchoRequest>, Compressor}>
     */
    public static function providePushCases(): iterable
    {
        foreach ([IdentityCompressor::Compressor, new GzipCompressor()] as $compressor) {
            $name = $compressor->name();

            yield "{$name}: single complete frame" => [
                [self::frame('hello', $compressor)],
                [
                    new EchoRequest('hello'),
                ],
                $compressor,
            ];

            yield "{$name}: two frames in one chunk" => [
                [self::frame('first', $compressor) . self::frame('second', $compressor)],
                [
                    new EchoRequest('first'),
                    new EchoRequest('second'),
                ],
                $compressor,
            ];

            yield "{$name}: three frames in one chunk" => [
                [self::frame('a', $compressor) . self::frame('bb', $compressor) . self::frame('ccc', $compressor)],
                [
                    new EchoRequest('a'),
                    new EchoRequest('bb'),
                    new EchoRequest('ccc'),
                ],
                $compressor,
            ];

            yield "{$name}: frame split across two chunks" => [
                (static function () use ($compressor): array {
                    $frame = self::frame('whole', $compressor);
                    $mid = (int) (\strlen($frame) / 2);

                    return [substr($frame, 0, $mid), substr($frame, $mid)];
                })(),
                [
                    new EchoRequest('whole'),
                ],
                $compressor,
            ];

            yield "{$name}: one complete frame + half of second" => [
                (static function () use ($compressor): array {
                    $f1 = self::frame('complete', $compressor);
                    $f2 = self::frame('split', $compressor);
                    $mid = (int) (\strlen($f2) / 2);

                    return [$f1 . substr($f2, 0, $mid), substr($f2, $mid)];
                })(),
                [
                    new EchoRequest('complete'),
                    new EchoRequest('split'),
                ],
                $compressor,
            ];

            yield "{$name}: byte by byte" => [
                str_split(self::frame('slow', $compressor)),
                [
                    new EchoRequest('slow'),
                ],
                $compressor,
            ];

            yield "{$name}: empty payload" => [
                [encodeFrame(new Frame(false, ''))],
                [
                    new EchoRequest(),
                ],
                $compressor,
            ];
        }
    }

    private static function frame(string $sentence, Compressor $compressor = IdentityCompressor::Compressor): string
    {
        $payload = ProtobufEncoder::default()->encode(new EchoRequest($sentence));

        return encodeFrame(new Frame(
            $compressor->name() !== IdentityCompressor::Compressor->name(),
            $payload !== '' ? $compressor->compress($payload) : $payload,
        ));
    }
}
