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
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Metadata\Timeout;
use function Amp\async;
use function Amp\delay;

/**
 * @api
 */
#[CoversClass(Server::class)]
#[CoversClass(Client::class)]
final class GrpcTimeoutTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    async(delay(...), 0.5)->await($cancellation);

                    return new EchoResponse($request->sentence);
                }
            }))
            ->build();
        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testTimeoutNotSet(): void
    {
        $client = new EchoServiceClient(new Client\Builder()->build());

        self::assertSame('Hello, gRPC', $client->echo(new EchoRequest('Hello, gRPC'))->sentence);
    }

    public function testServerTimeout(): void
    {
        $client = new EchoServiceClient(new Client\Builder()->build());

        self::expectExceptionMessage('A grpc error with status code "DEADLINE_EXCEEDED" and message "" occurred');
        $client->echo(new EchoRequest('Hello, gRPC'), new Metadata()->withKey(Timeout::milliseconds(200)));
    }
}
