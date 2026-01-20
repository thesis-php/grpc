<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Exception\ClientStreamIsClosed;

/**
 * @api
 * @template In
 * @template-covariant Out
 * @template-extends \IteratorAggregate<array-key, Out>
 */
interface ClientStream extends \IteratorAggregate
{
    /**
     * @param In $message
     * @throws ClientStreamIsClosed
     */
    public function send(mixed $message): void;

    /**
     * @return Out
     * @throws ClientStreamIsClosed
     */
    public function receive(): mixed;

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
