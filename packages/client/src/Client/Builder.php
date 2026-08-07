<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Http\Client\Connection\ConnectionLimitingPool;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Socket\ConnectContext;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\SocketConnector;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\Internal\Connection;
use Thesis\Grpc\Client\Internal\Http2;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Compression\IdentityCompressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Protobuf\ProtobufEncoder;
use Thesis\Protobuf\Decoder;

/**
 * @api
 */
final class Builder
{
    private const string DEFAULT_HOST = '127.0.0.1:50051';
    private const float DEFAULT_CONNECT_TIMEOUT = 10;
    private const int DEFAULT_CONNECTION_LIMIT = \PHP_INT_MAX;
    private const int DEFAULT_TRANSFER_TIMEOUT = 0; // no transfer timeout
    private const int DEFAULT_INACTIVITY_TIMEOUT = 0; // no inactivity timeout

    /** @var ?non-empty-string */
    private ?string $host = null;

    private ?Compressor $compressor = null;

    private ?DelegateHttpClient $httpclient = null;

    /** @var list<UnaryInterceptor> */
    private array $unaryInterceptors = [];

    /** @var list<StreamInterceptor> */
    private array $streamInterceptors = [];

    private ?TransportCredentials $credentials = null;

    /** @var positive-int */
    private int $connectionLimit = self::DEFAULT_CONNECTION_LIMIT;

    private float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT;

    private float $transferTimeout = self::DEFAULT_TRANSFER_TIMEOUT;

    private float $inactivityTimeout = self::DEFAULT_INACTIVITY_TIMEOUT;

    private ?SocketConnector $connector = null;

    private ?Encoder $encoder = null;

    /**
     * Required for decoding the `grpc-status-details-bin` header, `status`, and `details`.
     */
    private ?Decoder $protobuf = null;

    private ?LoadBalancerFactory $loadBalancerFactory = null;

    /** @var array<string, EndpointResolver> */
    private array $endpointResolvers = [];

    public function withProtobuf(Decoder $decoder): self
    {
        $builder = clone $this;
        $builder->protobuf = $decoder;

        return $builder;
    }

    public function withEncoding(Encoder $encoder): self
    {
        $builder = clone $this;
        $builder->encoder = $encoder;

        return $builder;
    }

    public function withCompression(Compressor $compressor): self
    {
        $builder = clone $this;
        $builder->compressor = $compressor;

        return $builder;
    }

    public function withHttpClient(DelegateHttpClient $httpclient): self
    {
        $builder = clone $this;
        $builder->httpclient = $httpclient;

        return $builder;
    }

    /**
     * @param non-empty-string $host
     */
    public function withHost(string $host): self
    {
        $builder = clone $this;
        $builder->host = $host;

        return $builder;
    }

    /**
     * @no-named-arguments
     */
    public function withUnaryInterceptors(UnaryInterceptor ...$interceptors): self
    {
        $builder = clone $this;
        $builder->unaryInterceptors = [
            ...$builder->unaryInterceptors,
            ...$interceptors,
        ];

        return $builder;
    }

    /**
     * @no-named-arguments
     */
    public function withStreamInterceptors(StreamInterceptor ...$interceptors): self
    {
        $builder = clone $this;
        $builder->streamInterceptors = [
            ...$builder->streamInterceptors,
            ...$interceptors,
        ];

        return $builder;
    }

    /**
     * @param positive-int $connectionLimit
     */
    public function withConnectionLimit(int $connectionLimit): self
    {
        $builder = clone $this;
        $builder->connectionLimit = $connectionLimit;

        return $builder;
    }

    public function withConnectTimeout(float $connectTimeout): self
    {
        $builder = clone $this;
        $builder->connectTimeout = $connectTimeout;

        return $builder;
    }

    public function withTransferTimeout(float $transferTimeout): self
    {
        $builder = clone $this;
        $builder->transferTimeout = $transferTimeout;

        return $builder;
    }

