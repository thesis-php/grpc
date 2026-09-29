<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Socket\ConnectContext;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketConnector;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\KeepaliveOptions;
use Thesis\Grpc\Client\KeepaliveSocketConnector;

#[CoversClass(Client\Builder::class)]
#[CoversClass(KeepaliveSocketConnector::class)]
final class KeepaliveTest extends TestCase
{
    private const string ADDRESS = '127.0.0.1:50061';

    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withAddresses(self::ADDRESS)
            ->withServices(new EchoServiceServerRegistry(new KeepaliveEchoServer()))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testClientConnectionsUseKeepalive(): void
    {
        $recorder = new RecordingSocketConnector(new DnsSocketConnector());
        $client = new Client\Builder()
            ->withHost(self::ADDRESS)
            ->withSocketConnector($recorder)
            ->withKeepalive(idle: 7, interval: 3, count: 4)
            ->build();

        self::assertSame('ping', new EchoServiceClient($client)->echo(new EchoRequest('ping'))->sentence);
        self::assertNotNull($recorder->socket);
        self::assertSame(
            ['keepalive' => 1, 'idle' => 7, 'interval' => 3, 'count' => 4],
            KeepaliveOptions::of($recorder->socket),
        );

        $client->close();
    }

    public function testKeepaliveIsOffByDefault(): void
    {
        $recorder = new RecordingSocketConnector(new DnsSocketConnector());
        $client = new Client\Builder()
            ->withHost(self::ADDRESS)
            ->withSocketConnector($recorder)
            ->build();

        new EchoServiceClient($client)->echo(new EchoRequest('ping'));

        self::assertNotNull($recorder->socket);
        self::assertSame(0, KeepaliveOptions::of($recorder->socket)['keepalive']);

        $client->close();
    }
}

final class RecordingSocketConnector implements SocketConnector
{
    public ?Socket $socket = null;

    public function __construct(
        private readonly SocketConnector $connector,
    ) {}

    #[\Override]
    public function connect(SocketAddress|string $uri, ?ConnectContext $context = null, ?Cancellation $cancellation = null): Socket
    {
        return $this->socket = $this->connector->connect($uri, $context, $cancellation);
    }
}

final readonly class KeepaliveEchoServer implements EchoServiceServer
{
    #[\Override]
    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
    {
        return new EchoResponse($request->sentence);
    }
}
