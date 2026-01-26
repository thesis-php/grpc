<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Thesis\Grpc\ClientStream;

/**
 * @api
 * @template TRequest of object
 * @template TResponse of object
 * @template-implements \IteratorAggregate<array-key, TResponse>
 */
final readonly class BidirectionalStreamChannel implements \IteratorAggregate
{
    /**
     * @param ClientStream<TRequest, TResponse> $stream
     */
    public function __construct(
        private ClientStream $stream,
    ) {}

    /**
     * @no-named-arguments
     * @param TRequest ...$requests
     */
    public function send(object ...$requests): void
    {
        foreach ($requests as $request) {
            $this->stream->send($request);
        }
    }

    /**
     * @return TResponse
     */
    public function receive(): object
    {
        return $this->stream->receive();
    }

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
