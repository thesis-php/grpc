<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\ServerStream;

/**
 * @api
 * @template TRequest of object
 * @template TResponse of object
 * @template-implements \IteratorAggregate<array-key, TRequest>
 */
final readonly class ClientStreamChannel implements \IteratorAggregate
{
    /**
     * @param ServerStream<TRequest, TResponse> $stream
     */
    public function __construct(
        private ServerStream $stream,
    ) {}

    /**
     * @param TResponse $response
     */
    public function close(object $response): void
    {
        $this->stream->send($response);
        $this->stream->close();
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->stream->getIterator();
    }
}
