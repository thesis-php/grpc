<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\TimeoutCancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use Thesis\Google\Protobuf\Timestamp;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\EndpointResolver;
use Thesis\Grpc\Client\EndpointResolverListener;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Client\Internal\CancellationError;
use Thesis\Grpc\Client\Internal\Http2\ConcurrentClientStream;
use Thesis\Grpc\Client\Resolution;
use Thesis\Grpc\Client\Target;
use Topic\Api\V1\Event;
use Topic\Api\V1\SubscribeRequest;
use Topic\Api\V1\TopicServiceClient;
use Topic\Api\V1\TopicServiceServer;
use Topic\Api\V1\TopicServiceServerRegistry;
use function Amp\delay;

#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(ConcurrentClientStream::class)]
#[CoversClass(CancellationError::class)]
final class ClientCancellationTest extends TestCase
{
    private const string ADDRESS = '127.0.0.1:50072';

    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withAddresses(self::ADDRESS)
            ->withServices(
                new EchoServiceServerRegistry(new SlowEchoServer()),
                new TopicServiceServerRegistry(new SlowTopicServer()),
            )
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testUnaryDeadlineIsDeadlineExceeded(): void
    {
        $error = self::catch(static fn() => self::echoClient()->echo(new EchoRequest('ping'), cancellation: new TimeoutCancellation(0.1)));

        self::assertSame(Code::DEADLINE_EXCEEDED, $error->statusCode);
        self::assertInstanceOf(CancelledException::class, $error->getPrevious());
    }

    public function testUnaryCancellationIsCancelled(): void
    {
        $cancellation = new DeferredCancellation();
        EventLoop::delay(0.1, static fn() => $cancellation->cancel());

        $error = self::catch(static fn() => self::echoClient()->echo(new EchoRequest('ping'), cancellation: $cancellation->getCancellation()));

        self::assertSame(Code::CANCELLED, $error->statusCode);
    }

    public function testDeadlineBeforeTheConnectionIsEstablished(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
                ->withHost('slow:///echo')
                ->withEndpointResolver('slow', new SlowResolver(self::ADDRESS))
                ->build(),
        );

        $error = self::catch(static fn() => $client->echo(new EchoRequest('ping'), cancellation: new TimeoutCancellation(0.05)));

        self::assertSame(Code::DEADLINE_EXCEEDED, $error->statusCode);
    }

    public function testStreamDeadlineIsDeadlineExceeded(): void
    {
        $stream = self::topicClient()->subscribe(new SubscribeRequest('events'), cancellation: new TimeoutCancellation(0.2));
        $stream->receive();

        $error = self::catch(static fn() => $stream->receive());

        self::assertSame(Code::DEADLINE_EXCEEDED, $error->statusCode);
    }

    public function testStreamCancellationIsCancelled(): void
    {
        $cancellation = new DeferredCancellation();
        $stream = self::topicClient()->subscribe(new SubscribeRequest('events'), cancellation: $cancellation->getCancellation());
        $stream->receive();
        EventLoop::delay(0.1, static fn() => $cancellation->cancel());

        $error = self::catch(static fn() => $stream->receive());

        self::assertSame(Code::CANCELLED, $error->statusCode);
    }

    public function testStreamIterationDeadlineIsDeadlineExceeded(): void
    {
        $stream = self::topicClient()->subscribe(new SubscribeRequest('events'), cancellation: new TimeoutCancellation(0.2));

        $error = self::catch(static function () use ($stream): void {
            foreach ($stream as $_);

        });

        self::assertSame(Code::DEADLINE_EXCEEDED, $error->statusCode);
    }

    /**
     * @param \Closure(): mixed $call
     */
    private static function catch(\Closure $call): InvokeError
    {
        try {
            $call();
        } catch (InvokeError $e) {
            return $e;
        }

        self::fail('Expected an InvokeError.');
    }

    private static function echoClient(): EchoServiceClient
    {
        return new EchoServiceClient(new Client\Builder()->withHost(self::ADDRESS)->build());
    }

    private static function topicClient(): TopicServiceClient
    {
        return new TopicServiceClient(new Client\Builder()->withHost(self::ADDRESS)->build());
    }
}

final readonly class SlowEchoServer implements EchoServiceServer
{
    #[\Override]
    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
    {
        delay(1, cancellation: $cancellation);

        return new EchoResponse($request->sentence);
    }
}

final readonly class SlowTopicServer implements TopicServiceServer
{
    #[\Override]
    public function subscribe(SubscribeRequest $request, Metadata $md, Cancellation $cancellation): iterable
    {
        yield new Event('first', '', new Timestamp(1));
        delay(1, cancellation: $cancellation);
        yield new Event('second', '', new Timestamp(2));
    }
}

final readonly class SlowResolver implements EndpointResolver
{
    /**
     * @param non-empty-string $address
     */
    public function __construct(
        private string $address,
    ) {}

    #[\Override]
    public function resolve(Target $target, EndpointResolverListener $listener, Cancellation $cancellation): Resolution
    {
        delay(1, cancellation: $cancellation);

        return new Resolution([new Endpoint(new Address($this->address))]);
    }
}
