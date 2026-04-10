<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 * @template-covariant In of object
 * @template Out of object
 * @template-implements ServerStream<In, Out>
 */
abstract class DecoratedStream implements ServerStream
{
    public Metadata $trailers { get => $this->stream->trailers; }

    public Metadata $headers { get => $this->stream->headers; }

    /**
     * @param ServerStream<In, Out> $stream
     */
    public function __construct(
        private readonly ServerStream $stream,
    ) {}

    #[\Override]
    public function send(object $message): void
    {
        $this->stream->send($message);
    }

    #[\Override]
    public function receive(): object
    {
        return $this->stream->receive();
    }

    #[\Override]
    public function close(): void
    {
        $this->stream->close();
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->stream->getIterator();
    }
}
