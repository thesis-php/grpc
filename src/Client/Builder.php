<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
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

    /** @var ?non-empty-string */
    private ?string $host = null;

    private ?Compressor $compressor = null;

    private ?DelegateHttpClient $httpclient = null;

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

    public static function buildDefault(Encoder $encoder): Client
    {
        return new self($encoder)->build();
    }

    public function build(): Client
    {
        return new Client(
            host: $this->host ?? self::DEFAULT_HOST,
            client: $this->httpclient ?? HttpClientBuilder::buildDefault(),
            encoder: $this->encoder,
            compressor: $this->compressor ?? IdentityCompressor::Compressor,
        );
    }
}
