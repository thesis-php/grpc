<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Chat\Api\V1\Message;
use Chat\Api\V1\MessengerServiceServer;
use Chat\Api\V1\MessengerServiceServerRegistry;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use File\Api\V1\Chunk;
use File\Api\V1\FileInfo;
use File\Api\V1\FileServiceServer;
use File\Api\V1\FileServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Server\BidirectionalStreamChannel;
use Thesis\Grpc\Server\CallableStreamInterceptor;
use Thesis\Grpc\Server\CallableUnaryInterceptor;
use Thesis\Grpc\Server\ClientStreamChannel;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;
use Thesis\Grpc\Server\StreamInfo;
use Topic\Api\V1\Event;
use Topic\Api\V1\SubscribeRequest;
use Topic\Api\V1\TopicServiceServer;
use Topic\Api\V1\TopicServiceServerRegistry;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(StreamInfo::class)]
final class ResponseMetadataTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(
                new EchoServiceServerRegistry(new readonly class implements EchoServiceServer {
                    #[\Override]
                    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                    {
                        if ($request->sentence === 'error') {
                            throw new InvokeError(Code::FAILED_PRECONDITION, 'error');
                        }

                        return new EchoResponse($request->sentence);
                    }
                }),
                new TopicServiceServerRegistry(new readonly class implements TopicServiceServer {
                    #[\Override]
                    public function subscribe(SubscribeRequest $request, Metadata $md, Cancellation $cancellation): iterable
                    {
                        yield new Event($request->topic);
                    }
                }),
                new FileServiceServerRegistry(new readonly class implements FileServiceServer {
                    #[\Override]
                    public function upload(ClientStreamChannel $stream, Metadata $md, Cancellation $cancellation): FileInfo
                    {
                        ResponseMetadataTest::setMetadata($stream->responseHeaders, $stream->trailers);

                        return new FileInfo(\count(iterator_to_array($stream, preserve_keys: false)));
                    }
                }),
                new MessengerServiceServerRegistry(new readonly class implements MessengerServiceServer {
                    #[\Override]
                    public function chat(BidirectionalStreamChannel $stream, Metadata $md, Cancellation $cancellation): void
                    {
                        ResponseMetadataTest::setMetadata($stream->responseHeaders, $stream->trailers);

                        foreach ($stream as $message) {
                            $stream->send($message);
                        }

                        $stream->close();
                    }
                }),
            )
            ->withUnaryInterceptors(new CallableUnaryInterceptor(static function (
                object $request,
                StreamInfo $info,
                Metadata $md,
                Cancellation $cancellation,
                callable $next,
            ): object {
                self::setMetadata($info->responseHeaders, $info->trailers);

                return $next($request, $info, $md, $cancellation);
            }))
            ->withStreamInterceptors(new CallableStreamInterceptor(static function (
                ServerStream $stream,
                StreamInfo $info,
                Metadata $md,
                Cancellation $cancellation,
                callable $next,
            ): void {
                if ($info->type === RpcType::ServerStream) {
                    self::setMetadata($info->responseHeaders, $info->trailers);
                }

                $next($stream, $info, $md, $cancellation);
            }))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    /**
     * @param Invoke<object, object> $invoke
     */
    #[DataProvider('provideResponseMetadataCases')]
    public function testResponseMetadata(Invoke $invoke, object $request, object $response): void
    {
        $stream = new Client\Builder()->build()->createStream($invoke);
        $stream->send($request);
        $stream->close();

        self::assertEquals($response, $stream->receive());

        $headers = $stream->headers();
        self::assertSame(['header'], $headers['x-header']);
        self::assertSame(["\x00\xff"], $headers['x-header-bin']);
        self::assertStringStartsWith('application/grpc', $headers->value('content-type') ?? '');
        self::assertSame(['trailer'], $stream->trailers()['x-trailer']);
    }

    /**
     * @return iterable<array-key, array{Invoke<object, object>, object, object}>
     */
    public static function provideResponseMetadataCases(): iterable
    {
        yield 'unary' => [
            new Invoke('/echos.api.v1.EchoService/Echo', EchoResponse::class, RpcType::Unary),
            new EchoRequest('ping'),
            new EchoResponse('ping'),
        ];

        yield 'server stream' => [
            new Invoke('/topic.api.v1.TopicService/Subscribe', Event::class, RpcType::ServerStream),
            new SubscribeRequest('payments'),
            new Event('payments'),
        ];

        yield 'client stream' => [
            new Invoke('/file.api.v1.FileService/Upload', FileInfo::class, RpcType::ClientStream),
            new Chunk('content'),
            new FileInfo(1),
        ];

        yield 'bidirectional stream' => [
            new Invoke('/chat.api.v1.MessengerService/Chat', Message::class, RpcType::BidirectionalStream),
            new Message('hello'),
            new Message('hello'),
        ];
    }

    public function testErrorBeforeFirstMessage(): void
    {
        $stream = new Client\Builder()->build()->createStream(
            new Invoke('/echos.api.v1.EchoService/Echo', EchoResponse::class, RpcType::Unary),
        );
        $stream->send(new EchoRequest('error'));
        $stream->close();

        try {
            $stream->receive();
            self::fail('InvokeError expected.');
        } catch (InvokeError $e) {
            self::assertSame(Code::FAILED_PRECONDITION, $e->statusCode);
        }

        self::assertSame(['header'], $stream->headers()['x-header']);
        self::assertSame(['trailer'], $stream->trailers()['x-trailer']);
    }

    public static function setMetadata(Metadata $headers, Metadata $trailers): void
    {
        $headers->join(new Metadata([
            'x-header' => 'header',
            'x-header-bin' => "\x00\xff",
            'content-type' => 'text/plain',
        ]));
        $trailers->join(new Metadata(['x-trailer' => 'trailer']));
    }
}
