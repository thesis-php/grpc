# Non-blocking full-featured gRPC implementation for PHP

## Installation

```shell
composer require thesis/grpc
```
---

This library implements non-blocking gRPC for PHP. It supports all the advantages of the gRPC framework, including client, server, and bidirectional streaming — not to mention the ability to run PHP as a gRPC **server**, not just a client.

### Requirements

To use this library you will need [thesis/protoc-plugin](https://github.com/thesis-php/protoc-plugin) and [thesis/protobuf](https://github.com/thesis-php/protobuf). The former, as the name suggests, is a `protoc` plugin that generates a gRPC client and server in the conventional gRPC style, so it is recommended to read the plugin [documentation](https://github.com/thesis-php/protoc-plugin?tab=readme-ov-file#pure-php-plugin-for-protoc) first. Once you've done that, you're ready to start building gRPC-based communication.

### Unary Requests

Let's start with unary requests — the simplest form of gRPC communication. A unary request performs a single round trip between the client and the server: send a request, get a response. Think of it as classical HTTP. Consider the following proto schema:

```protobuf
syntax = "proto3";

package auth.api.v1;

message AuthenticateRequest {
   string user = 1;
   string pass = 2;
}

message AuthenticateResponse {
   string token = 1;
}

service AuthenticationService {
   rpc Authenticate(AuthenticateRequest) returns (AuthenticateResponse);
}
```

Assuming your `.proto` files live in a `protos/` directory alongside `src/`, and you want to generate code into `genproto/`, run the following command (see the plugin documentation for installation instructions):

```sh
protoc \
    --plugin=protoc-gen-php-plugin=/usr/local/bin/protoc-gen-php \
    protos/*.proto \
    --php-plugin_out=genproto
```

This will produce an `Auth/Api/V1/` directory inside `genproto/` containing the following files:

- `AuthenticateRequest.php`
- `AuthenticateResponse.php`
- `AuthenticationServiceClient.php`
- `AuthenticationServiceServer.php`
- `AuthenticationServiceServerRegistry.php`
- `autoload.metadata.php`
- `ProtosAuthV1DescriptorRegistry.php`

If you have read the plugin documentation, you will already know how to work with these files. Once you have registered `autoload.metadata.php` in your `composer.json` and configured a PSR-4 namespace for your generated gRPC code, you are ready to implement the server.

### Implementing the Server

To implement a gRPC server, you need to provide a concrete implementation of the generated `AuthenticationServiceServer` interface:

```php
use Amp\Cancellation;
use Auth\Api\V1\AuthenticateRequest;
use Auth\Api\V1\AuthenticateResponse;
use Auth\Api\V1\AuthenticationServiceServer;
use Google\Rpc\Code;
use Google\Rpc\PreconditionFailure;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class AuthenticationServer implements AuthenticationServiceServer
{
    #[\Override]
    public function authenticate(AuthenticateRequest $request, Metadata $md, Cancellation $cancellation): AuthenticateResponse
    {
        if ($request->user === 'root' && $request->pass === 'secret') {
            return new AuthenticateResponse('supertoken');
        }

        throw new InvokeError(Code::FAILED_PRECONDITION, 'Invalid authentication credentials', [
            new PreconditionFailure([
                new PreconditionFailure\Violation('auth', 'credentials', 'invalid credentials'),
            ]),
        ]);
    }
}
```

### Starting the Server

```php
<?php

declare(strict_types=1);

use Auth\Api\V1\AuthenticationServiceServerRegistry;
use Thesis\Grpc\Server;
use function Amp\trapSignal;

$server = new Server\Builder()
    ->withServices(new AuthenticationServiceServerRegistry(
        new AuthenticationServer(),
    ))
    ->build();

$server->start();

trapSignal([\SIGINT, \SIGTERM]);
$server->stop();
```

By default, the server listens on `0.0.0.0:50051`. To change the address, use the `withAddresses` builder method:

```php
$server = new Server\Builder()
    ->withAddresses('0.0.0.0:8080')
    // ...
    ->build();
```

### Using the Client

Unlike the server, the client requires no implementation — it is already generated for you. Simply create a connection and pass it to the generated client:

```php
use Auth\Api\V1\AuthenticateRequest;
use Auth\Api\V1\AuthenticationServiceClient;
use Thesis\Grpc\Client;

$client = new AuthenticationServiceClient(
    new Client\Builder()
        ->build(),
);

$response = $client->authenticate(new AuthenticateRequest('root', 'secret'));
dump($response->token); // supertoken
```

Just like the server builder, the client builder lets you customize the connection. For example, you can override the host if the server is not running on the default address:

```php
$client = new AuthenticationServiceClient(
    new Client\Builder()
        ->withHost('http://127.0.0.1:8080')
        ->build(),
);
```

### Error Handling

If the server returns an error, you can handle it with a standard `try/catch` block:

```php
try {
    $response = $client->authenticate(new AuthenticateRequest('root', 'secret'));
    dump($response->token);
} catch (InvokeError $e) {
    dump($e->statusCode, $e->statusMessage, $e->details);
}
```

### Compression

You can configure compression between the client and the server. If the client compresses protobuf messages using gzip, the server must support it as well — otherwise you will receive an `UNIMPLEMENTED` error. Compression is configured via the respective builders.

Configuring compression on the server:

```php
$server = new Server\Builder()
    ->withServices(new AuthenticationServiceServerRegistry(
        new AuthenticationServer(),
    ))
    ->withCompressors(new GzipCompressor())
    ->build();
```

And on the client:

```php
$client = new AuthenticationServiceClient(
    new Client\Builder()
        ->withCompression(new GzipCompressor())
        ->build(),
);
```

A server can support multiple compression algorithms simultaneously, serving different clients with different configurations, while each client uses exactly one. The library ships with built-in implementations for the most popular algorithms: gzip, deflate, and snappy. Some of these may require the corresponding PHP extension to be installed. If you need a custom compression strategy, implement the `Thesis\Grpc\Compression\Compressor` interface.