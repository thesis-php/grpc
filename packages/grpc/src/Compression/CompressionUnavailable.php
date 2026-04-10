<?php

declare(strict_types=1);

namespace Thesis\Grpc\Compression;

use Thesis\Grpc\GrpcException;

/**
 * @api
 */
final class CompressionUnavailable extends GrpcException
{
    /**
     * @param non-empty-string $algorithm
     */
    public function __construct(
        public readonly string $algorithm,
    ) {
        parent::__construct("Compression algorithm '{$algorithm}' is unavailable.");
    }
}
