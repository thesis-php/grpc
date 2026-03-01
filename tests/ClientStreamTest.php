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
use Thesis\Grpc\Server\CallableInterceptor;
use Thesis\Grpc\Server\ClientStreamHandler;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;
use Thesis\Grpc\Server\StreamInfo;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(ClientStreamHandler::class)]
final class ClientStreamTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new FileServiceServerRegistry(new ClientStreamServer()))
            ->withInterceptors(new CallableInterceptor(static function (
                ServerStream $stream,
                StreamInfo $info,
                Metadata $md,
                Cancellation $cancellation,
                callable $next,
            ): void {
                $next(
                    new DoubleContentStream($stream),
                    $info,
                    $md,
                    $cancellation,
                );
            }))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testClientStream(): void
    {
        $client = new FileServiceClient(new Client\Builder()->build());

        $stream = $client->upload();

        for ($i = 0; $i < 10; ++$i) {
            $stream->send(new Chunk(random_bytes(10)));
        }

        $info = $stream->close();
        self::assertSame(100, $info->size);
    }

    public function testWrapServerStream(): void
    {
        $client = new FileServiceClient(new Client\Builder()->build());

        $stream = $client->upload(new Metadata()->with('double-content', '1'));

        for ($i = 0; $i < 10; ++$i) {
            $stream->send(new Chunk(random_bytes(10)));
        }

        $info = $stream->close();
        self::assertSame(200, $info->size);
    }
}

final readonly class ClientStreamServer implements FileServiceServer
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
 * @template-covariant In of object
 * @template Out of object
 * @template-extends Server\DecoratedStream<In, Out>
 */
final class DoubleContentStream extends Server\DecoratedStream
{
    /**
     * @param ServerStream<In, Out> $stream
     */
    public function __construct(ServerStream $stream)
    {
        parent::__construct($stream);
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        $doubleContent = $this->headers->has('double-content');

        foreach (parent::getIterator() as $chunk) {
            if ($doubleContent && $chunk instanceof Chunk) {
                $chunk = new Chunk("{$chunk->content}{$chunk->content}");
            }

            yield $chunk; // @phpstan-ignore generator.valueType
        }
    }
}
