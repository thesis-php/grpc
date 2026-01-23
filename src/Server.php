<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Http\Server\HttpServer;
use Thesis\Grpc\Server\Internal\Http2;

/**
 * @api
 */
final readonly class Server
{
    /**
     * @param list<Server\Service> $services
     */
    public function __construct(
        private HttpServer $server,
        private Server\MessageEncoderFactory $encoderFactory,
        private Server\MessageCompressorFactory $compressorFactory,
        private array $services,
    ) {}

    public function start(): void
    {
        $this->server->start(
            new Http2\ServerRequestHandler(
                $this->encoderFactory,
                $this->compressorFactory,
                $this->services,
            ),
            new Http2\ServerErrorHandler(),
        );
    }

    public function stop(): void
    {
        $this->server->stop();
    }
}
