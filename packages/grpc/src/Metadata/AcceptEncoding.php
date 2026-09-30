<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class AcceptEncoding implements MetadataKey
{
    public const string HEADER = 'grpc-accept-encoding';

    /**
     * @param list<non-empty-string> $encodings
     */
    public function __construct(
        public array $encodings,
    ) {}

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        return $md->replace(self::HEADER, implode(',', $this->encodings));
    }
}
