<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Metadata;

#[CoversClass(Timeout::class)]
#[CoversFunction('Thesis\Grpc\Metadata\parseTimeout')]
final class TimeoutTest extends TestCase
{
    /**
     * @param non-empty-string $headerValue
     */
    #[DataProvider('provideTimeoutCases')]
    public function testTimeout(Timeout $timeout, string $headerValue, float $seconds): void
    {
        self::assertSame($headerValue, (string) $timeout);
        self::assertSame($seconds, $timeout->toSeconds());
    }

    /**
     * @return iterable<array-key, array{Timeout, non-empty-string, float}>
     */
    public static function provideTimeoutCases(): iterable
    {
        yield 'hours' => [
            Timeout::hours(1),
            '1H',
            3_600.0,
        ];

        yield 'minutes' => [
            Timeout::minutes(2),
            '2M',
            120.0,
        ];

        yield 'seconds' => [
            Timeout::seconds(123),
            '123S',
            123,
        ];

        yield 'milliseconds' => [
            Timeout::milliseconds(2_347),
            '2347m',
            2.347,
        ];

        yield 'microseconds' => [
            Timeout::microseconds(1_000_243),
            '1000243u',
            1.000_243,
        ];

        yield 'nanoseconds' => [
            Timeout::nanoseconds(1_000_000_900),
            '1000000900n',
            1.000_000_9,
        ];
    }

    #[DataProvider('provideParseTimeoutCases')]
    public function testParseTimeout(string $value, ?Timeout $timeout = null): void
    {
        self::assertEquals($timeout, parseTimeout(new Metadata()->with(Timeout::HEADER, $value)));
    }

    /**
     * @return iterable<array-key, array{string, ?Timeout}>
     */
    public static function provideParseTimeoutCases(): iterable
    {
        yield 'hours' => [
            '2H',
            Timeout::hours(2),
        ];

        yield 'minutes' => [
            '12M',
            Timeout::minutes(12),
        ];

        yield 'seconds' => [
            '60S',
            Timeout::seconds(60),
        ];

        yield 'milliseconds' => [
            '600m',
            Timeout::milliseconds(600),
        ];

        yield 'microseconds' => [
            '6000u',
            Timeout::microseconds(6_000),
        ];

        yield 'nanoseconds' => [
            '60000n',
            Timeout::nanoseconds(60_000),
        ];

        yield 'empty' => [
            '',
            null,
        ];

        yield 'too short' => [
            '1',
            null,
        ];

        yield 'too long' => [
            '123456789S',
            null,
        ];

        yield 'unknown unit' => [
            '1D',
            null,
        ];

        yield 'value is not a number' => [
            'iS',
            null,
        ];
    }

    public function testFromInterval(): void
    {
        $time = new \DateTimeImmutable('NOW');

        $timeout = Timeout::fromInterval(
            new \DateInterval('PT2S'),
            $time,
        );

        self::assertSame('2S', (string) $timeout);
        self::assertSame(2.0, $timeout->toSeconds());
    }

    public function testFromDateTime(): void
    {
        $time = new \DateTimeImmutable('NOW');

        $timeout = Timeout::fromDateTime(
            $time->add(new \DateInterval('PT2S')),
            $time,
        );

        self::assertSame('2S', (string) $timeout);
        self::assertSame(2.0, $timeout->toSeconds());
    }
}
