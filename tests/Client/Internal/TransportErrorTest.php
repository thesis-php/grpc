<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\ByteStream\StreamException;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\SocketException;
use Amp\Http\Http2\Http2ConnectionException;
use Amp\Http\Http2\Http2Parser;
use Amp\Http\Http2\Http2StreamException;
use Amp\NullCancellation;
use Amp\Socket\ConnectException;
use Amp\TimeoutException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\InvokeError;

#[CoversClass(TransportError::class)]
final class TransportErrorTest extends TestCase
{
    #[DataProvider('provideTransportErrorCases')]
    public function testTransportError(\Throwable $e, Code $code): void
    {
        $error = TransportError::from($e, new NullCancellation());

        self::assertSame($code, $error->statusCode);
        self::assertSame($e, $error->getPrevious());
    }

    /**
     * @return iterable<array-key, array{\Throwable, Code}>
     */
    public static function provideTransportErrorCases(): iterable
    {
        foreach ([
            Http2Parser::GRACEFUL_SHUTDOWN => Code::INTERNAL,
            Http2Parser::PROTOCOL_ERROR => Code::INTERNAL,
            Http2Parser::INTERNAL_ERROR => Code::INTERNAL,
            Http2Parser::FLOW_CONTROL_ERROR => Code::INTERNAL,
            Http2Parser::SETTINGS_TIMEOUT => Code::INTERNAL,
            Http2Parser::STREAM_CLOSED => Code::INTERNAL,
            Http2Parser::FRAME_SIZE_ERROR => Code::INTERNAL,
            Http2Parser::REFUSED_STREAM => Code::UNAVAILABLE,
            Http2Parser::CANCEL => Code::CANCELLED,
            Http2Parser::COMPRESSION_ERROR => Code::INTERNAL,
            Http2Parser::CONNECT_ERROR => Code::INTERNAL,
            Http2Parser::ENHANCE_YOUR_CALM => Code::RESOURCE_EXHAUSTED,
            Http2Parser::INADEQUATE_SECURITY => Code::PERMISSION_DENIED,
            Http2Parser::HTTP_1_1_REQUIRED => Code::INTERNAL,
            0xFF => Code::INTERNAL,
        ] as $http2 => $code) {
            yield "RST_STREAM {$http2}" => [self::reset($http2), $code];
        }

        yield 'reset in the body' => [new StreamException('HTTP response did not complete', previous: self::reset(Http2Parser::CANCEL)), Code::CANCELLED];

        yield 'connection error' => [new SocketException('Connection error', Http2Parser::PROTOCOL_ERROR, new Http2ConnectionException('Protocol error', Http2Parser::PROTOCOL_ERROR)), Code::UNAVAILABLE];

        yield 'connection lost' => [new SocketException('Connection closed unexpectedly', Http2Parser::INTERNAL_ERROR), Code::UNAVAILABLE];

        yield 'connection lost in the body' => [new StreamException('HTTP response did not complete', previous: new SocketException('Connection closed unexpectedly')), Code::UNAVAILABLE];

        yield 'connection refused' => [new SocketException('Connection refused'), Code::UNAVAILABLE];

        yield 'connect failed' => [new ConnectException('Connection refused'), Code::UNAVAILABLE];

        yield 'other http error' => [new HttpException('Invalid response'), Code::INTERNAL];

        yield 'cancelled' => [new CancelledException(), Code::CANCELLED];

        yield 'deadline' => [new CancelledException(new TimeoutException()), Code::DEADLINE_EXCEEDED];
    }

    public function testInvokeErrorIsKept(): void
    {
        $e = new InvokeError(Code::NOT_FOUND);

        self::assertSame($e, TransportError::from($e, new NullCancellation()));
    }

    public function testRequestedCancellationWins(): void
    {
        $cancellation = new DeferredCancellation();
        $cancellation->cancel(new TimeoutException());

        $error = TransportError::from(new SocketException('Connection closed unexpectedly'), $cancellation->getCancellation());

        self::assertSame(Code::DEADLINE_EXCEEDED, $error->statusCode);
    }

    private static function reset(int $code): SocketException
    {
        return new SocketException('Stream closed by server', $code, new Http2StreamException('Stream closed by server', 1, $code));
    }
}
