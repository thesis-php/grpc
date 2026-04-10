<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal;

use Amp\Cancellation;
use Amp\Future;
use Amp\Http\Server\HttpServer;
use Amp\NullCancellation;
use Revolt\EventLoop;
use Thesis\Grpc\Server;
use Thesis\Grpc\Server\Service;
use function Amp\async;

/**
 * @internal
 */
final class AmphpHttpServer implements Server
{
    private HttpServerState $state = HttpServerState::Idle;

    private ?string $cancellationId = null;

    public function __construct(
        private readonly HttpServer $server,
        private readonly Http2\ServerRequestHandler $requestHandler,
        private readonly Http2\ServerErrorHandler $errorHandler,
    ) {}

    #[\Override]
    public function start(Cancellation $cancellation = new NullCancellation()): void
    {
        if ($this->state !== HttpServerState::Idle) {
            return;
        }

        $this->state = HttpServerState::Serve;
        $this->server->start($this->requestHandler, $this->errorHandler);
        $this->cancellationId = $cancellation->subscribe(fn() => $this->stop());
    }

    #[\Override]
    public function stop(Cancellation $cancellation = new NullCancellation()): void
    {
        if ($this->state !== HttpServerState::Serve) {
            return;
        }

        $this->state = HttpServerState::Idle;

        if ($this->cancellationId !== null) {
            EventLoop::cancel($this->cancellationId);
            $this->cancellationId = null;
        }

        // We stop the HTTP server to prevent accepting new connections and requests on existing ones,
        // while simultaneously notifying all active handlers via the provided cancellation that they
        // should finish. Both operations are performed concurrently because {@see HttpServer::stop()}
        // suspends until all ongoing requests have been processed.
        $futures = [];
        $futures[] = async($this->server->stop(...));
        $futures[] = async($this->requestHandler->stop(...), $cancellation);

        Future\awaitAll($futures, $cancellation);
    }

    public function __destruct()
    {
        $this->stop();
    }

    #[\Override]
    public function register(Service ...$services): void
    {
        if ($this->state === HttpServerState::Serve) {
            throw new Server\ServerRunning();
        }

        $this->requestHandler->register(...$services);
    }

    #[\Override]
    public function services(): array
    {
        return $this->requestHandler->services();
    }
}
