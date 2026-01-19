<?php

declare(strict_types=1);

namespace Thesis\Grpc\Compression;

/**
 * @api
 * @phpstan-type CompressionLevel = -1|int<0, 9>
 */
final readonly class GzipCompressor implements Compressor
{
    /**
     * @param CompressionLevel $level
     * @throws CompressionUnavailable
     */
    public function __construct(
        private int $level = -1,
    ) {
        if (!\extension_loaded('zlib')) {
            throw new CompressionUnavailable('gzip');
        }
    }

    #[\Override]
    public function name(): string
    {
        return 'gzip';
    }

    #[\Override]
    public function compress(string $buffer): string
    {
        $compressed = gzencode($buffer, $this->level);

        if ($compressed === false || $compressed === '') {
            throw new CompressionFailed();
        }

        return $compressed;
    }

    #[\Override]
    public function decompress(string $buffer): string
    {
        $uncompressed = gzdecode($buffer);

        if ($uncompressed === false || $uncompressed === '') {
            throw new DecompressionFailed();
        }

        return $uncompressed;
    }
}
