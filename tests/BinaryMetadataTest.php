<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use File\Api\V1\Chunk;
use File\Api\V1\FileInfo;
use File\Api\V1\FileServiceServer;
use File\Api\V1\FileServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Server\CallableStreamInterceptor;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;
use Thesis\Grpc\Server\StreamInfo;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
final class BinaryMetadataTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(
                new FileServiceServerRegistry(new readonly class implements FileServiceServer {
                    #[\Override]
                    public function upload(Server\ClientStreamChannel $stream, Metadata $md, Cancellation $cancellation): FileInfo
                    {
                        iterator_to_array($stream);

                        return new FileInfo();
                    }
                }),
                new EchoServiceServerRegistry(new readonly class implements EchoServiceServer {
                    #[\Override]
                    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                    {
                        return new EchoResponse();
                    }
                }),
            )
            ->withStreamInterceptors(new CallableStreamInterceptor(static function (
                ServerStream $stream,
                StreamInfo $info,
                Metadata $md,
                Cancellation $cancellation,
                callable $next,
            ): void {
                $stream->trailers->join(new Metadata()->with('x-test-bin', ...$md['x-test-bin']));
                $next($stream, $info, $md, $cancellation);
            }))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testBinaryMetadata(): void
    {
        $stream = new Client\Builder()->build()->createStream(
            new Invoke('/file.api.v1.FileService/Upload', FileInfo::class, RpcType::ClientStream),
            new Metadata()->with('x-test-bin', "\xab\xab\xab", "\x00\xff"),
        );

        $stream->send(new Chunk('content'));
        $stream->close();
        $stream->receive();

        self::assertSame(["\xab\xab\xab", "\x00\xff"], $stream->trailers()['x-test-bin']);
    }

    public function testMalformedBinaryMetadata(): void
    {
        $request = new Request('http://127.0.0.1:50051/echos.api.v1.EchoService/Echo', 'POST');
        $request->setProtocolVersions(['2']);
        $request->setHeaders([
            'content-type' => 'application/grpc',
            'x-test-bin' => 'q6u!',
        ]);

        $response = HttpClientBuilder::buildDefault()->request($request);
        $response->getBody()->buffer();

        self::assertSame([
            'grpc-status' => [(string) Code::INTERNAL->value],
            'grpc-message' => ['Malformed binary metadata in header "x-test-bin"'],
        ], $response->getTrailers()->await()->getHeaders());
    }
}
