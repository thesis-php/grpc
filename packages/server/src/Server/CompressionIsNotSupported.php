<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\UnimplementedException;

/**
 * @api
 */
final class CompressionIsNotSupported extends UnimplementedException
{
    /**
     * @param non-empty-string $compression
     */
    public function __construct(
        public string $compression,
    ) {
        parent::__construct("Decompression is not supported by server: {$compression}");
    }
}
