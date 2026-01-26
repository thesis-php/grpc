<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/protobuf.php';
require_once __DIR__ . '/schema.php';

use Amp\Cancellation;
use Thesis\Grpc;
use Thesis\Grpc\Server;
use Thesis\Protobuf;
use Thesis\Protobuf\Reflection;
use function Amp\trapSignal;

$encoder = new ProtobufEncoder(
    new Protobuf\Serializer(),
    Reflection\Reflector::build(),
);

/**
 * @generated
 * @api
 */
interface EchoServer
{
    public function echo(EchoRequest $request, Cancellation $cancellation): EchoResponse;

    /**
     * @param Server\ClientStreamChannel<EchoRequest, EchoResponse> $stream
     */
    public function clientStream(Server\ClientStreamChannel $stream, Cancellation $cancellation): void;

    /**
     * @param Server\ServerStreamChannel<EchoRequest, EchoResponse> $stream
     */
    public function serverStream(
        EchoRequest $request,
        Server\ServerStreamChannel $stream,
        Cancellation $cancellation,
    ): void;

    /**
     * @param Server\BidirectionalStreamChannel<EchoRequest, EchoResponse> $stream
     */
    public function boundedStream(Server\BidirectionalStreamChannel $stream, Cancellation $cancellation): void;
}

/**
 * @generated
 * @api
 */
final readonly class EchoServiceRegistrar implements Server\ServiceRegistry
{
    public function __construct(
        private EchoServer $server,
    ) {}

    #[Override]
    public function services(): iterable
    {
        yield new Server\Service('test.v1.EchoController', [
            new Server\Rpc(
                new Server\Handle('Echo', EchoRequest::class),
                new Server\UnaryHandler($this->server->echo(...)),
            ),
            new Server\Rpc(
                new Server\Handle('ClientStream', EchoRequest::class),
                new Server\ClientStreamHandler($this->server->clientStream(...)),
            ),
            new Server\Rpc(
                new Server\Handle('ServerStream', EchoRequest::class),
                new Server\ServerStreamHandler($this->server->serverStream(...)),
            ),
            new Server\Rpc(
                new Server\Handle('BoundedStream', EchoRequest::class),
                new Server\BidirectionalStreamHandler($this->server->boundedStream(...)),
            ),
        ]);
    }
}

/**
 * @api
 */
final readonly class EchoServerImpl implements EchoServer
{
    #[Override]
    public function echo(EchoRequest $request, Cancellation $cancellation): EchoResponse
    {
        return new EchoResponse('Pong');
    }

    #[Override]
    public function clientStream(Server\ClientStreamChannel $stream, Cancellation $cancellation): void
    {
        $join = [];

        foreach ($stream as $message) {
            $join[] = $message->word;
        }

        $stream->close(new EchoResponse(implode(', ', $join)));
    }

    #[Override]
    public function serverStream(
        EchoRequest $request,
        Server\ServerStreamChannel $stream,
        Cancellation $cancellation,
    ): void {
        $stream->send(...array_map(static fn(int $i) => new EchoResponse("{$request->word}#{$i}"), range(0, 5)));
        $stream->close();
    }

    #[Override]
    public function boundedStream(Server\BidirectionalStreamChannel $stream, Cancellation $cancellation): void
    {
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('Hello too'));
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('I am ok'));
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('Bye bye'));
    }
}

$server = new Server\Builder()
    ->withEncoders($encoder)
    ->withCompressors(new Grpc\Compression\GzipCompressor())
    ->withAddresses('0.0.0.0:50051')
    ->withServices(new EchoServiceRegistrar(new EchoServerImpl()))
    ->build();

$server->start();

trapSignal([\SIGINT, \SIGTERM]);
$server->stop();
dump('Server stopped.');
