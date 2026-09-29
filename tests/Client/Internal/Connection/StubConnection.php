<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Connection;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Client\Internal\Connection;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\PickContext;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

final class StubConnection implements Connection
{
    public bool $closed = false;

    /**
     * @template In of object
     * @template Out of object
     * @param Invoke<In, Out> $invoke
     * @return StubStream<In, Out>
     */
    #[\Override]
    public function createStream(Invoke $invoke, Metadata $md, Cancellation $cancellation, PickContext $pick): ClientStream
    {
        /** @var StubStream<In, Out> */
        return new StubStream();
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->closed = true;
    }
}
