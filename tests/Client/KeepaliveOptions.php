<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Socket\ResourceSocket;
use PHPUnit\Framework\TestCase;

/**
 * Reads the TCP keepalive options of a connected socket (Linux option names).
 */
final class KeepaliveOptions
{
    /**
     * @return array{keepalive: mixed, idle: mixed, interval: mixed, count: mixed}
     */
    public static function of(mixed $socket): array
    {
        TestCase::assertInstanceOf(ResourceSocket::class, $socket);
        $resource = $socket->getResource();
        TestCase::assertIsResource($resource);
        $raw = socket_import_stream($resource);
        TestCase::assertInstanceOf(\Socket::class, $raw);

        return [
            'keepalive' => socket_get_option($raw, \SOL_SOCKET, \SO_KEEPALIVE),
            'idle' => socket_get_option($raw, \SOL_TCP, \TCP_KEEPIDLE),
            'interval' => socket_get_option($raw, \SOL_TCP, \TCP_KEEPINTVL),
            'count' => socket_get_option($raw, \SOL_TCP, \TCP_KEEPCNT),
        ];
    }
}
