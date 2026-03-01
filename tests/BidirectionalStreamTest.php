<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Chat\Api\V1\Message;
use Chat\Api\V1\MessengerServiceClient;
use Chat\Api\V1\MessengerServiceServer;
use Chat\Api\V1\MessengerServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Server\BidirectionalStreamHandler;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(BidirectionalStreamHandler::class)]
final class BidirectionalStreamTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new MessengerServiceServerRegistry(new BidirectionalStreamServer()))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testBidirectionalStream(): void
    {
        $client = new MessengerServiceClient(new Client\Builder()->build());

        $chat = $client->chat();

        $chat->send(new Message('Hi from gRPC client'));
        self::assertSame('Hi from gRPC server', $chat->receive()->text);

        $chat->send(new Message('How are you?'));
        self::assertSame("I'm fine.", $chat->receive()->text);
        self::assertSame('How are you too?', $chat->receive()->text);

        $chat->send(new Message("And I'm fine too."));
        $chat->send(new Message('Bye'));
        $chat->close();

        self::assertSame('See you.', $chat->receive()->text);
    }
}

final readonly class BidirectionalStreamServer implements MessengerServiceServer
{
    #[\Override]
    public function chat(
        Server\BidirectionalStreamChannel $stream,
        Metadata $md,
        Cancellation $cancellation,
    ): void {
        foreach ($stream as $message) {
            $replies = match ($message->text) {
                'Hi from gRPC client' => ['Hi from gRPC server'],
                'How are you?' => ["I'm fine.", 'How are you too?'],
                "And I'm fine too." => [],
                'Bye' => ['See you.'],
                default => ["I didn't understand you"],
            };

            foreach ($replies as $reply) {
                $stream->send(new Message($reply));
            }
        }

        $stream->close();
    }
}
