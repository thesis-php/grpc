<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Socket\DnsSocketConnector;
use Amp\Socket\ServerSocket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function Amp\Socket\listen;

#[CoversClass(KeepaliveSocketConnector::class)]
final class KeepaliveSocketConnectorTest extends TestCase
{
    private ServerSocket $server;

    protected function setUp(): void
    {
        $this->server = listen('127.0.0.1:0');
    }

    protected function tearDown(): void
    {
        $this->server->close();
    }

    public function testEnablesKeepaliveWithTheGivenTimings(): void
    {
        $socket = new KeepaliveSocketConnector(new DnsSocketConnector(), idle: 7, interval: 3, count: 4)
            ->connect($this->server->getAddress()->toString());

        self::assertSame(
            ['keepalive' => 1, 'idle' => 7, 'interval' => 3, 'count' => 4],
            KeepaliveOptions::of($socket),
        );

        $socket->close();
    }

    public function testPlainConnectionsHaveNoKeepalive(): void
    {
        $socket = new DnsSocketConnector()->connect($this->server->getAddress()->toString());

        self::assertSame(0, KeepaliveOptions::of($socket)['keepalive']);

        $socket->close();
    }
}
