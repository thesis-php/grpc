<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Connection;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * A stream that never carries messages: the connection tests only need one to exist.
 *
 * @template In of object
 * @template-covariant Out of object
 * @implements ClientStream<In, Out>
 */
final readonly class StubStream implements ClientStream
{
    #[\Override]
    public function send(object $message): void {}

    #[\Override]
    public function receive(): object
    {
        throw new \LogicException('The stub stream carries no messages.');
    }

    #[\Override]
    public function headers(): Metadata
    {
        return new Metadata();
    }

    #[\Override]
    public function trailers(Cancellation $cancellation = new NullCancellation()): Metadata
    {
        return new Metadata();
    }

    #[\Override]
    public function close(): void {}

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from [];
    }
}
