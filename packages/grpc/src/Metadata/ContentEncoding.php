<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class ContentEncoding implements MetadataKey
{
    public const string HEADER = 'grpc-encoding';
    public const string GRPC_DEFAULT_COMPRESSION = Compressor::DEFAULT_COMPRESSION;

    /**
     * @param ?non-empty-string $encoding
     */
    public function __construct(
        public ?string $encoding = null,
    ) {}

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        if ($this->encoding !== null) {
            $md = $md->replace(self::HEADER, $this->encoding);
        }

        return $md;
    }
}

/**
 * @internal
 */
function parseContentEncoding(Metadata $md): ?ContentEncoding
{
    $contentEncoding = $md->value(ContentEncoding::HEADER);
    if ($contentEncoding === null) {
        return null;
    }

    return new ContentEncoding($contentEncoding);
}
