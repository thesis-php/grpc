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
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Metadata\Timeout;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;
use function Amp\async;
use function Amp\delay;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
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

        try {
            $client->echo(new EchoRequest('Hello, gRPC'), new Metadata()->withKey(Timeout::milliseconds(200)));
            self::fail('InvokeError expected.');
        } catch (InvokeError $e) {
            self::assertSame(Code::DEADLINE_EXCEEDED, $e->statusCode);
        }
    }
}
