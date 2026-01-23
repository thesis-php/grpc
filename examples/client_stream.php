<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/protobuf.php';
require_once __DIR__ . '/schema.php';

use Thesis\Grpc\Client;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Compression;
use Thesis\Protobuf;
use Thesis\Protobuf\Reflection;
use function Amp\async;

$encoder = new ProtobufEncoder(
    new Protobuf\Serializer(),
    Reflection\Reflector::build(),
);

$client = new Client\Builder($encoder)
    ->withHost('http://127.0.0.1:50051')
    ->withCompression(new Compression\GzipCompressor())
    ->build();

$stream = $client->createStream(new Invoke(
    method: 'test.v1.EchoController/ClientStream',
    type: EchoResponse::class,
));

async(static function () use ($stream): void {
    for ($i = 0; $i < 5; ++$i) {
        $stream->send(new EchoRequest("req#{$i}"));
    }

    $stream->close();
});

dump($stream->receive());
