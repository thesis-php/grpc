<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketConnector;

/**
 * Enables TCP keepalive on every connection made by the decorated connector, so that the
 * operating system detects a dead peer (a crashed host, a network partition, a half-open
 * connection) even while the connection is idle, e.g. a long-lived stream waiting for data.
 *
 * A dead peer is detected after roughly `idle + interval * count` seconds of silence.
 * It does not detect a peer whose host is alive but whose process hangs.
 *
 * Requires the "sockets" extension.
 *
 * @api
 */
final readonly class KeepaliveSocketConnector implements SocketConnector
{
    /**
     * @param positive-int $idle seconds of idleness before the first probe
     * @param positive-int $interval seconds between unanswered probes
     * @param positive-int $count unanswered probes before the connection is dropped
     * @throws KeepaliveUnavailable
     */
    public function __construct(
        private SocketConnector $connector,
        private int $idle = 10,
        private int $interval = 10,
        private int $count = 3,
    ) {
        if (!\extension_loaded('sockets')) {
            throw new KeepaliveUnavailable();
        }
    }

    #[\Override]
    public function connect(
        SocketAddress|string $uri,
        ?ConnectContext $context = null,
        ?Cancellation $cancellation = null,
    ): Socket {
        $socket = $this->connector->connect($uri, $context, $cancellation);

        if ($socket instanceof ResourceSocket) {
            $this->enable($socket);
        }

        return $socket;
    }

    /**
     * @throws ConnectException
     */
    private function enable(ResourceSocket $socket): void
    {
        $resource = $socket->getResource();
        if (!\is_resource($resource)) {
            return;
        }

        $raw = socket_import_stream($resource);
        if ($raw === false) {
            return;
        }

        $options = [[\SOL_SOCKET, \SO_KEEPALIVE, 1]];

        // Linux and most BSDs, macOS names the idle time TCP_KEEPALIVE.
        if (\defined('TCP_KEEPIDLE')) {
            $options[] = [\SOL_TCP, \TCP_KEEPIDLE, $this->idle];
        } elseif (\defined('TCP_KEEPALIVE') && \is_int($idle = \constant('TCP_KEEPALIVE'))) {
            $options[] = [\SOL_TCP, $idle, $this->idle];
        }

        if (\defined('TCP_KEEPINTVL')) {
            $options[] = [\SOL_TCP, \TCP_KEEPINTVL, $this->interval];
        }

        if (\defined('TCP_KEEPCNT')) {
            $options[] = [\SOL_TCP, \TCP_KEEPCNT, $this->count];
        }

        foreach ($options as [$level, $option, $value]) {
            if (!socket_set_option($raw, $level, $option, $value)) {
                $socket->close();

                throw new ConnectException(\sprintf(
                    'Cannot enable TCP keepalive on the connection to "%s": %s',
                    $socket->getRemoteAddress()->toString(),
                    socket_strerror(socket_last_error($raw)),
                ));
            }
        }
    }
}
