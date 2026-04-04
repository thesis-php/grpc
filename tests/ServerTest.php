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
use Thesis\Grpc\Server\Internal\AmphpHttpServer;
use Thesis\Grpc\Server\ServerRunning;

#[CoversClass(AmphpHttpServer::class)]
final class ServerTest extends TestCase
{
    public function testRegisterService(): void
    {
        $server = new Server\Builder()->build();
        $server->register(...new EchoServiceServerRegistry(new class implements EchoServiceServer {
            #[\Override]
            public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
            {
                return new EchoResponse($request->sentence);
            }
        })->services());

        $server->start();
        $client = new EchoServiceClient(new Client\Builder()->build());
        self::assertSame('Hello, gRPC', $client->echo(new EchoRequest('Hello, gRPC'))->sentence);
        $server->stop();
    }

    public function testRegisterServiceInRunningServer(): void
    {
        $server = new Server\Builder()->build();
        $server->start();
        $this->expectExceptionObject(new ServerRunning());
        $server->register(...new EchoServiceServerRegistry(new class implements EchoServiceServer {
            #[\Override]
            public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
            {
                return new EchoResponse($request->sentence);
            }
        })->services());
    }
}
