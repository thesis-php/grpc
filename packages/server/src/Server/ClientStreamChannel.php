<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 * @template TRequest of object
 * @template TResponse of object
 * @template-implements \IteratorAggregate<array-key, TRequest>
 */
final readonly class ClientStreamChannel implements \IteratorAggregate
{
    /** Can be modified before the first message is sent. */
    public Metadata $responseHeaders;

    public Metadata $trailers;

    /**
     * @param ServerStream<TRequest, TResponse> $stream
     */
    public function __construct(
        private ServerStream $stream,
    ) {
        $this->responseHeaders = $stream->responseHeaders;
        $this->trailers = $stream->trailers;
    }

    /**
     * @return TRequest
     */
    public function receive(): object
    {
        return $this->stream->receive();
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->stream->getIterator();
    }
}
