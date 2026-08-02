<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use File\Api\V1\Chunk;
use File\Api\V1\FileInfo;
use File\Api\V1\FileServiceClient;
use File\Api\V1\FileServiceServer;
use File\Api\V1\FileServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Server\ClientStreamHandler;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(ClientStreamHandler::class)]
final class DecoratedStreamTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new FileServiceServerRegistry(new SummingFileServer()))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testStreamInterceptorWrapsTheClientStreamWithADecorator(): void
    {
        $client = new FileServiceClient(
            new Client\Builder()
                ->withStreamInterceptors(new Client\CallableStreamInterceptor(
                    static fn(Invoke $invoke, Metadata $md, Cancellation $cancellation, callable $next): ClientStream => new DoublingClientStream($next(
                        $invoke,
                        $md,
                        $cancellation,
                    )),
                ))
                ->build(),
        );

        $stream = $client->upload();

        for ($i = 0; $i < 10; ++$i) {
            $stream->send(new Chunk(random_bytes(10)));
        }

        // Each 10-byte chunk is doubled on the way out by the decorator, so the server sees 200 bytes.
        $info = $stream->close();
        self::assertSame(200, $info->size);
    }
}

final readonly class SummingFileServer implements FileServiceServer
{
    #[\Override]
    public function upload(Server\ClientStreamChannel $stream, Metadata $md, Cancellation $cancellation): FileInfo
    {
        $size = 0;

        /** @var Chunk $chunk */
        foreach ($stream as $chunk) {
            $size += \strlen($chunk->content);
        }

        return new FileInfo($size);
    }
}

/**
 * @template-extends Client\DecoratedStream<Chunk, FileInfo>
 */
final readonly class DoublingClientStream extends Client\DecoratedStream
{
    #[\Override]
    public function send(object $message): void
    {
        parent::send(new Chunk("{$message->content}{$message->content}"));
    }
}
