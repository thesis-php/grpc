<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\RetrySocketConnector;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;
use function Amp\async;
use function Amp\delay;

#[CoversClass(AmphpHttpServer::class)]
final class GracefulShutdownTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    async(delay(...), 1);

                    return new EchoResponse($request->sentence);
                }
            }))
            ->build();
        $this->server->start();
    }

    public function testGracefulShutdown(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
                ->withSocketConnector(new RetrySocketConnector(new DnsSocketConnector(), 1, 1))
                ->build(),
        );

        $successFuture = async($client->echo(...), new EchoRequest('Hello, gRPC'));
        delay(0.1);
        $this->server->stop();
        $failFuture = async($client->echo(...), new EchoRequest('Hello, gRPC'));
        self::assertEquals(new EchoResponse('Hello, gRPC'), $successFuture->await());
        $this->expectExceptionMessage("Connection to '127.0.0.1:50051' failed");
        $failFuture->await();
    }
}
