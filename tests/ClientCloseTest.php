<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\TrackingSocketConnector;

#[CoversClass(TrackingSocketConnector::class)]
#[RequiresOperatingSystemFamily('Linux')]
final class ClientCloseTest extends TestCase
{
    private const string ADDRESS = '127.0.0.1:50074';
    private const int PORT = 50_074;

    public function testCloseReleasesConnections(): void
    {
        $server = new Server\Builder()
            ->withAddresses(self::ADDRESS)
            ->withServices(new EchoServiceServerRegistry(new readonly class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    return new EchoResponse($request->sentence);
                }
            }))
            ->build();

        $server->start();

        try {
            $client = new Client\Builder()->withHost(self::ADDRESS)->build();
            new EchoServiceClient($client)->echo(new EchoRequest('ping'));
            self::assertSame(1, self::clientConnections());

            // Checked right away: the socket must be closed by the client, not after the server reacts to GOAWAY.
            $client->close();
            self::assertSame(0, self::clientConnections());
        } finally {
            $server->stop();
        }
    }

    /**
     * Established TCP connections to the server port, i.e. the client side only.
     */
    private static function clientConnections(): int
    {
        $lines = file('/proc/net/tcp');
        self::assertNotFalse($lines);

        $pattern = \sprintf('/^\s*\d+: \S+ [0-9A-F]+:%04X 01 /', self::PORT);

        return \count(array_filter($lines, static fn(string $line) => preg_match($pattern, $line) === 1));
    }
}
