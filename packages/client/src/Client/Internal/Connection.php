<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @internal
 */
interface Connection
{
    /**
     * @template In of object
     * @template Out of object
     * @param Invoke<In, Out> $invoke
     * @return ClientStream<In, Out>
     */
    public function createStream(
        Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream;

    public function close(Cancellation $cancellation = new NullCancellation()): void;
}
