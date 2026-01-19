<?php

declare(strict_types=1);

namespace Thesis\Grpc;

/**
 * @api
 * @template T
 * @template-extends Stream<T>
 */
interface SinkStream extends Stream
{
    /**
     * @param T $message
     */
    public function push(mixed $message): void;

    public function complete(): void;
}
