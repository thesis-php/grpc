<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Connection;

use Amp\Cancellation;
use Amp\Future;
use Amp\NullCancellation;
use Thesis\Grpc\Client\Internal\Connection;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\PickContext;
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
        Metadata $md,
        Cancellation $cancellation,
        PickContext $pick,
    ): ClientStream {
        return $this
            ->createConnection($cancellation)
            ->createStream($invoke, $md, $cancellation, $pick);
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $future = $this->future;
        $this->future = null;

        $future?->await($cancellation)->close($cancellation);
    }

    private function createConnection(Cancellation $cancellation): Connection
    {
        return ($this->future ??= async($this->factory))->await($cancellation);
    }
}
