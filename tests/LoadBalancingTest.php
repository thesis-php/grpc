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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Client\Internal\Connection\DefaultConnection;
use Thesis\Grpc\Client\LoadBalancer\RoundRobin;
use Thesis\Grpc\Client\LoadBalancer\RoundRobinFactory;

#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(DefaultConnection::class)]
#[CoversClass(RoundRobin::class)]
final class LoadBalancingTest extends TestCase
{
    /** Nothing listens here. */
    private const string DEAD_ADDRESS = '127.0.0.1:50079';

    private Server $server1;

    private Server $server2;

    protected function setUp(): void
    {
        $this->server1 = new Server\Builder()
            ->withAddresses('0.0.0.0:50051')
            ->withServices(new EchoServiceServerRegistry(new IdentifyingEchoServer('server-1')))
            ->build();

        $this->server2 = new Server\Builder()
            ->withAddresses('0.0.0.0:50052')
            ->withServices(new EchoServiceServerRegistry(new IdentifyingEchoServer('server-2')))
            ->build();

        $this->server1->start();
        $this->server2->start();
    }

    protected function tearDown(): void
    {
        $this->server1->stop();
        $this->server2->stop();
    }

    /**
     * @param non-empty-string $target
     */
    #[DataProvider('providePickFirstSkipsUnreachableEndpointCases')]
    public function testPickFirstSkipsUnreachableEndpoint(string $target): void
    {
        $client = new EchoServiceClient(new Client\Builder()->withHost($target)->build());

        for ($i = 0; $i < 3; ++$i) {
            self::assertSame('[server-1] ping', $client->echo(new EchoRequest('ping'))->sentence);
        }
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function providePickFirstSkipsUnreachableEndpointCases(): iterable
    {
        yield 'unreachable first' => ['ipv4:' . self::DEAD_ADDRESS . ',127.0.0.1:50051'];

        yield 'unreachable last' => ['ipv4:127.0.0.1:50051,' . self::DEAD_ADDRESS];
    }

    public function testEveryEndpointUnreachableIsUnavailable(): void
    {
        $client = new EchoServiceClient(new Client\Builder()->withHost('ipv4:' . self::DEAD_ADDRESS)->build());

        try {
            $client->echo(new EchoRequest('ping'));
            self::fail('InvokeError expected.');
        } catch (InvokeError $e) {
            self::assertSame(Code::UNAVAILABLE, $e->statusCode);
        }
    }

    public function testRoundRobinDistributesRequests(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
                ->withHost('ipv4:127.0.0.1:50051,127.0.0.1:50052')
                ->withLoadBalancer(new RoundRobinFactory())
                ->build(),
        );

        $responses = [];
        for ($i = 0; $i < 10; ++$i) {
            $responses[] = $client->echo(new EchoRequest('ping'))->sentence;
        }

        self::assertCount(5, array_filter($responses, static fn(string $s) => str_contains($s, 'server-1')));
        self::assertCount(5, array_filter($responses, static fn(string $s) => str_contains($s, 'server-2')));
    }
}

/**
 * @internal
 */
final readonly class IdentifyingEchoServer implements EchoServiceServer
{
    public function __construct(
        private string $serverId,
    ) {}

    #[\Override]
    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
    {
        return new EchoResponse("[{$this->serverId}] {$request->sentence}");
    }
}
