<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/protobuf.php';
require_once __DIR__ . '/schema.php';

use Amp\Cancellation;
use Thesis\Grpc;
use Thesis\Grpc\Server;
use Thesis\Grpc\ServerStream;
use Thesis\Protobuf;
use Thesis\Protobuf\Reflection;
use function Amp\trapSignal;

$encoder = new ProtobufEncoder(
    new Protobuf\Serializer(),
    Reflection\Reflector::build(),
);

interface EchoServer
{
    public function echo(EchoRequest $request, Cancellation $cancellation): EchoResponse;

    /**
     * @param iterable<array-key, EchoRequest> $stream
     */
    public function clientStream(iterable $stream, Cancellation $cancellation): EchoResponse;

    /**
     * @return iterable<array-key, EchoResponse>
     */
    public function serverStream(EchoRequest $request, Cancellation $cancellation): iterable;

    /**
     * @param ServerStream<EchoRequest, EchoResponse> $stream
     */
    public function boundedStream(ServerStream $stream, Cancellation $cancellation): void;
}

/**
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
                new UnaryEchoHandler($this->server->echo(...)),
            ),
            new Server\Rpc(
                new Server\Handle('ClientStream', EchoRequest::class),
                new ClientStreamEchoHandler($this->server->clientStream(...)),
            ),
            new Server\Rpc(
                new Server\Handle('ServerStream', EchoRequest::class),
                new ServerStreamEchoHandler($this->server->serverStream(...)),
            ),
            new Server\Rpc(
                new Server\Handle('BoundedStream', EchoRequest::class),
                new BoundedStreamEchoHandler($this->server->boundedStream(...)),
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
    public function clientStream(iterable $stream, Cancellation $cancellation): EchoResponse
    {
        $join = [];

        foreach ($stream as $message) {
            $join[] = $message->word;
        }

        return new EchoResponse(implode(', ', $join));
    }

    #[Override]
    public function serverStream(EchoRequest $request, Cancellation $cancellation): iterable
    {
        for ($i = 0; $i < 5; ++$i) {
            yield new EchoResponse("{$request->word}#{$i}");
        }
    }

    #[Override]
    public function boundedStream(ServerStream $stream, Cancellation $cancellation): void
    {
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('Hello too'));
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('I am ok'));
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('Bye bye'));
    }
}

/**
 * @internal
 * @template-implements Server\Handler<EchoRequest, EchoResponse>
 */
final readonly class UnaryEchoHandler implements Server\Handler
{
    /**
     * @param Closure(EchoRequest, Cancellation): EchoResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {}

    #[Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        $request = $stream->receive();
        $response = ($this->handler)($request, $cancellation);
        $stream->send($response);
        $stream->close();
    }
}

/**
 * @internal
 * @template-implements Server\Handler<EchoRequest, EchoResponse>
 */
final readonly class ClientStreamEchoHandler implements Server\Handler
{
    /**
     * @param Closure(iterable<array-key, EchoRequest>, Cancellation): EchoResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {}

    #[Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        $response = ($this->handler)($stream->getIterator(), $cancellation);
        $stream->send($response);
        $stream->close();
    }
}

/**
 * @internal
 * @template-implements Server\Handler<EchoRequest, EchoResponse>
 */
final readonly class ServerStreamEchoHandler implements Server\Handler
{
    /**
     * @param Closure(EchoRequest, Cancellation): iterable<array-key, EchoResponse> $handler
     */
    public function __construct(
        private Closure $handler,
    ) {}

    #[Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        $request = $stream->receive();
        $responses = ($this->handler)($request, $cancellation);

        foreach ($responses as $response) {
            $stream->send($response);
        }

        $stream->close();
    }
}

/**
 * @internal
 * @template-implements Server\Handler<EchoRequest, EchoResponse>
 */
final readonly class BoundedStreamEchoHandler implements Server\Handler
{
    /**
     * @param Closure(ServerStream<EchoRequest, EchoResponse>, Cancellation): void $handler
     */
    public function __construct(
        private Closure $handler,
    ) {}

    #[Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        ($this->handler)($stream, $cancellation);
        $stream->close();
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
