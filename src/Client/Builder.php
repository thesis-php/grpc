<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Http\Client\Connection\ConnectionLimitingPool;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Socket\ConnectContext;
use Amp\Socket\SocketConnector;
use Thesis\Grpc\Client;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Compression\IdentityCompressor;
use Thesis\Grpc\Encoding\Encoder;

/**
 * @api
 */
final class Builder
{
    private const string DEFAULT_HOST = 'http://localhost:50051';
    private const float DEFAULT_CONNECT_TIMEOUT = 10;
    private const int DEFAULT_CONNECTION_LIMIT = \PHP_INT_MAX;

    /** @var ?non-empty-string */
    private ?string $host = null;

    private ?Compressor $compressor = null;

    private ?DelegateHttpClient $httpclient = null;

    /** @var list<Interceptor> */
    private array $interceptors = [];

    private ?TransportCredentials $credentials = null;

    /** @var positive-int */
    private int $connectionLimit = self::DEFAULT_CONNECTION_LIMIT;

    private float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT;

    private ?SocketConnector $connector = null;

    public function __construct(
        private Encoder $encoder,
    ) {}

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
    public function withInterceptors(Interceptor ...$interceptors): self
    {
        $builder = clone $this;
        $builder->interceptors = [
            ...$builder->interceptors,
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

    public static function buildDefault(Encoder $encoder): Client
    {
        return new self($encoder)->build();
    }

    public function build(): Client
    {
        return new Client(
            host: $this->host ?? self::DEFAULT_HOST,
            client: $this->httpclient ?? new HttpClientBuilder()
                ->usingPool(ConnectionLimitingPool::byAuthority(
                    $this->connectionLimit,
                    new DefaultConnectionFactory(
                        $this->connector,
                        new ConnectContext()
                            ->withConnectTimeout($this->connectTimeout)
                            ->withTlsContext($this->credentials?->buildTlsContext()),
                    ),
                ))
                ->skipDefaultUserAgent()
                ->skipAutomaticCompression()
                ->skipDefaultAcceptHeader()
                ->build(),
            encoder: $this->encoder,
            compressor: $this->compressor ?? IdentityCompressor::Compressor,
            interceptors: $this->interceptors,
        );
    }
}
