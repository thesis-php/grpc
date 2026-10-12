<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Amp\CompositeCancellation;
use Amp\NullCancellation;
use Amp\TimeoutCancellation;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\PickContext;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\GrpcException;
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
                $cancellation = self::deadline($md, $cancellation);

                try {
                    $stream = $this->connection->createStream($invoke, $md, $cancellation, $pick);
                    $stream->send($request);
                    $stream->close();

                    return $stream->receive();
                } catch (GrpcException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    throw TransportError::from($e, $cancellation);
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
            function (
                Client\Invoke $invoke,
                Metadata $md,
                Cancellation $cancellation,
            ) use ($pick): ClientStream {
                $cancellation = self::deadline($md, $cancellation);

                try {
                    return $this->connection->createStream($invoke, $md, $cancellation, $pick);
                } catch (\Throwable $e) {
                    throw TransportError::from($e, $cancellation);
                }
            },
        );
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->connection->close($cancellation);
    }

    /**
     * Like grpc-go, the client enforces the deadline it sends in "grpc-timeout" itself,
     * so an expired call ends with DEADLINE_EXCEEDED rather than whatever the server does on its side.
     */
    private static function deadline(Metadata $md, Cancellation $cancellation): Cancellation
    {
        $timeout = Metadata\parseTimeout($md);

        return $timeout !== null ? new CompositeCancellation($cancellation, new TimeoutCancellation($timeout->toSeconds())) : $cancellation;
    }
}
