<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Thesis\Grpc\Exception\ServerStreamIsClosed;

/**
 * @api
 * @template-covariant In of object
 * @template Out of object
 * @template-extends \IteratorAggregate<array-key, In>
 */
interface ServerStream extends \IteratorAggregate
{
    /**
     * Incoming request metadata.
     */
    public Metadata $headers { get; }

    /**
     * Outgoing response metadata. This property can be modified before the first message is sent ({@see send}) or the stream is closed ({@see close}).
     */
    public Metadata $responseHeaders { get; }

    /**
     * This property can be modified before the stream is closed ({@see close}).
     */
    public Metadata $trailers { get; }

    /**
     * @param Out $message
     * @throws ServerStreamIsClosed
     */
    public function send(object $message): void;

    /**
     * @return In
     * @throws ServerStreamIsClosed
     */
    public function receive(): object;

    public function close(): void;
}
