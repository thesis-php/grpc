<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Connection;

use Amp\Cancellation;
use Amp\Future;
use Amp\NullCancellation;
use Thesis\Grpc\Client\Internal\Connection;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;
use function Amp\async;

/**
 * @internal
 */
final class LazyConnection implements Connection
{
    /** @var ?Future<Connection> */
    private ?Future $future = null;

    /**
     * @param \Closure(): Connection $factory
     */
    public function __construct(
        private readonly \Closure $factory,
    ) {}

    #[\Override]
    public function createStream(
        Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        $this->future ??= async($this->factory);
        $connection = $this->future->await($cancellation);

        return $connection->createStream($invoke, $md, $cancellation);
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $future = $this->future;
        $this->future = null;

        $future?->await($cancellation)->close($cancellation);
    }
}
