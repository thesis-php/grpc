<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use Google\Rpc\BadRequest;
use Google\Rpc\Code;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;
use Thesis\Grpc\Server\UnaryHandler;
use Thesis\Grpc\Stub\AuthorizationClientInterceptor;
use Thesis\Grpc\Stub\AuthorizationServerInterceptor;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
#[CoversClass(UnaryHandler::class)]
final class UnaryTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new UnaryEchoServer()))
            ->withInterceptors(new AuthorizationServerInterceptor('secret'))
            ->build();

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testAuthorizationInterceptor(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
            ->withInterceptors(new AuthorizationClientInterceptor('secret'))
            ->build(),
        );

        $response = $client->echo(new EchoRequest('ping'));
        self::assertSame('ping', $response->sentence);
    }

    public function testAuthorizationError(): void
    {
        $client = new EchoServiceClient(new Client\Builder()->build());

        $this->expectExceptionObject(new InvokeError(
            Code::UNAUTHENTICATED,
            'Use authorization, Luke!',
        ));
        $client->echo(new EchoRequest());
    }

    public function testPassMetadata(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
            ->withInterceptors(new AuthorizationClientInterceptor('secret'))
            ->build(),
        );

        $response = $client->echo(new EchoRequest(), new Metadata()->with('server-sentence', $sentence = 'pong'));
        self::assertSame($sentence, $response->sentence);
    }

    public function testWrapClientStream(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
            ->withInterceptors(
                new AuthorizationClientInterceptor('secret'),
                new Client\CallableInterceptor(static fn(Invoke $invoke, Metadata $metadata, Cancellation $cancellation, callable $next): ClientStream => new MitmClientStream($next(
                    $invoke,
                    $metadata,
                    $cancellation,
                ))),
            )
            ->build(),
        );

        $response = $client->echo(new EchoRequest('ping'));
        self::assertSame('pong', $response->sentence);
    }

    public function testServerHandlerException(): void
    {
        $client = new EchoServiceClient(
            new Client\Builder()
            ->withInterceptors(new AuthorizationClientInterceptor('secret'))
            ->build(),
        );

        try {
            $client->echo(new EchoRequest(), new Metadata()->with('server-exception', '1'));
        } catch (InvokeError $e) {
            // We use this ugly hack because {@see expectExceptionObject()} only checks the code and message, but we need to verify other fields of the exception.
            self::assertSame(Code::INVALID_ARGUMENT, $e->statusCode);
            self::assertSame('Request is invalid', $e->statusMessage);
            self::assertEquals([new BadRequest([new BadRequest\FieldViolation('sentence', 'invalid sentence')])], $e->details);

            return;
        }

        self::fail('Client::echo() above should be throw an exception');
    }
}

final readonly class UnaryEchoServer implements EchoServiceServer
{
    #[\Override]
    public function echo(
        EchoRequest $request,
        Metadata $md,
        Cancellation $cancellation,
    ): EchoResponse {
        $throwException = $md->value('server-exception') === '1';
        if ($throwException) {
            throw new InvokeError(Code::INVALID_ARGUMENT, 'Request is invalid', [
                new BadRequest([
                    new BadRequest\FieldViolation('sentence', 'invalid sentence'),
                ]),
            ]);
        }

        $sentence = $md->value('server-sentence') ?? $request->sentence;

        return new EchoResponse($sentence);
    }
}

/**
 * @template-extends Client\DecoratedStream<EchoRequest, EchoResponse>
 */
final readonly class MitmClientStream extends Client\DecoratedStream
{
    public function __construct(ClientStream $stream)
    {
        parent::__construct($stream);
    }

    #[\Override]
    public function send(object $message): void
    {
        parent::send(new EchoRequest('pong'));
    }
}
