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

### Interceptors

The library supports interceptors for both the client and the server. For example, if you want to restrict access to authorized clients only, you can write a server-side interceptor:

```php
use Amp\Cancellation;
use Google\Rpc\Code;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\ServerStream;

final readonly class ServerAuthenticationInterceptor implements Server\Interceptor
{
    #[\Override]
    public function intercept(
        ServerStream $stream,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): void {
        if (!str_ends_with($info->method, 'Authenticate') && $md->value('Authorization') !== 'supertoken') {
            throw new InvokeError(Code::UNAUTHENTICATED);
        }

        $next($stream, $info, $md, $cancellation);
    }
}
```

Incoming headers — through which authorization tokens are typically passed — are available via `Metadata`. The interceptor also has access to `StreamInfo`, which exposes the current RPC method name, allowing you to selectively skip authorization checks for specific methods such as the authentication endpoint itself.

Registering the interceptor on the server:

```php
$server = new Server\Builder()
    ->withInterceptors(new ServerAuthenticationInterceptor())
    ->build();
```

The same pattern applies on the client side. Here is a client interceptor that automatically attaches an authorization token to every outgoing request:

```php
use Amp\Cancellation;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

final readonly class ClientAuthenticationInterceptor implements Client\Interceptor
{
    #[\Override]
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): ClientStream {
        return $next($invoke, $md->with('Authorization', 'supertoken'), $cancellation);
    }
}
```

Registering it on the client:

```php
$client = new AuthenticationServiceClient(
    new Client\Builder()
        ->withInterceptors(new ClientAuthenticationInterceptor())
        ->build(),
);
```

### Stream Decorators

For more advanced use cases, you can decorate streams directly. This allows you to intercept every individual message flowing through a stream — useful for logging, performance metrics, tracing, or any other cross-cutting concern.

Let's implement a server-side stream decorator that logs every incoming and outgoing message:

```php
use Psr\Log\LoggerInterface;
use Thesis\Grpc\Server;
use Thesis\Grpc\ServerStream;

/**
 * @api
 * @template-covariant In of object
 * @template Out of object
 * @template-extends Server\DecoratedStream<In, Out>
 */
final class LoggingServerStream extends Server\DecoratedStream
{
    public function __construct(
        ServerStream $stream,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($stream);
    }

    #[\Override]
    public function send(object $message): void
    {
        $this->logger->info('message "{type}" was sent', [
            'type' => $message::class,
        ]);

        parent::send($message);
    }

    #[\Override]
    public function receive(): object
    {
        $message = parent::receive();

        $this->logger->info('message "{type}" was received', [
            'type' => $message::class,
        ]);

        return $message;
    }
}
```

Now wire it up via an interceptor that substitutes the original stream with the decorated one:

```php
use Amp\Cancellation;
use Psr\Log\LoggerInterface;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\ServerStream;

final readonly class LoggingServerInterceptor implements Server\Interceptor
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function intercept(ServerStream $stream, StreamInfo $info, Metadata $md, Cancellation $cancellation, callable $next): void
    {
        $next(new LoggingServerStream($stream, $this->logger), $info, $md, $cancellation);
    }
}
```

Register the interceptor on the server:

```php
$server = new Server\Builder()
    ->withInterceptors(new LoggingServerInterceptor(/** LoggerInterface implementation */))
    ->build();
```

The same approach works on the client side. First, the stream decorator:

```php
use Psr\Log\LoggerInterface;
use Thesis\Grpc\Client;
use Thesis\Grpc\ClientStream;

/**
 * @api
 * @template In of object
 * @template-covariant Out of object
 * @template-extends Client\DecoratedStream<In, Out>
 */
final readonly class LoggingClientStream extends Client\DecoratedStream
{
    public function __construct(
        ClientStream $stream,
        private LoggerInterface $logger,
    ) {
        parent::__construct($stream);
    }

    #[\Override]
    public function send(object $message): void
    {
        $this->logger->info('message "{type}" was sent', [
            'type' => $message::class,
        ]);

        parent::send($message);
    }

    #[\Override]
    public function receive(): object
    {
        $message = parent::receive();

        $this->logger->info('message "{type}" was received', [
            'type' => $message::class,
        ]);

        return $message;
    }
}
```

The interceptor that substitutes the stream:

```php
use Amp\Cancellation;
use Psr\Log\LoggerInterface;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

final readonly class LoggingClientInterceptor implements Client\Interceptor
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function intercept(Invoke $invoke, Metadata $md, Cancellation $cancellation, callable $next): ClientStream
    {
        return new LoggingClientStream(
            $next($invoke, $md, $cancellation),
            $this->logger,
        );
    }
}
```

And register it via the client builder:

```php
$client = new AuthenticationServiceClient(
    new Client\Builder()
        ->withInterceptors(new LoggingClientInterceptor(/** LoggerInterface implementation */))
        ->build(),
);
```

### Client Streaming

If you have prior gRPC experience in PHP, nothing so far will have surprised you. Let's move on to what truly sets gRPC apart — streams.

We'll start with client streaming. In this pattern, the client sends a stream of messages to the server, and once the stream is complete, the server returns a single response and closes the connection. As an example, consider a proto schema where the client sends a series of words and asks the server to count the total number of bytes received:

```protobuf
syntax = "proto3";

package counter.api.v1;

message Word {
    bytes value = 1;
}

message Info {
    int32 count = 1;
}

service CounterService {
    rpc Count(stream Word) returns (Info);
}
```

After generating the code, implement the server:

```php
use Amp\Cancellation;
use Counter\Api\V1\CounterServiceServer;
use Counter\Api\V1\Info;
use Counter\Api\V1\Word;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;

/**
 * @api
 */
final readonly class CounterServer implements CounterServiceServer
{
    #[\Override]
    public function count(Server\ClientStreamChannel $stream, Metadata $md, Cancellation $cancellation): void
    {
        $bytes = 0;

        foreach ($stream as $message) {
            $bytes += \strlen($message->value);
        }

        $stream->close(new Info($bytes));
    }
}
```

And register it:

```php
$server = new Server\Builder()
    ->withServices(new AuthenticationServiceServerRegistry(new AuthenticationServer()))
    ->withServices(new CounterServiceServerRegistry(new CounterServer()))
    ->build();
```

Note that a single server instance can host multiple service handlers.

On the client side, calling `count()` returns a stream with two methods: `send()`, which transmits messages to the server, and `close()`, which finalizes the stream and waits for the server's response. Everything operates in non-blocking mode — if needed, the stream can be wrapped in `\Amp\async()`.

```php
use Counter\Api\V1\CounterServiceClient;
use Counter\Api\V1\Word;
use Thesis\Grpc\Client;

$client = new CounterServiceClient(
    new Client\Builder()
        ->build(),
);

$words = $client->count();

for ($i = 0; $i < 10; ++$i) {
    $words->send(new Word(random_bytes(10)));
}

dump($words->close()->count); // 100
```
