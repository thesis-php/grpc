<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @api
 * @template In of object
 * @template-covariant Out of object
 * @template-implements ClientStream<In, Out>
 */
abstract readonly class DecoratedStream implements ClientStream
{
    /**
     * @param ClientStream<In, Out> $stream
     */
    public function __construct(
        private ClientStream $stream,
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
    public function headers(): Metadata
    {
        return $this->stream->headers();
    }

    #[\Override]
    public function trailers(Cancellation $cancellation = new NullCancellation()): Metadata
    {
        return $this->stream->trailers($cancellation);
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
