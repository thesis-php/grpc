<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;

#[CoversFunction('Thesis\Grpc\Internal\Http2\encodeMetadata')]
#[CoversFunction('Thesis\Grpc\Internal\Http2\decodeMetadata')]
final class HeadersTest extends TestCase
{
    /**
     * @param array<non-empty-string, list<string>> $headers
     */
    #[DataProvider('provideEncodeCases')]
    public function testEncode(Metadata $md, array $headers): void
    {
        self::assertSame($headers, encodeMetadata($md));
    }

    /**
     * @return iterable<array-key, array{Metadata, array<non-empty-string, list<string>>}>
     */
    public static function provideEncodeCases(): iterable
    {
        yield 'binary without padding' => [
            new Metadata(['x-test-bin' => "\xab\xab\xab"]),
            ['x-test-bin' => ['q6ur']],
        ];

        yield 'padding is stripped' => [
            new Metadata(['x-test-bin' => ["\xab", "\xab\xab"]]),
            ['x-test-bin' => ['qw', 'q6s']],
        ];

        yield 'text is untouched' => [
            new Metadata(['x-test' => "\xab", 'x-test-bin-suffix' => 'q6ur']),
            ['x-test' => ["\xab"], 'x-test-bin-suffix' => ['q6ur']],
        ];
    }

    /**
     * @param array<non-empty-string, list<string>> $headers
     */
    #[DataProvider('provideDecodeCases')]
    public function testDecode(array $headers, Metadata $md): void
    {
        self::assertEquals($md, decodeMetadata($headers));
    }

    /**
     * @return iterable<array-key, array{array<non-empty-string, list<string>>, Metadata}>
     */
    public static function provideDecodeCases(): iterable
    {
        yield 'without padding' => [
            ['x-test-bin' => ['q6ur', 'q6s']],
            new Metadata(['x-test-bin' => ["\xab\xab\xab", "\xab\xab"]]),
        ];

        yield 'with padding' => [
            ['x-test-bin' => ['q6s=', 'qw==']],
            new Metadata(['x-test-bin' => ["\xab\xab", "\xab"]]),
        ];

        yield 'comma separated values' => [
            ['x-test-bin' => ['q6ur,q6s=', 'qw']],
            new Metadata(['x-test-bin' => ["\xab\xab\xab", "\xab\xab", "\xab"]]),
        ];

        yield 'text is untouched' => [
            ['x-test' => ['q6ur,q6s']],
            new Metadata(['x-test' => ['q6ur,q6s']]),
        ];
    }

    #[DataProvider('provideDecodeMalformedCases')]
    public function testDecodeMalformed(string $value): void
    {
        $this->expectExceptionObject(new InvokeError(Code::INTERNAL, 'Malformed binary metadata in header "x-test-bin"'));
        decodeMetadata(['x-test-bin' => [$value]]);
    }

    /**
     * @return iterable<array-key, array{string}>
     */
    public static function provideDecodeMalformedCases(): iterable
    {
        yield 'invalid character' => ['q6u!'];
        yield 'excessive padding' => ['q6ur=='];
        yield 'truncated' => ['q'];
    }
}
