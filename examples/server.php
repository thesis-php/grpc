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

/**
 * @api
 * @template-implements Server\Handler<EchoRequest, EchoResponse>
 */
final readonly class EchoHandler implements Server\Handler
{
    /**
     * @param Closure(EchoRequest): EchoResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {}

    #[Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        $request = $stream->receive();
        $response = ($this->handler)($request);
        $stream->send($response);
        $stream->close();
    }
}

/**
 * @api
 * @template-implements Server\Handler<EchoRequest, EchoResponse>
 */
final readonly class EchoClientStreamHandler implements Server\Handler
{
    #[Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        foreach ($stream as $message) {
            dump($message->word);
        }

        $stream->send(new EchoResponse('Handled.'));
        $stream->close();
    }
}

/**
 * @api
 * @template-implements Server\Handler<EchoRequest, EchoResponse>
 */
final readonly class EchoServerStreamHandler implements Server\Handler
{
    #[Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        $request = $stream->receive();
        dump($request->word);

        for ($i = 0; $i < 5; ++$i) {
            $stream->send(new EchoResponse("resp#{$i}"));
        }

        $stream->close();
    }
}

/**
 * @api
 * @template-implements Server\Handler<EchoRequest, EchoResponse>
 */
final readonly class EchoBidirectionalStreamHandler implements Server\Handler
{
    #[Override]
    public function handle(ServerStream $stream, Cancellation $cancellation): void
    {
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('Hello too'));
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('I am ok'));
        dump($stream->receive()->word);
        $stream->send(new EchoResponse('Bye bye'));

        $stream->close();
    }
}

$server = new Server\Builder()
    ->withEncoders($encoder)
    ->withCompressors(new Grpc\Compression\GzipCompressor())
    ->withAddresses('0.0.0.0:50051')
    ->withServices(
        new Server\Service('test.v1.EchoController', [
            new Server\Rpc(
                new Server\Handle('Echo', EchoRequest::class),
                new EchoHandler(static function (EchoRequest $request): EchoResponse {
                    dump($request->word);

                    return new EchoResponse('Pong');
                }),
            ),
            new Server\Rpc(
                new Server\Handle('ClientStream', EchoRequest::class),
                new EchoClientStreamHandler(),
            ),
            new Server\Rpc(
                new Server\Handle('ServerStream', EchoRequest::class),
                new EchoServerStreamHandler(),
            ),
            new Server\Rpc(
                new Server\Handle('BoundedStream', EchoRequest::class),
                new EchoBidirectionalStreamHandler(),
            ),
        ]),
    )
    ->build();

$server->start();

trapSignal([\SIGINT, \SIGTERM]);
$server->stop();
dump('Server stopped.');
