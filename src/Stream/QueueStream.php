<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stream;

use Amp\Pipeline;
use Thesis\Grpc\SinkStream;

/**
 * @api
 * @template T
 * @template-implements SinkStream<T>
 */
final readonly class QueueStream implements SinkStream
{
    /** @var Pipeline\Queue<T> */
    private Pipeline\Queue $queue;

    /**
     * @param non-negative-int $bufferSize
     */
    public function __construct(int $bufferSize = 0)
    {
        $this->queue = new Pipeline\Queue($bufferSize);
    }

    #[\Override]
    public function push(mixed $message): void
    {
        $this->queue->push($message);
    }

    #[\Override]
    public function complete(): void
    {
        if (!$this->queue->isComplete()) {
            $this->queue->complete();
        }
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->queue->iterate();
    }

    public function __destruct()
    {
        $this->complete();
    }
}
