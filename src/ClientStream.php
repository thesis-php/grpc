<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Exception\ClientStreamIsClosed;

/**
 * @api
 * @template In of object
 * @template-covariant Out of object
 * @template-extends \IteratorAggregate<array-key, Out>
 */
interface ClientStream extends \IteratorAggregate
{
    /**
     * @param In $message
     * @throws ClientStreamIsClosed
     */
    public function send(object $message): void;

    /**
     * @return Out
     * @throws InvokeError
     */
    public function receive(): object;

    /**
     * This method should be called after the ({@see send}) method has been called for the first time.
     * Otherwise, it will "block" the calling code.
     */
    public function headers(): Metadata;

    /**
     * This method should be called after the stream is closed ({@see close}) on either the client
     * or server side (when {@see receive} throws an exception).
     * Otherwise, it will "block" the calling code.
     */
    public function trailers(Cancellation $cancellation = new NullCancellation()): Metadata;

    /**
     * @throws ClientStreamIsClosed
     */
    public function close(): void;
}
