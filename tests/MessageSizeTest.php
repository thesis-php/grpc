<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use File\Api\V1\Chunk;
use File\Api\V1\FileInfo;
use File\Api\V1\FileServiceClient;
use File\Api\V1\FileServiceServer;
use File\Api\V1\FileServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;
use Topic\Api\V1\Event;
use Topic\Api\V1\SubscribeRequest;
use Topic\Api\V1\TopicServiceClient;
use Topic\Api\V1\TopicServiceServer;
use Topic\Api\V1\TopicServiceServerRegistry;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
final class MessageSizeTest extends TestCase
{
    public const int PAYLOAD_SIZE = 1_024 * 1_024;

    public function testUnary(): void
    {
        $server = self::server(new Server\Builder());
        $sentence = str_repeat('a', 271_828);

        try {
            self::assertSame($sentence, new EchoServiceClient(new Client\Builder()->build())->echo(new EchoRequest($sentence))->sentence);
        } finally {
            $server->stop();
        }
    }

    public function testClientStream(): void
    {
        $server = self::server(new Server\Builder());

        try {
            $stream = new FileServiceClient(new Client\Builder()->build())->upload();

            for ($i = 0; $i < 4; ++$i) {
                $stream->send(new Chunk(random_bytes(64 * 1_024)));
            }

            self::assertSame(4 * 64 * 1_024, $stream->close()->size);
        } finally {
            $server->stop();
        }
    }

    public function testServerStream(): void
    {
        self::markTestSkipped('Corrupts and hangs until https://github.com/amphp/http-server/pull/396 is released.');

        $server = self::server(new Server\Builder()); // @phpstan-ignore deadCode.unreachable (the test is skipped until the amphp fix is released)

        try {
            $events = iterator_to_array(new TopicServiceClient(new Client\Builder()->build())->subscribe(new SubscribeRequest('11')), preserve_keys: false);

            self::assertSame(11 * self::PAYLOAD_SIZE, array_sum(array_map(static fn(Event $event): int => \strlen($event->payload), $events)));
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('provideMessageTooLargeCases')]
    public function testMessageTooLarge(Server\Builder $serverBuilder, Client\Builder $clientBuilder): void
    {
        $server = self::server($serverBuilder);

        try {
            $this->expectExceptionObject(new InvokeError(Code::RESOURCE_EXHAUSTED, 'Received message larger than max (2051 vs. 1024)'));
            new EchoServiceClient($clientBuilder->build())->echo(new EchoRequest(str_repeat('a', 2_048)));
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<array-key, array{Server\Builder, Client\Builder}>
     */
    public static function provideMessageTooLargeCases(): iterable
    {
        yield 'server receives' => [
            new Server\Builder()->withMaxReceiveMessageSize(1_024),
            new Client\Builder(),
        ];

        yield 'client receives' => [
            new Server\Builder(),
            new Client\Builder()->withMaxReceiveMessageSize(1_024),
        ];
    }

    private static function server(Server\Builder $builder): Server
    {
        $server = $builder
            ->withServices(
                new EchoServiceServerRegistry(new readonly class implements EchoServiceServer {
                    #[\Override]
                    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                    {
                        return new EchoResponse($request->sentence);
                    }
                }),
                new FileServiceServerRegistry(new readonly class implements FileServiceServer {
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
                }),
                new TopicServiceServerRegistry(new readonly class implements TopicServiceServer {
                    #[\Override]
                    public function subscribe(SubscribeRequest $request, Metadata $md, Cancellation $cancellation): iterable
                    {
                        for ($i = 0; $i < (int) $request->topic; ++$i) {
                            yield new Event(payload: str_repeat('x', MessageSizeTest::PAYLOAD_SIZE));
                        }
                    }
                }),
            )
            ->build();
        $server->start();

        return $server;
    }
}
