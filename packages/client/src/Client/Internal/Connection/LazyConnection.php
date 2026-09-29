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

        if ($future === null) {
            return;
        }

        try {
            $connection = $future->await($cancellation);
        } catch (\Throwable $e) {
            if ($future->isComplete()) {
                // The connection was never established: there is nothing to close.
                return;
            }

            throw $e;
        }

        $connection->close($cancellation);
    }

    private function createConnection(Cancellation $cancellation): Connection
    {
        $future = $this->future ??= async($this->factory);

        try {
            return $future->await($cancellation);
        } catch (\Throwable $e) {
            // Forget a failed attempt so the next call retries it. A caller that merely
            // stopped waiting (cancellation) leaves the attempt running for the others.
            if ($future->isComplete() && $this->future === $future) {
                $this->future = null;
            }

            throw $e;
        }
    }
}
