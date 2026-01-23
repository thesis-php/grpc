<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Transport;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Driver\Http2Driver;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Driver\HttpDriverFactory;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\RequestHandler;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final readonly class OnlyHttp2DriverFactory implements HttpDriverFactory
{
    public function __construct(
        private LoggerInterface $logger,
        private int $streamTimeout,
        private int $connectionTimeout,
        private int $headerSizeLimit,
        private int $bodySizeLimit,
    ) {}

    #[\Override]
    public function createHttpDriver(RequestHandler $requestHandler, ErrorHandler $errorHandler, Client $client): HttpDriver
    {
        return new Http2Driver(
            requestHandler: $requestHandler,
            errorHandler: $errorHandler,
            logger: $this->logger,
            streamTimeout: $this->streamTimeout,
            connectionTimeout: $this->connectionTimeout,
            headerSizeLimit: $this->headerSizeLimit,
            bodySizeLimit: $this->bodySizeLimit,
        );
    }

    #[\Override]
    public function getApplicationLayerProtocols(): array
    {
        return ['h2'];
    }
}
