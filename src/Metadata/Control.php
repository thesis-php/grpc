<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Metadata;
use Thesis\Package;

/**
 * @api
 */
final readonly class Control implements MetadataKey
{
    /**
     * @param non-empty-string $encoding
     * @param non-empty-string $compression
     */
    public function __construct(
        private string $encoding,
        private string $compression,
    ) {}

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        return $md
            ->with('Content-Type', "application/grpc+{$this->encoding}")
            ->with('User-Agent', 'grpc-php-thesis/' . Package\version('thesis/grpc'))
            ->with('grpc-encoding', $this->compression)
            ->with('TE', 'trailers')
            ;
    }
}
