<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
final class NotImplementedTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    return new EchoResponse();
                }
            }))
            ->build();
        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testEmptyMethod(): void
    {
        $client = new Client\Builder()->build();

        $this->expectExceptionMessage('A grpc error with status code "UNIMPLEMENTED" and message "Malformed method name: /" occurred');
        $client->invoke(new EchoRequest(), new Invoke('/', EchoResponse::class));
    }

    public function testServiceNotImplemented(): void
    {
        $client = new Client\Builder()->build();

        $this->expectExceptionMessage('A grpc error with status code "UNIMPLEMENTED" and message "Unknown service echos.api.v2.EchoService" occurred');
        $client->invoke(new EchoRequest(), new Invoke('/echos.api.v2.EchoService/Echo', EchoResponse::class));
    }

    public function testMethodNotImplemented(): void
    {
        $client = new Client\Builder()->build();

        $this->expectExceptionMessage('A grpc error with status code "UNIMPLEMENTED" and message "Unknown method Ping for service echos.api.v1.EchoService" occurred');
        $client->invoke(new EchoRequest(), new Invoke('/echos.api.v1.EchoService/Ping', EchoResponse::class));
    }
}
