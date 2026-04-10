<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Metadata;

/**
 * @api
 */
interface MetadataKey
{
    public function append(Metadata $md): Metadata;
}
