<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use Google\Rpc\Code;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Client\Internal\Connection\DefaultConnection;
use Thesis\Grpc\Client\LoadBalancer\PickFirst;
use Thesis\Grpc\Client\LoadBalancer\RoundRobin;
use Thesis\Grpc\Client\LoadBalancer\RoundRobinFactory;
use Thesis\Grpc\Client\Retry;

#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(DefaultConnection::class)]
#[CoversClass(Retry::class)]
#[CoversClass(RoundRobin::class)]
#[CoversClass(PickFirst::class)]
final class RetryFailoverTest extends TestCase
{
    private const string DEAD = '127.0.0.1:50051';
    private const string LIVE = '127.0.0.1:50052';

    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withAddresses('0.0.0.0:50052')
            ->withServices(new EchoServiceServerRegistry(new SucceedingEchoServer()))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testRetryFailsOverFromADeadEndpointToALiveOne(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
                ->withHost(\sprintf('ipv4:%s,%s', self::DEAD, self::LIVE))
                ->withLoadBalancer(new RoundRobinFactory())
                ->withRetry(new Retry(maxAttempts: 3, initialBackoff: 0.0))
                ->build(),
        );

        for ($i = 0; $i < 10; ++$i) {
            self::assertSame('ok', $client->echo(new EchoRequest('ping'))->sentence);
        }
    }

    public function testWithoutRetryADeadEndpointSurfacesAsUnavailable(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
                ->withHost(\sprintf('ipv4:%s', self::DEAD))
                ->build(),
        );

        try {
            $client->echo(new EchoRequest('ping'));
            self::fail('Expected an InvokeError for the dead endpoint.');
        } catch (InvokeError $e) {
            self::assertSame(Code::UNAVAILABLE, $e->statusCode);
        }
    }

    public function testRetryDoesNotHelpWhenEveryEndpointIsDead(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
                ->withHost(\sprintf('ipv4:%s,127.0.0.1:50053', self::DEAD))
                ->withLoadBalancer(new RoundRobinFactory())
                ->withRetry(new Retry(maxAttempts: 3, initialBackoff: 0.0))
                ->build(),
        );

        $this->expectException(InvokeError::class);
        $client->echo(new EchoRequest('ping'));
    }
}

/**
 * @internal
 */
final readonly class SucceedingEchoServer implements EchoServiceServer
{
    #[\Override]
    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
    {
        return new EchoResponse('ok');
    }
}
