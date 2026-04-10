<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Exception\ClientStreamIsClosed;

/**
 * @api
 */
interface Client
{
    /**
     * @template In of object
     * @template Out of object
     * @param In $request
     * @param Client\Invoke<In, Out> $invoke
     * @return Out
     * @throws InvokeError
     * @throws ClientStreamIsClosed
     */
    public function invoke(
        object $request,
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): object;

    /**
     * @template In of object
     * @template Out of object
     * @param Client\Invoke<In, Out> $invoke
     * @return ClientStream<In, Out>
     */
    public function createStream(
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream;

    public function close(Cancellation $cancellation = new NullCancellation()): void;
}
