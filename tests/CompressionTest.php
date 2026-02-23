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
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Compression\GzipCompressor;

#[CoversClass(Server::class)]
#[CoversClass(Client::class)]
#[CoversClass(Compressor::class)]
final class CompressionTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    return new EchoResponse($request->sentence);
                }
            }))
            ->withCompressors(new GzipCompressor())
            ->build();
        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testCompressionNotUsed(): void
    {
        $client = new EchoServiceClient(new Client\Builder()->build());
        self::assertSame('Hello, gRPC', $client->echo(new EchoRequest('Hello, gRPC'))->sentence);
    }

    public function testGzipCompressionUsed(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
            ->withCompression(new GzipCompressor())
            ->build(),
        );
        self::assertSame('Hello, gRPC', $client->echo(new EchoRequest('Hello, gRPC'))->sentence);
    }

    public function testUnknownForServerCompressionUsed(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
                ->withCompression(new class implements Compressor {
                    #[\Override]
                    public function name(): string
                    {
                        return 'strrev';
                    }

                    #[\Override]
                    public function compress(string $buffer): string
                    {
                        return strrev($buffer);
                    }

                    #[\Override]
                    public function decompress(string $buffer): string
                    {
                        return strrev($buffer);
                    }
                })
                ->build(),
        );

        self::expectExceptionMessage('A grpc error with status code "UNIMPLEMENTED" and message "Decompression is not supported by server: strrev" occurred');
        $client->echo(new EchoRequest('Hello, gRPC'));
    }
}
