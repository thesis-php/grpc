<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Http\Server\Driver\ConnectionLimitingClientFactory;
use Amp\Http\Server\Driver\ConnectionLimitingServerSocketFactory;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\ExceptionHandler;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Middleware\ConcurrencyLimitingMiddleware;
use Amp\Http\Server\Middleware\ExceptionHandlerMiddleware;
use Amp\Http\Server\Middleware\ForwardedMiddleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\SocketAddress;
use Amp\Sync\LocalSemaphore;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Compression\IdentityCompressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Server;
use Thesis\Grpc\Server\Internal\Transport;

/**
 * @api
 */
final class Builder
{
    private const int DEFAULT_CONCURRENCY_LIMIT = 1_000;
    private const int DEFAULT_CONNECTION_LIMIT = 1_000;
    private const int DEFAULT_CONNECTIONS_PER_IP_LIMIT = 10;
    private const int DEFAULT_STREAM_TIMEOUT = HttpDriver::DEFAULT_STREAM_TIMEOUT;
    private const int DEFAULT_CONNECTION_TIMEOUT = HttpDriver::DEFAULT_CONNECTION_TIMEOUT;
    private const int DEFAULT_HEADER_SIZE_LIMIT = HttpDriver::DEFAULT_HEADER_SIZE_LIMIT;
    private const int DEFAULT_BODY_SIZE_LIMIT = HttpDriver::DEFAULT_BODY_SIZE_LIMIT;

    /** @var list<non-empty-string> */
    private const array ALLOWED_HTTP_METHODS = ['POST'];

    /** @var list<non-empty-string> */
    private const array DEFAULT_ADDRESSES = ['0.0.0.0:50051'];

    /** @var list<Compressor> */
    private array $compressors = [];

    /** @var list<Encoder> */
    private array $encoders = [];

    /** @var list<Middleware> */
    private array $middlewares = [];

    /** @var list<Interceptor> */
    private array $interceptors = [];

    /** @var list<Service> */
    private array $services = [];

    /** @var list<SocketAddress|non-empty-string> */
    private array $addresses = [];

    private ?LoggerInterface $logger = null;

    private ?ProxyConfig $proxy = null;

    private ?TransportCredentials $credentials = null;

    private ?ExceptionHandler $exceptionHandler = null;

    /** @var positive-int */
    private int $connectionLimit = self::DEFAULT_CONNECTION_LIMIT;

    /** @var positive-int */
    private int $connectionLimitPerIp = self::DEFAULT_CONNECTIONS_PER_IP_LIMIT;

    /** @var ?positive-int */
    private ?int $concurrencyLimit = self::DEFAULT_CONCURRENCY_LIMIT;

    /** @var positive-int */
    private int $streamTimeout = self::DEFAULT_STREAM_TIMEOUT;

    /** @var positive-int */
    private int $connectionTimeout = self::DEFAULT_CONNECTION_TIMEOUT;

    /** @var positive-int */
    private int $headerSizeLimit = self::DEFAULT_HEADER_SIZE_LIMIT;

    /** @var positive-int */
    private int $bodySizeLimit = self::DEFAULT_BODY_SIZE_LIMIT;

    /**
     * @no-named-arguments
     */
    public function withCompressors(Compressor ...$compressors): self
    {
        $builder = clone $this;
        $builder->compressors = [
            ...$builder->compressors,
            ...$compressors,
        ];

        return $builder;
    }

    /**
     * @no-named-arguments
     */
    public function withEncoders(Encoder ...$encoders): self
    {
        $builder = clone $this;
        $builder->encoders = [
            ...$builder->encoders,
            ...$encoders,
        ];

        return $builder;
    }

