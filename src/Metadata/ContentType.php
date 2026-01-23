<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class ContentType implements MetadataKey
{
    /**
     * @param non-empty-string $value
     */
    public function __construct(
        private string $value,
    ) {}

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        return $md->with('content-type', $this->value);
    }
}
