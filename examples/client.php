<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/protobuf.php';
require_once __DIR__ . '/schema.php';

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Client;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Compression\GzipCompressor;
use Thesis\Protobuf;
use Thesis\Protobuf\Reflection;

$encoder = new ProtobufEncoder(
    new Protobuf\Serializer(),
    Reflection\Reflector::build(),
);

/**
 * @generated
 * @api
 */
final readonly class EchoClient
{
    public function __construct(
        private Client $client,
    ) {}

    public function echo(EchoRequest $request, Cancellation $cancellation = new NullCancellation()): EchoResponse
    {
        return $this->client->invoke(
            request: $request,
            invoke: new Client\Invoke(
                method: 'test.v1.EchoController/Echo',
                type: EchoResponse::class,
            ),
            cancellation: $cancellation,
        );
    }

    public function clientStream(Cancellation $cancellation = new NullCancellation()): EchoClientClientStream
    {
        $stream = $this->client->createStream(
            invoke: new Client\Invoke(
                method: 'test.v1.EchoController/ClientStream',
                type: EchoResponse::class,
            ),
            cancellation: $cancellation,
        );

        /** @phpstan-ignore argument.type */
        return new EchoClientClientStream($stream);
    }

    /**
     * @return iterable<array-key, EchoResponse>
     */
    public function serverStream(EchoRequest $request, Cancellation $cancellation = new NullCancellation()): iterable
    {
        $stream = $this->client->createStream(
            invoke: new Client\Invoke(
                method: 'test.v1.EchoController/ServerStream',
                type: EchoResponse::class,
            ),
            cancellation: $cancellation,
        );

        $stream->send($request);
        $stream->close();

        return $stream->getIterator();
    }

    public function duplexStream(Cancellation $cancellation = new NullCancellation()): EchoClientDuplexStream
    {
        $stream = $this->client->createStream(
            invoke: new Client\Invoke(
                method: 'test.v1.EchoController/BoundedStream',
                type: EchoResponse::class,
            ),
            cancellation: $cancellation,
        );

        /** @phpstan-ignore argument.type */
        return new EchoClientDuplexStream($stream);
    }
}

/**
 * @generated
 * @api
 */
final readonly class EchoClientClientStream
{
    /**
     * @param ClientStream<EchoRequest, EchoResponse> $stream
     */
    public function __construct(
        private ClientStream $stream,
    ) {}

    public function send(EchoRequest $request): void
    {
        $this->stream->send($request);
    }

    public function close(): EchoResponse
    {
        $this->stream->close();

        return $this->stream->receive();
    }
}

/**
 * @generated
 * @api
 * @template-implements \IteratorAggregate<array-key, EchoResponse>
 */
final readonly class EchoClientDuplexStream implements IteratorAggregate
{
    /**
     * @param ClientStream<EchoRequest, EchoResponse> $stream
     */
    public function __construct(
        private ClientStream $stream,
    ) {}

    public function send(EchoRequest $request): void
    {
        $this->stream->send($request);
    }

    public function receive(): EchoResponse
    {
        return $this->stream->receive();
    }

    public function close(): void
    {
        $this->stream->close();
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return $this->stream->getIterator();
    }
}

$client = new Client\Builder($encoder)
    ->withHost('http://127.0.0.1:50051')
    ->withCompression(new GzipCompressor())
    ->build();

$echoClient = new EchoClient($client);

// Unary echo request
dump($echoClient->echo(new EchoRequest('Ping')));

// Client stream request
$clientStream = $echoClient->clientStream();

for ($i = 0; $i < 5; ++$i) {
    $clientStream->send(new EchoRequest("req#{$i}"));
}

dump($clientStream->close());

// Server stream request
$messages = $echoClient->serverStream(new EchoRequest('Ping'));

foreach ($messages as $message) {
    dump($message);
}

// Duplex stream
$duplexStream = $echoClient->duplexStream();

$duplexStream->send(new EchoRequest('Hello'));
dump($duplexStream->receive()->word);

$duplexStream->send(new EchoRequest('How are you?'));
dump($duplexStream->receive()->word);

$duplexStream->send(new EchoRequest('Bye'));

$duplexStream->close();
dump($duplexStream->receive()->word);