    /**
     * @no-named-arguments
     */
    public function withMiddlewares(Middleware ...$middlewares): self
    {
        $builder = clone $this;
        $builder->middlewares = [
            ...$builder->middlewares,
            ...$middlewares,
        ];

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
     * @no-named-arguments
     */
    public function withServices(Service ...$services): self
    {
        $builder = clone $this;
        $builder->services = [
            ...$builder->services,
            ...$services,
        ];

        return $builder;
    }

    public function withLogger(LoggerInterface $logger): self
    {
        $builder = clone $this;
        $builder->logger = $logger;

        return $builder;
    }

    public function withProxy(ProxyConfig $proxy): self
    {
        $builder = clone $this;
        $builder->proxy = $proxy;

        return $builder;
    }

    public function withTransportCredentials(TransportCredentials $credentials): self
    {
        $builder = clone $this;
        $builder->credentials = $credentials;

        return $builder;
    }

    public function withExceptionHandler(ExceptionHandler $exceptionHandler): self
    {
        $builder = clone $this;
        $builder->exceptionHandler = $exceptionHandler;

        return $builder;
    }

    /**
     * @no-named-arguments
     * @param SocketAddress|non-empty-string ...$addresses
     */
    public function withAddresses(SocketAddress|string ...$addresses): self
    {
        $builder = clone $this;
        $builder->addresses = [
            ...$builder->addresses,
            ...$addresses,
        ];

        return $builder;
    }

    /**
     * @param positive-int $limit
     */
    public function withConnectionLimit(int $limit): self
    {
        $builder = clone $this;
        $builder->connectionLimit = $limit;

        return $builder;
    }

    /**
     * @param positive-int $limit
     */
    public function withConnectionLimitPerIp(int $limit): self
    {
        $builder = clone $this;
        $builder->connectionLimitPerIp = $limit;

        return $builder;
    }

    /**
     * @param ?positive-int $limit
     */
    public function withConcurrencyLimit(?int $limit = null): self
    {
        $builder = clone $this;
        $builder->concurrencyLimit = $limit;

        return $builder;
    }

    /**
     * @param positive-int $timeout
     */
    public function withStreamTimeout(int $timeout): self
    {
        $builder = clone $this;
        $builder->streamTimeout = $timeout;

        return $builder;
    }

    /**
     * @param positive-int $timeout
     */
    public function withConnectionTimeout(int $timeout): self
    {
        $builder = clone $this;
        $builder->connectionTimeout = $timeout;

        return $builder;
    }

    /**
     * @param positive-int $limit
     */
    public function withHeaderSizeLimit(int $limit): self
    {
        $builder = clone $this;
        $builder->headerSizeLimit = $limit;

        return $builder;
    }

    /**
     * @param positive-int $limit
     */
    public function withBodySizeLimit(int $limit): self
    {
        $builder = clone $this;
        $builder->bodySizeLimit = $limit;

        return $builder;
    }

    public function build(): Server
    {
        $encoders = $this->encoders;
        // Try to search thesis/grpc-proto and set it.
        if ($encoders === []) {
            throw new \RuntimeException('No encodings has been set. Please specify at least one.');
        }

        $logger = $this->logger ?? new NullLogger();

        $serverSocketFactory = new ConnectionLimitingServerSocketFactory(
            new LocalSemaphore($this->connectionLimit),
        );

        if ($this->proxy !== null) {
            $serverSocketFactory = new ResourceServerSocketFactory();
        }

        $clientFactory = new ConnectionLimitingClientFactory(
            new SocketClientFactory($logger),
            $logger,
            $this->connectionLimitPerIp,
        );

        if ($this->proxy !== null) {
            $clientFactory = new SocketClientFactory($logger);
        }

        $middlewares = $this->middlewares;

        $compressors = [
            ...$this->compressors,
            IdentityCompressor::Compressor,
        ];

        $middlewares[] = self::encodingMiddleware($compressors);

        if ($this->concurrencyLimit !== null) {
            $middlewares[] = new ConcurrencyLimitingMiddleware($this->concurrencyLimit);
        }

        if ($this->proxy !== null) {
            $middlewares[] = new ForwardedMiddleware($this->proxy->headerType, $this->proxy->trustedProxies);
        }

        if ($this->exceptionHandler !== null) {
            $middlewares[] = new ExceptionHandlerMiddleware($this->exceptionHandler);
        }

        $server = new SocketHttpServer(
            logger: $logger,
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientFactory,
            middleware: $middlewares,
            allowedMethods: self::ALLOWED_HTTP_METHODS,
            httpDriverFactory: new Transport\OnlyHttp2DriverFactory(
                logger: $logger,
                streamTimeout: $this->streamTimeout,
                connectionTimeout: $this->connectionTimeout,
                headerSizeLimit: $this->headerSizeLimit,
                bodySizeLimit: $this->bodySizeLimit,
            ),
        );

        $addresses = $this->addresses;
        if ($addresses === []) {
            $addresses = self::DEFAULT_ADDRESSES;
        }

        $bindContext = new BindContext()
            ->withTlsContext($this->credentials?->createServerContext());

        foreach ($addresses as $address) {
            $server->expose($address, $bindContext);
        }

        return new Server(
            server: $server,
            encoderFactory: new MessageEncoderFactory($encoders),
            compressorFactory: new MessageCompressorFactory($compressors),
            services: $this->services,
        );
    }

    /**
     * @param list<Compressor> $compressors
     */
    private static function encodingMiddleware(array $compressors): Middleware\ClosureMiddleware
    {
        $compressions = implode(',', array_unique(array_map(
            static fn(Compressor $compressor) => $compressor->name(),
            $compressors,
        )));

        return new Middleware\ClosureMiddleware(static function (Request $request, RequestHandler $handler) use ($compressions): Response {
            $response = $handler->handleRequest($request);
            $response->setHeader('grpc-accept-encoding', $compressions);

            return $response;
        });
    }
}
