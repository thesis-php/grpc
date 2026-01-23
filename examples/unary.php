<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/protobuf.php';
require_once __DIR__ . '/schema.php';

use Thesis\Grpc;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\Invoke;
use Thesis\Protobuf;
use Thesis\Protobuf\Reflection;

$encoder = new ProtobufEncoder(
    new Protobuf\Serializer(),
    Reflection\Reflector::build(),
);

$client = new Client\Builder($encoder)
    ->withHost('http://127.0.0.1:50051')
    ->withCompression(new Grpc\Compression\GzipCompressor())
    ->build();

$response = $client->invoke(
    request: new EchoRequest('Ping'),
    invoke: new Invoke(
        method: 'test.v1.EchoController/Echo',
        type: EchoResponse::class,
    ),
);

dump($response->word);
