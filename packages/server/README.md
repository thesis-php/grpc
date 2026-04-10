# thesis/grpc-server

> Read-only subtree split from `https://github.com/thesis-php/grpc`.
>
> Do not open issues/PRs here. Use the monorepo:
> - https://github.com/thesis-php/grpc/issues
> - https://github.com/thesis-php/grpc/pulls

Async gRPC server for PHP with HTTP/2 transport, unary and streaming RPC handlers, interceptors, and graceful shutdown.

## Contents

- [Installation](#installation)
- [Requirements](#requirements)
- [Implementing a service](#implementing-a-service)
- [Starting the server](#starting-the-server)
- [TLS and mTLS](#tls-and-mtls)
- [Compression](#compression)
- [Interceptors](#interceptors)
- [RPC types](#rpc-types)
- [Stream decorators](#stream-decorators)
- [Graceful shutdown](#graceful-shutdown)

## Installation

```bash
composer require thesis/grpc-server
```

## Requirements

To generate gRPC server interfaces and registries from `.proto`, use:

- https://github.com/thesis-php/protoc-plugin

## Implementing a service

```php
use Amp\Cancellation;
use Auth\Api\V1\AuthenticateRequest;
use Auth\Api\V1\AuthenticateResponse;
use Auth\Api\V1\AuthenticationServiceServer;
use Thesis\Grpc\Metadata;

final readonly class AuthenticationServer implements AuthenticationServiceServer
{
    public function authenticate(AuthenticateRequest $request, Metadata $md, Cancellation $cancellation): AuthenticateResponse
    {
        return new AuthenticateResponse('supertoken');
    }
}
```

## Starting the server

```php
use Auth\Api\V1\AuthenticationServiceServerRegistry;
use Thesis\Grpc\Server;
use function Amp\trapSignal;

$server = new Server\Builder()
    ->withServices(new AuthenticationServiceServerRegistry(new AuthenticationServer()))
    ->build();

$server->start();
trapSignal([\SIGINT, \SIGTERM]);
$server->stop();
```

Default bind address: `0.0.0.0:50051`.

Use `withAddresses()` to override bind addresses:

```php
$server = new Server\Builder()
    ->withAddresses('0.0.0.0:8080')
    ->build();
```

## TLS and mTLS

```php
use Amp\Socket\Certificate;
use Thesis\Grpc\Server;

$server = new Server\Builder()
    ->withTransportCredentials(
        new Server\TransportCredentials()
            ->withDefaultCertificate(new Certificate('/certs/server.crt', '/certs/server.key'))
            ->withCaCert('/certs/ca.crt')
            ->withVerifyPeer(), // optional (mTLS)
    )
    ->build();
```

Practical recommendations:

- Ensure server certificate SAN (`DNS` / `IP`) matches what clients pass via `withPeerName()`.
- For mTLS, use certificates with correct `extendedKeyUsage` (`serverAuth` for server, `clientAuth` for client).
- Prefer certificates signed by a trusted CA and modern algorithms (for example, SHA-256).

## Compression

Register one or more compressors on the server:

```php
use Thesis\Grpc\Compression\GzipCompressor;

$server = new Server\Builder()
    ->withCompressors(new GzipCompressor())
    ->build();
```

## Interceptors

Interceptors let you apply cross-cutting server logic like auth, audit, tracing, and request validation around every RPC.

```php
use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\ServerStream;

final readonly class ServerAuthInterceptor implements Server\Interceptor
{
    public function intercept(ServerStream $stream, StreamInfo $info, Metadata $md, Cancellation $cancellation, callable $next): void
    {
        $next($stream, $info, $md, $cancellation);
    }
}
```

## RPC types

Generated server interfaces expose all four gRPC RPC models directly:

- unary request/response
- client streaming
- server streaming
- bidirectional streaming

Unary example:

```php
use Amp\Cancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceServer;
use Thesis\Grpc\Metadata;

final readonly class EchoServer implements EchoServiceServer
{
    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
    {
        return new EchoResponse($request->sentence);
    }
}
```

Client streaming example:

```php
use Amp\Cancellation;
use File\Api\V1\Chunk;
use File\Api\V1\FileInfo;
use File\Api\V1\FileServiceServer;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;

final readonly class FileServer implements FileServiceServer
{
    public function upload(Server\ClientStreamChannel $stream, Metadata $md, Cancellation $cancellation): FileInfo
    {
        $size = 0;

        /** @var Chunk $chunk */
        foreach ($stream as $chunk) {
            $size += \strlen($chunk->content);
        }

        return new FileInfo($size);
    }
}
```

Server streaming example:

```php
use Amp\Cancellation;
use Topic\Api\V1\Event;
use Topic\Api\V1\SubscribeRequest;
use Topic\Api\V1\TopicServiceServer;
use Thesis\Grpc\Metadata;

final readonly class TopicServer implements TopicServiceServer
{
    public function subscribe(SubscribeRequest $request, Metadata $md, Cancellation $cancellation): iterable
    {
        yield new Event('event-1', '{"id":1}');
        yield new Event('event-2', '{"id":2}');
    }
}
```

Bidirectional streaming example:

```php
use Amp\Cancellation;
use Chat\Api\V1\Message;
use Chat\Api\V1\MessengerServiceServer;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;

final readonly class MessengerServer implements MessengerServiceServer
{
    public function chat(Server\BidirectionalStreamChannel $stream, Metadata $md, Cancellation $cancellation): void
    {
        foreach ($stream as $message) {
            $stream->send(new Message("echo: {$message->text}"));
        }

        $stream->close();
    }
}
```

## Stream decorators

If you need per-message interception (not just per-RPC), decorate server streams.

```php
use Psr\Log\LoggerInterface;
use Thesis\Grpc\Server;
use Thesis\Grpc\ServerStream;

/**
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

    public function send(object $message): void
    {
        $this->logger->info('sent {type}', ['type' => $message::class]);
        parent::send($message);
    }

    public function receive(): object
    {
        $message = parent::receive();
        $this->logger->info('recv {type}', ['type' => $message::class]);

        return $message;
    }
}
```

## Graceful shutdown

`Server::stop()` stops accepting new requests and waits for active handlers.
You can pass `TimeoutCancellation` to bound wait time.
Handlers receive `Cancellation`; they should respect it so shutdown can finish promptly.

```php
use Amp\TimeoutCancellation;

$server->stop(new TimeoutCancellation(30));
```
