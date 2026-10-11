<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Amp\Socket\ConnectContext;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketConnector;

/**
 * The amphp pool keeps idle connections open and cannot be closed, while closing an HTTP/2 connection
 * without active streams only sends GOAWAY. So the client remembers the sockets it opens to close them itself.
 *
 * @internal
 */
final class TrackingSocketConnector implements SocketConnector
{
    /** @var array<int, Socket> */
    private array $sockets = [];

    public function __construct(
        private readonly SocketConnector $connector,
    ) {}

    #[\Override]
    public function connect(SocketAddress|string $uri, ?ConnectContext $context = null, ?Cancellation $cancellation = null): Socket
    {
        $socket = $this->connector->connect($uri, $context, $cancellation);

        $id = spl_object_id($socket);
        $this->sockets[$id] = $socket;
        $socket->onClose(function () use ($id): void {
            unset($this->sockets[$id]);
        });

        return $socket;
    }

    public function close(): void
    {
        foreach ($this->sockets as $socket) {
            $socket->close();
        }
    }
}
