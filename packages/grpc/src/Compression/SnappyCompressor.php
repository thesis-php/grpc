<?php

declare(strict_types=1);

namespace Thesis\Grpc\Compression;

/**
 * @api
 */
final readonly class SnappyCompressor implements Compressor
{
    /**
     * @throws CompressionUnavailable
     */
    public function __construct()
    {
        if (!\extension_loaded('snappy')) {
            throw new CompressionUnavailable('snappy');
        }
    }

    #[\Override]
    public function name(): string
    {
        return 'snappy';
    }

    #[\Override]
    public function compress(string $buffer): string
    {
        $compressed = snappy_compress($buffer);

        if ($compressed === false || $compressed === '') {
            throw new CompressionFailed();
        }

        return $compressed;
    }

    #[\Override]
    public function decompress(string $buffer): string
    {
        $uncompressed = snappy_uncompress($buffer);

        if ($uncompressed === false || $uncompressed === '') {
            throw new DecompressionFailed();
        }

        return $uncompressed;
    }
}
