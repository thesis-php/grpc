<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc;
use Thesis\Grpc\Metadata;

#[CoversClass(Status::class)]
#[CoversFunction('Thesis\Grpc\Metadata\parseStatus')]
final class StatusTest extends TestCase
{
    #[DataProvider('provideMessageCases')]
    public function testMessage(string $message, string $headerValue): void
    {
        $md = new Metadata()->withKey(new Status(Rpc\Code::INTERNAL, $message));

        self::assertSame($headerValue, $md->value(Status::MESSAGE_HEADER));
        self::assertSame($message, parseStatus($md)->message);
    }

    /**
     * @return iterable<array-key, array{string, string}>
     */
    public static function provideMessageCases(): iterable
    {
        yield 'ascii' => [
            'Unknown method Ping for service echos.api.v1.EchoService: [{}] ~!',
            'Unknown method Ping for service echos.api.v1.EchoService: [{}] ~!',
        ];

        yield 'percent' => [
            '100%',
            '100%25',
        ];

        yield 'range boundaries' => [
            " $%&~\x7f",
            ' $%25&~%7F',
        ];

        yield 'special status message' => [
            "\t\ntest with whitespace\r\nand Unicode BMP ☺ and non-BMP 😈\t\n",
            '%09%0Atest with whitespace%0D%0Aand Unicode BMP %E2%98%BA and non-BMP %F0%9F%98%88%09%0A',
        ];
    }

    #[DataProvider('provideParseMessageCases')]
    public function testParseMessage(string $headerValue, string $message): void
    {
        self::assertSame($message, parseStatus(new Metadata()->with(Status::MESSAGE_HEADER, $headerValue))->message);
    }

    /**
     * @return iterable<array-key, array{string, string}>
     */
    public static function provideParseMessageCases(): iterable
    {
        yield 'lowercase hex' => [
            '%e2%98%ba',
            '☺',
        ];

        yield 'invalid escape' => [
            '%zz',
            '%zz',
        ];

        yield 'trailing percent' => [
            'done 100%',
            'done 100%',
        ];

        yield 'plus is not a space' => [
            'a+b',
            'a+b',
        ];
    }

    public function testMissingMessage(): void
    {
        self::assertNull(parseStatus(new Metadata())->message);
    }
}
