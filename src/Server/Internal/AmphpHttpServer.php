<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal;

use Amp\Cancellation;
use Amp\Http\Server\HttpServer;
use Amp\NullCancellation;
use Thesis\Grpc\Server;

/**
 * @internal
 */
final readonly class AmphpHttpServer implements Server
{
    public function __construct(
        private HttpServer $server,
        private Http2\ServerRequestHandler $requestHandler,
        private Http2\ServerErrorHandler $errorHandler,
    ) {}

    #[\Override]
    public function start(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->server->start(
            $this->requestHandler,
            $this->errorHandler,
        );
    }

    #[\Override]
    public function stop(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->server->stop();
    }
}
