<?php

declare(strict_types=1);

namespace Thesis\Grpc;

/**
 * @api
 * @template In of object
 * @template Out of object
 */
final readonly class Request
{
    /**
     * @param non-empty-string $method
     * @param class-string<Out> $replyType
     */
    public function __construct(
        public string $method,
        public string $replyType,
        public Metadata $md = new Metadata(),
    ) {}
}
