<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stream;

use Thesis\Grpc\Stream;

/**
 * @api
 * @template T of object
 * @template-implements Stream<T>
 */
final readonly class UnaryStream implements Stream
{
    /**
     * @param T $message
     */
    public function __construct(
        private object $message,
    ) {}

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield $this->message;
    }
}
