<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Metadata;
use Thesis\Package;

/**
 * @api
 */
enum UserAgent implements MetadataKey
{
    public const string HEADER = 'User-Agent';
    case Key;

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        return $md->replace(self::HEADER, 'grpc-php-thesis/' . Package\version('thesis/grpc-client'));
    }
}
