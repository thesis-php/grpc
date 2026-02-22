<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use BcMath\Number;
use Google\Protobuf\Timestamp;
use Google\Rpc\Code;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Server\ServerStreamHandler;
use Topic\Api\V1\Event;
use Topic\Api\V1\SubscribeRequest;
use Topic\Api\V1\TopicServiceClient;
use Topic\Api\V1\TopicServiceServer;
use Topic\Api\V1\TopicServiceServerRegistry;

/**
 * @api
 */
#[CoversClass(Server::class)]
#[CoversClass(Client::class)]
#[CoversClass(ServerStreamHandler::class)]
final class ServerStreamTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new TopicServiceServerRegistry(new TopicServer()))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testEventReceived(): void
    {
        $client = new TopicServiceClient(new Client\Builder()->build());

        $stream = $client->subscribe(new SubscribeRequest('payments'));

        $paymentEvents = [];
        foreach ($stream as $event) {
            $paymentEvents[] = $event;
        }

        self::assertCount(2, $paymentEvents);
        self::assertEquals(
            [
                new Event('payment_finished', '{"id": 1}', new Timestamp(new Number(1_771_782_096))),
                new Event('payment_rejected', '{"id": 2}', new Timestamp(new Number(1_771_782_097))),
            ],
            $paymentEvents,
        );

        $stream = $client->subscribe(new SubscribeRequest('subscriptions'));

        $subscriptionEvents = [];
        foreach ($stream as $event) {
            $subscriptionEvents[] = $event;
        }

        self::assertCount(1, $subscriptionEvents);
        self::assertEquals(
            [
                new Event('subscription_terminated', '{"id": 1}', new Timestamp(new Number(1_771_782_099))),
            ],
            $subscriptionEvents,
        );

        $this->expectExceptionObject(new InvokeError(Code::FAILED_PRECONDITION, 'Unknown topic "users"'));
        $stream = $client->subscribe(new SubscribeRequest('users'));
        iterator_to_array($stream);
    }
}

final readonly class TopicServer implements TopicServiceServer
{
    #[\Override]
    public function subscribe(
        SubscribeRequest $request,
        Server\ServerStreamChannel $stream,
        Metadata $md,
        Cancellation $cancellation,
    ): void {
        $events = [
            'payments' => [
                new Event('payment_finished', '{"id": 1}', new Timestamp(new Number(1_771_782_096))),
                new Event('payment_rejected', '{"id": 2}', new Timestamp(new Number(1_771_782_097))),
            ],
            'subscriptions' => [
                new Event('subscription_terminated', '{"id": 1}', new Timestamp(new Number(1_771_782_099))),
            ],
        ];

        foreach ($events[$request->topic] ?? throw new InvokeError(Code::FAILED_PRECONDITION, 'Unknown topic "' . $request->topic . '"') as $it) {
            $stream->send($it);
        }

        $stream->close();
    }
}
