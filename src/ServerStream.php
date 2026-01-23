<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Thesis\Grpc\Exception\ServerStreamIsClosed;

/**
 * @api
 * @template-covariant In
 * @template Out
 * @template-extends \IteratorAggregate<array-key, In>
 */
interface ServerStream extends \IteratorAggregate
{
    /**
     * This property can be modified before the ({@see send}) method has been called for the first time.
     */
    public Metadata $headers { get; }

    /**
     * This property can be modified before the stream is closed ({@see close}).
     */
    public Metadata $trailers { get; }

    /**
     * @param Out $message
     * @throws ServerStreamIsClosed
     */
    public function send(mixed $message): void;

    /**
     * @return In
     * @throws ServerStreamIsClosed
     */
    public function receive(): mixed;

    /**
     * @throws ServerStreamIsClosed
     */
    public function close(): void;
}
