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

$encoder = new ProtobufEncoder(
    new Protobuf\Serializer(),
    Reflection\Reflector::build(),
);

$client = new Client\Builder($encoder)
    ->withHost('http://127.0.0.1:50051')
    ->withCompression(new Compression\GzipCompressor())
    ->build();

$stream = $client->createStream(new Invoke(
    method: 'test.v1.EchoController/ServerStream',
    type: EchoResponse::class,
));

$stream->send(new EchoRequest('Ping'));
$stream->close();

foreach ($stream as $message) {
    dump($message);
}
