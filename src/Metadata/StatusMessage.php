<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class StatusMessage implements MetadataKey
{
    public function __construct(
        private string $message,
    ) {}

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        return $md->with('grpc-message', $this->message);
    }
}
