# thesis/grpc-client

> Read-only subtree split from `https://github.com/thesis-php/grpc`.
>
> Do not open issues/PRs here. Use the monorepo:
> - https://github.com/thesis-php/grpc/issues
> - https://github.com/thesis-php/grpc/pulls

Async gRPC client for PHP with HTTP/2 transport, streaming RPCs, interceptors, TLS/mTLS, and client-side load balancing.

## Contents

- [Installation](#installation)
- [Requirements](#requirements)
- [Basic usage](#basic-usage)
- [TLS and mTLS](#tls-and-mtls)
- [Target addressing](#target-addressing)
- [Load balancing](#load-balancing)
- [Endpoint resolution](#endpoint-resolution)
- [Error handling](#error-handling)
- [Compression](#compression)
- [Interceptors](#interceptors)
- [Client streaming](#client-streaming)
- [Server streaming](#server-streaming)
- [Bidirectional streaming](#bidirectional-streaming)
- [Closing the client](#closing-the-client)

## Installation

```bash
composer require thesis/grpc-client
```

## Requirements

To generate gRPC client classes from `.proto`, use:

- https://github.com/thesis-php/protoc-plugin

## Basic usage

```php
use Auth\Api\V1\AuthenticateRequest;
use Auth\Api\V1\AuthenticationServiceClient;
use Thesis\Grpc\Client;

$client = new AuthenticationServiceClient(
    new Client\Builder()
        ->withHost('dns:///127.0.0.1:50051')
        ->build(),
);

$response = $client->authenticate(new AuthenticateRequest('root', 'secret'));
```

You can pass request metadata too:

```php
use Thesis\Grpc\Metadata;

$response = $client->authenticate(
    new AuthenticateRequest('root', 'secret'),
    new Metadata()->with('x-request-id', 'req-123'),
);
```

## TLS and mTLS

```php
use Amp\Socket\Certificate;
use Thesis\Grpc\Client;

$client = new Client\Builder()
    ->withTransportCredentials(
        new Client\TransportCredentials()
            ->withCaCert('/certs/ca.crt')
            ->withPeerName('localhost')
            ->withCertificate(new Certificate('/certs/client.crt', '/certs/client.key')), // optional (mTLS)
    )
    ->build();
```

## Target addressing

The target string passed to `withHost()` follows gRPC name resolution format: `scheme:endpoint`.

Supported schemes:

| Scheme        | Format                                            | Description                                                              |
|---------------|---------------------------------------------------|--------------------------------------------------------------------------|
| `dns`         | `dns:///host:port` or `dns://authority/host:port` | Resolves hostname via DNS. Supports periodic re-resolution based on TTL. |
| `ipv4`        | `ipv4:addr1:port1,addr2:port2`                    | Comma-separated IPv4 addresses, no DNS lookup.                           |
| `ipv6`        | `ipv6:[addr1]:port1,[addr2]:port2`                | Comma-separated IPv6 addresses in bracket notation.                      |
| `unix`        | `unix:///path/to/socket`                          | Connects via Unix domain socket.                                         |
| `passthrough` | `passthrough:///address`                          | Passes address through as-is without resolution.                         |

If scheme is omitted, `dns` is assumed:

```php
// Equivalent:
->withHost('my-grpc-server:50051')
->withHost('dns:///my-grpc-server:50051')
```

Use custom DNS server with authority:

```php
->withHost('dns://10.0.0.1:53/my-grpc-server:50051')
```

Multi-endpoint target without DNS:

```php
->withHost('ipv4:10.0.0.1:50051,10.0.0.2:50051,10.0.0.3:50051')
```

## Load balancing

When resolution returns multiple addresses, the load balancer picks endpoint per RPC.

Built-in policies:

- `PickFirstFactory` (default): shuffles endpoint list, picks one, and keeps it pinned until it disappears after refresh.
- `RoundRobinFactory`: cycles through all available endpoints.

With DNS targets, resolver can re-resolve by TTL and call balancer `refresh()` with updated endpoints. Re-resolution interval is clamped between minimum and maximum bounds (default 30..300 seconds).

```php
use Thesis\Grpc\Client\LoadBalancer\RoundRobinFactory;

$client = new Client\Builder()
    ->withHost('ipv4:10.0.0.1:50051,10.0.0.2:50051')
    ->withLoadBalancer(new RoundRobinFactory())
    ->build();
```

Custom load balancer example:

```php
use Random\Randomizer;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\LoadBalancer;
use Thesis\Grpc\Client\LoadBalancerFactory;
use Thesis\Grpc\Client\PickContext;

final class RandomBalancer implements LoadBalancer
{
    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    public function __construct(
        private array $endpoints,
        private readonly Randomizer $randomizer = new Randomizer(),
    ) {}

    public function refresh(array $endpoints): void
    {
        $this->endpoints = $endpoints;
    }

    public function pick(PickContext $context): Endpoint
    {
        return $this->endpoints[$this->randomizer->getInt(0, \count($this->endpoints) - 1)];
    }
}

final readonly class RandomBalancerFactory implements LoadBalancerFactory
{
    public function name(): string
    {
        return 'random';
    }

    public function create(array $endpoints): LoadBalancer
    {
        return new RandomBalancer($endpoints);
    }
}
```

## Endpoint resolution

Resolver is selected by target scheme. You can override resolver for a specific scheme:

```php
use Amp\Cache\LocalCache;
use Thesis\Grpc\Client\Builder;
use Thesis\Grpc\Client\EndpointResolver\DnsResolver;
use Thesis\Grpc\Client\Scheme;

$client = new Builder()
    ->withHost('dns:///my-grpc-server:50051')
    ->withEndpointResolver(Scheme::Dns, new DnsResolver(
        cache: new LocalCache(),
        minResolveInterval: 60,
        maxResolveInterval: 600,
    ))
    ->build();
```

Default resolvers by scheme:

- `dns` -> `DnsResolver`
- `ipv4`, `ipv6`, `unix` -> `StaticResolver`
- `passthrough` -> `PassthroughResolver`

You can also implement your own `EndpointResolver` for service discovery backends like Consul/etcd.

## Error handling

```php
use Thesis\Grpc\InvokeError;

try {
    $response = $client->authenticate(new AuthenticateRequest('root', 'secret'));
} catch (InvokeError $e) {
    dump($e->statusCode, $e->statusMessage, $e->details);
}
```

## Compression

Compression reduces payload size and is useful for large protobuf messages or bandwidth-constrained links.

```php
use Thesis\Grpc\Compression\GzipCompressor;

$client = new Client\Builder()
    ->withCompression(new GzipCompressor())
    ->build();
```

## Interceptors

Interceptors let you add cross-cutting logic (auth, logging, tracing, retry, metadata enrichment) without changing service stubs.

```php
use Amp\Cancellation;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

final readonly class ClientAuthInterceptor implements Client\Interceptor
{
    public function intercept(Invoke $invoke, Metadata $md, Cancellation $cancellation, callable $next): ClientStream
    {
        return $next($invoke, $md->with('Authorization', 'supertoken'), $cancellation);
    }
}
```

## Client streaming

Use client streaming when you need to send many messages and receive one final aggregated response.

```php
use File\Api\V1\Chunk;
use File\Api\V1\FileServiceClient;

$files = new FileServiceClient(new Client\Builder()->build());
$upload = $files->upload();

for ($i = 0; $i < 10; ++$i) {
    $upload->send(new Chunk(random_bytes(10)));
}

$info = $upload->close(); // FileInfo
dump($info->size); // 100
```

## Server streaming

Use server streaming when a single request should return a sequence of server messages.

```php
use Topic\Api\V1\SubscribeRequest;
use Topic\Api\V1\TopicServiceClient;

$topics = new TopicServiceClient(new Client\Builder()->build());
$stream = $topics->subscribe(new SubscribeRequest('payments'));

foreach ($stream as $event) {
    dump($event->name, $event->payload);
}
```

## Bidirectional streaming

Use bidirectional streaming for conversational protocols where both sides can send messages independently.

```php
use Chat\Api\V1\Message;
use Chat\Api\V1\MessengerServiceClient;

$chat = new MessengerServiceClient(new Client\Builder()->build())->chat();

$chat->send(new Message('Hi from gRPC client'));
dump($chat->receive()->text);

$chat->send(new Message('Bye'));
$chat->close();
dump($chat->receive()->text);
```

## Closing the client

Call `Client::close()` to stop background resolver activity and release resources, especially when DNS re-resolution is enabled.

```php
try {
    $response = $client->authenticate(new AuthenticateRequest('root', 'secret'));
} finally {
    $client->close();
}
```
