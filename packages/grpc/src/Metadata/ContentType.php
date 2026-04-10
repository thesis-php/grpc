<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class ContentType implements MetadataKey
{
    public const string HEADER = 'Content-Type';
    public const string GRPC_CONTENT_TYPE = 'application/grpc';
    public const string GRPC_DEFAULT_ENCODING = Encoder::DEFAULT_ENCODING;

    /**
     * @param ?non-empty-string $encoding
     */
    public function __construct(
        public ?string $encoding = null,
    ) {}

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        return $md->replace(self::HEADER, self::GRPC_CONTENT_TYPE . ($this->encoding !== null ? "+{$this->encoding}" : ''));
    }
}

/**
 * @internal
 */
function parseContentType(Metadata $md): ?ContentType
{
    $contentType = $md->value(ContentType::HEADER);

    if ($contentType === null || !str_starts_with($contentType, ContentType::GRPC_CONTENT_TYPE)) {
        return null;
    }

    $suffix = substr($contentType, \strlen(ContentType::GRPC_CONTENT_TYPE));
    $encoding = match ($suffix[0] ?? ';') {
        ';' => ContentType::GRPC_DEFAULT_ENCODING,
        '+' => substr($suffix, 1),
        // We prohibit any unknown characters except ";" and "+".
        default => '',
    };

    if ($encoding === '') {
        return null;
    }

    return new ContentType($encoding);
}
