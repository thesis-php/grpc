<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class PickContext
{
    /**
     * @param non-empty-string $methodName
     */
    public function __construct(
        public string $methodName,
        public Metadata $metadata,
    ) {}
}
