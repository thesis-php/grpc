<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Amp\NullCancellation;
use Google\Rpc\Code;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\PickContext;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\GrpcException;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;

/**
 * @internal
 */
final readonly class AmphpHttpClient implements Client
{
    public function __construct(
        private Connection $connection,
        private UnaryInterceptorComposer $unary,
        private StreamInterceptorComposer $stream,
    ) {}

    #[\Override]
    public function invoke(
        object $request,
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): object {
        $pick = new PickContext($invoke->method, $md);

        return $this->unary->intercept( // @phpstan-ignore return.type
            $request,
            $invoke,
            $md,
            $cancellation,
            function (
                object $request,
                Client\Invoke $invoke,
                Metadata $md,
                Cancellation $cancellation,
            ) use ($pick): object {
                try {
                    $stream = $this->connection->createStream($invoke, $md, $cancellation, $pick);
                    $stream->send($request);
                    $stream->close();

                    return $stream->receive();
                } catch (GrpcException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    // Transport-level failures (e.g. a refused connection) map to UNAVAILABLE,
                    // so interceptors above see a gRPC status rather than a raw amphp exception.
                    throw new InvokeError(Code::UNAVAILABLE, $e->getMessage(), previous: $e);
                }
            },
        );
    }

    #[\Override]
    public function createStream(
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        $pick = new PickContext($invoke->method, $md);

        return $this->stream->intercept( // @phpstan-ignore return.type
            $invoke,
            $md,
            $cancellation,
            fn(
                Client\Invoke $invoke,
                Metadata $md,
                Cancellation $cancellation,
            ): ClientStream => $this->connection->createStream($invoke, $md, $cancellation, $pick),
        );
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->connection->close($cancellation);
    }
}
