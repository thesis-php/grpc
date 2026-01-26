<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/protobuf.php';
require_once __DIR__ . '/schema.php';

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Client;
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
        /** @var Client\Invoke<EchoRequest, EchoResponse> $invoke */
        $invoke = new Client\Invoke(
            method: 'test.v1.EchoController/Echo',
            type: EchoResponse::class,
        );

        return $this->client->invoke(
            request: $request,
            invoke: $invoke,
            cancellation: $cancellation,
        );
    }

    /**
     * @return Client\ClientStreamChannel<EchoRequest, EchoResponse>
     */
    public function clientStream(Cancellation $cancellation = new NullCancellation()): Client\ClientStreamChannel
    {
        /** @var Client\Invoke<EchoRequest, EchoResponse> $invoke */
        $invoke = new Client\Invoke(
            method: 'test.v1.EchoController/ClientStream',
            type: EchoResponse::class,
        );

        $stream = $this->client->createStream(
            invoke: $invoke,
            cancellation: $cancellation,
        );

        return new Client\ClientStreamChannel($stream);
    }

    /**
     * @return Client\ServerStreamChannel<EchoRequest, EchoResponse>
     */
    public function serverStream(EchoRequest $request, Cancellation $cancellation = new NullCancellation()): Client\ServerStreamChannel
    {
        /** @var Client\Invoke<EchoRequest, EchoResponse> $invoke */
        $invoke = new Client\Invoke(
            method: 'test.v1.EchoController/ServerStream',
            type: EchoResponse::class,
        );

        $stream = $this->client->createStream(
            invoke: $invoke,
            cancellation: $cancellation,
        );

        $stream->send($request);
        $stream->close();

        return new Client\ServerStreamChannel($stream);
    }

    /**
     * @return Client\BidirectionalStreamChannel<EchoRequest, EchoResponse>
     */
    public function duplexStream(Cancellation $cancellation = new NullCancellation()): Client\BidirectionalStreamChannel
    {
        /** @var Client\Invoke<EchoRequest, EchoResponse> $invoke */
        $invoke = new Client\Invoke(
            method: 'test.v1.EchoController/BoundedStream',
            type: EchoResponse::class,
        );

        $stream = $this->client->createStream(
            invoke: $invoke,
            cancellation: $cancellation,
        );

        return new Client\BidirectionalStreamChannel($stream);
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
$clientStream->send(
    ...array_map(static fn(int $i) => new EchoRequest("req#{$i}"), range(0, 5)),
);

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
