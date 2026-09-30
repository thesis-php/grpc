<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Http\Server\Middleware\ClosureMiddleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
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
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Compression\DeflateCompressor;
use Thesis\Grpc\Compression\GzipCompressor;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(Compressor::class)]
final class CompressionTest extends TestCase
{
    private const string RESPONSE_ENCODING_HEADER = 'x-response-encoding';

    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    return new EchoResponse($request->sentence === Metadata\AcceptEncoding::HEADER ? ($md->value($request->sentence) ?? '') : $request->sentence);
                }
            }))
            ->withCompressors(new GzipCompressor())
            ->withMiddlewares(new ClosureMiddleware(static function (Request $request, RequestHandler $handler): Response {
                $response = $handler->handleRequest($request);

                if (($encoding = $request->getHeader(self::RESPONSE_ENCODING_HEADER)) !== null) {
                    $response->setHeader(Metadata\ContentEncoding::HEADER, $encoding);
                }

                return $response;
            }))
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

    #[DataProvider('provideAcceptEncodingSentCases')]
    public function testAcceptEncodingSent(Client\Builder $builder, string $expected): void
    {
        $client = new EchoServiceClient($builder->build());
        self::assertSame($expected, $client->echo(new EchoRequest(Metadata\AcceptEncoding::HEADER))->sentence);
    }

    /**
     * @return iterable<string, array{Client\Builder, string}>
     */
    public static function provideAcceptEncodingSentCases(): iterable
    {
        yield 'default' => [
            new Client\Builder(),
            'identity',
        ];

        yield 'compression' => [
            new Client\Builder()->withCompression(new GzipCompressor()),
            'gzip,identity',
        ];

        yield 'compression and compressors' => [
            new Client\Builder()
                ->withCompression(new GzipCompressor())
                ->withCompressors(new DeflateCompressor(), new GzipCompressor()),
            'gzip,deflate,identity',
        ];
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

        $this->expectExceptionMessage('A grpc error with status code "UNIMPLEMENTED" and message "Decompression is not supported by server: strrev" occurred');
        $client->echo(new EchoRequest('Hello, gRPC'));
    }

    public function testUnknownForClientResponseCompression(): void
    {
        $client = new EchoServiceClient(new Client\Builder()->build());

        try {
            $client->echo(new EchoRequest('Hello, gRPC'), new Metadata()->with(self::RESPONSE_ENCODING_HEADER, 'snappy'));
            self::fail('InvokeError expected.');
        } catch (InvokeError $e) {
            self::assertSame(Code::INTERNAL, $e->statusCode);
            self::assertSame("Compression algorithm 'snappy' is unavailable.", $e->statusMessage);
        }
    }
}