    public function withInactivityTimeout(float $inactivityTimeout): self
    {
        $builder = clone $this;
        $builder->inactivityTimeout = $inactivityTimeout;

        return $builder;
    }

    public function withTransportCredentials(TransportCredentials $credentials): self
    {
        $builder = clone $this;
        $builder->credentials = $credentials;

        return $builder;
    }

    public function withSocketConnector(SocketConnector $connector): self
    {
        $builder = clone $this;
        $builder->connector = $connector;

        return $builder;
    }

    public function withLoadBalancer(LoadBalancerFactory $factory): self
    {
        $builder = clone $this;
        $builder->loadBalancerFactory = $factory;

        return $builder;
    }

    public function withEndpointResolver(string $scheme, EndpointResolver $resolver): self
    {
        $builder = clone $this;
        $builder->endpointResolvers[$scheme] = $resolver;

        return $builder;
    }

    public static function buildDefault(): Client
    {
        return new self()->build();
    }

    /**
     * @throws InvalidTarget
     */
    public function build(): Client
    {
        $target = Target::parse($this->host ?? self::DEFAULT_HOST);

        $encoder = $this->encoder ?? ProtobufEncoder::default();
        $compressor = $this->compressor ?? IdentityCompressor::Compressor;
        $protobuf = $this->protobuf ?? Decoder\Builder::buildDefault();
        $loadBalancerFactory = $this->loadBalancerFactory ?? new LoadBalancer\PickFirstFactory();
        $tlsContext = $this->credentials?->createContext();
        $uriFactory = new Http2\UriFactory($tlsContext !== null ? Internal\HttpScheme::Https : Internal\HttpScheme::Http);
        $transferTimeout = $this->transferTimeout;
        $inactivityTimeout = $this->inactivityTimeout;

        $resolver = $this->endpointResolvers[$target->scheme] ?? match (Scheme::tryFrom($target->scheme)) {
            Scheme::Dns => new EndpointResolver\DnsResolver(),
            Scheme::Passthrough => new EndpointResolver\PassthroughResolver(),
            Scheme::Ipv4, Scheme::Ipv6, Scheme::Unix => new EndpointResolver\StaticResolver(),
            null => throw new InvalidTarget($this->host ?? ''),
        };

        $controlMetadata = new Internal\AppendControlMetadataInterceptor(
            $encoder->name(),
            $compressor->name(),
        );

        // Control metadata sits innermost (closest to the transport) so every user
        // interceptor runs before the HTTP/2 headers are finalised.
        $unary = new Internal\UnaryInterceptorComposer([
            ...$this->unaryInterceptors,
            $controlMetadata,
        ]);

        $stream = new Internal\StreamInterceptorComposer([
            ...$this->streamInterceptors,
            $controlMetadata,
        ]);

        $httpclient = $this->httpclient ?? new HttpClientBuilder()
            ->usingPool(ConnectionLimitingPool::byAuthority(
                $this->connectionLimit,
                new DefaultConnectionFactory(
                    $this->connector ?? new DnsSocketConnector(),
                    new ConnectContext()
                        ->withConnectTimeout($this->connectTimeout)
                        ->withTlsContext($tlsContext),
                ),
            ))
            ->skipDefaultUserAgent()
            ->skipAutomaticCompression()
            ->skipDefaultAcceptHeader()
            ->build();

        return new Internal\AmphpHttpClient(
            new Connection\LazyConnection(
                static fn() => new Connection\DefaultConnection(
                    target: $target,
                    resolver: $resolver,
                    loadBalancerFactory: $loadBalancerFactory,
                    streams: new Http2\StreamFactory(
                        http: $httpclient,
                        uri: $uriFactory,
                        errors: new Http2\ErrorHandler($protobuf),
                        transferTimeout: $transferTimeout,
                        inactivityTimeout: $inactivityTimeout,
                        encoder: $encoder,
                        compressor: $compressor,
                    ),
                ),
            ),
            $unary,
            $stream,
        );
    }
}
