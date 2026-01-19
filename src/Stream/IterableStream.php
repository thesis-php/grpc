<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stream;

use Thesis\Grpc\Stream;

/**
 * @api
 * @template T
 * @template-implements Stream<T>
 */
final readonly class IterableStream implements Stream
{
    /**
     * @param iterable<array-key, T> $messages
     */
    public function __construct(
        private iterable $messages,
    ) {}

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->messages;
    }
}
