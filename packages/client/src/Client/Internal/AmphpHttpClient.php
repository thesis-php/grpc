<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Client;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @internal
 */
final readonly class AmphpHttpClient implements Client
{
    public function __construct(
        private Connection $connection,
    ) {}

    #[\Override]
    public function invoke(
        object $request,
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): object {
        $stream = $this->connection->createStream(
            $invoke,
            $md,
            $cancellation,
        );

        $stream->send($request);
        $stream->close();

        return $stream->receive();
    }

    #[\Override]
    public function createStream(
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        return $this->connection->createStream(
            $invoke,
            $md,
            $cancellation,
        );
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->connection->close($cancellation);
    }
}
