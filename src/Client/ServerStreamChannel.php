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
final readonly class ServerStreamChannel implements \IteratorAggregate
{
    /**
     * @param ClientStream<TRequest, TResponse> $stream
     */
    public function __construct(
        private ClientStream $stream,
    ) {}

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->stream->getIterator();
    }
}
