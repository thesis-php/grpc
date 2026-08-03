<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;

/**
 * @api
 * @template TRequest of object
 * @template TResponse of object
 */
final readonly class UnaryHandler
{
    /**
     * @param \Closure(TRequest, Metadata, Cancellation): TResponse $handler
     */
    public function __construct(
        private \Closure $handler,
    ) {}

    /**
     * @param TRequest $request
     * @return TResponse
     */
    public function invoke(object $request, Metadata $md, Cancellation $cancellation): object
    {
        return ($this->handler)($request, $md, $cancellation);
    }
}
