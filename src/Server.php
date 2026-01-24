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
    public function __construct(
        private HttpServer $server,
        private Http2\ServerRequestHandler $requestHandler,
        private Http2\ServerErrorHandler $errorHandler,
    ) {}

    public function start(): void
    {
        $this->server->start(
            $this->requestHandler,
            $this->errorHandler,
        );
    }

    public function stop(): void
    {
        $this->server->stop();
    }
}
