<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Http\HPack;
use Amp\Http\Http2\Http2Parser;
use Amp\Socket\Socket;
use Chat\Api\V1\Message;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoServiceClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Client\Internal\Http2\ConcurrentClientStream;
use Thesis\Grpc\Client\Internal\Http2\ErrorHandler;
use Thesis\Grpc\Client\Internal\TransportError;
use function Amp\async;
use function Amp\Socket\listen;

#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(ConcurrentClientStream::class)]
#[CoversClass(ErrorHandler::class)]
#[CoversClass(TransportError::class)]
final class TransportErrorTest extends TestCase
{
    private const string ADDRESS = '127.0.0.1:50076';

    /**
     * @param \Closure(Socket): void $respond
     * @param \Closure(Client): mixed $call
     */
    #[DataProvider('provideTransportErrorCases')]
    public function testTransportError(\Closure $respond, \Closure $call, Code $code): void
    {
        $server = listen(self::ADDRESS);
        async(static function () use ($server, $respond): void {
            while (($socket = $server->accept()) !== null) {
                async(self::serve(...), $socket, $respond)->ignore();
            }
        })->ignore();

        $client = new Client\Builder()->withHost(self::ADDRESS)->build();

        try {
            $call($client);
            self::fail('Expected an InvokeError.');
        } catch (InvokeError $e) {
            self::assertSame($code, $e->statusCode, $e->getMessage());
        } finally {
            $client->close();
            $server->close();
        }
    }

    /**
     * @return iterable<array-key, array{\Closure(Socket): void, \Closure(Client): mixed, Code}>
     */
    public static function provideTransportErrorCases(): iterable
    {
        $calls = [
            'unary' => static fn(Client $client) => new EchoServiceClient($client)->echo(new EchoRequest('ping')),
            'receive' => static fn(Client $client) => self::chat($client)->receive(),
            'foreach' => static function (Client $client): void {
                foreach (self::chat($client) as $_);
            },
        ];

        $resets = [
            'headers' => static fn(Client $client) => self::chat($client)->headers(),
            'trailers' => static fn(Client $client) => self::chat($client)->trailers(),
        ];

        $scenarios = [
            'RST_STREAM(CANCEL)' => [self::reset(Http2Parser::CANCEL), Code::CANCELLED, [...$calls, ...$resets]],
            'RST_STREAM(ENHANCE_YOUR_CALM)' => [self::reset(Http2Parser::ENHANCE_YOUR_CALM), Code::RESOURCE_EXHAUSTED, [...$calls, ...$resets]],
            'GOAWAY' => [
                static fn(Socket $socket) => $socket->write(Http2Parser::compileFrame(pack('NN', 0, Http2Parser::GRACEFUL_SHUTDOWN), Http2Parser::GOAWAY, Http2Parser::NO_FLAG)),
                Code::UNAVAILABLE,
                $calls,
            ],
            'connection closed' => [static fn(Socket $socket) => $socket->close(), Code::UNAVAILABLE, $calls],
            'HTTP 503' => [self::httpStatus(503), Code::UNAVAILABLE, $calls],
            'HTTP 404' => [self::httpStatus(404), Code::UNIMPLEMENTED, $calls],
        ];

        foreach ($scenarios as $scenario => [$respond, $code, $cases]) {
            foreach ($cases as $case => $call) {
                yield "{$scenario} on {$case}" => [$respond, $call, $code];
            }
        }
    }

    /**
     * A bare HTTP/2 server: it acknowledges the client's settings and answers the first stream with $respond
     * once the request is sent. An answer to an unfinished request trips amphp/http-client < 5.3.7, which leaves
     * the failed request body future unhandled (fixed upstream in 423f023).
     *
     * @param \Closure(Socket): void $respond
     */
    private static function serve(Socket $socket, \Closure $respond): void
    {
        $buffer = '';
        $read = static function (int $length) use ($socket, &$buffer): ?string {
            while (\strlen($buffer) < $length) {
                if (($chunk = $socket->read()) === null) {
                    return null;
                }

                $buffer .= $chunk;
            }

            $data = substr($buffer, 0, $length);
            $buffer = substr($buffer, $length);

            return $data;
        };

        if ($read(\strlen(Http2Parser::PREFACE)) === null) {
            return;
        }

        $socket->write(Http2Parser::compileFrame('', Http2Parser::SETTINGS, Http2Parser::NO_FLAG));

        while (($header = $read(9)) !== null) {
            /** @var array{length: int, type: int, flags: int, stream: int} $frame */
            $frame = unpack('Nlength/Ctype/Cflags/Nstream', "\0{$header}");

            if ($read($frame['length']) === null) {
                return;
            }

            if ($frame['type'] === Http2Parser::SETTINGS && ($frame['flags'] & Http2Parser::ACK) === 0) {
                $socket->write(Http2Parser::compileFrame('', Http2Parser::SETTINGS, Http2Parser::ACK));
            }

            if ($frame['stream'] === 1 && ($frame['flags'] & Http2Parser::END_STREAM) !== 0) {
                $respond($socket);
            }
        }
    }

    /**
     * @return \Closure(Socket): void
     */
    private static function reset(int $code): \Closure
    {
        return static fn(Socket $socket) => $socket->write(Http2Parser::compileFrame(pack('N', $code), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 1));
    }

    /**
     * @return \Closure(Socket): void
     */
    private static function httpStatus(int $status): \Closure
    {
        return static fn(Socket $socket) => $socket->write(Http2Parser::compileFrame(
            new HPack()->encode([[':status', (string) $status]]),
            Http2Parser::HEADERS,
            Http2Parser::END_HEADERS | Http2Parser::END_STREAM,
            1,
        ));
    }

    /**
     * @return ClientStream<Message, Message>
     */
    private static function chat(Client $client): ClientStream
    {
        /** @var Client\Invoke<Message, Message> $invoke */
        $invoke = new Client\Invoke('/chat.api.v1.MessengerService/Chat', Message::class, RpcType::BidirectionalStream);

        $stream = $client->createStream($invoke);
        $stream->send(new Message('hello'));
        $stream->close();

        return $stream;
    }
}
