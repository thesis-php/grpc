<?php

declare(strict_types=1);

namespace Thesis\Grpc\Compression;

/**
 * @api
 */
interface Compressor
{
    /**
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * @param non-empty-string $buffer
     * @return non-empty-string
     * @throws CompressionFailed
     */
    public function compress(string $buffer): string;

    /**
     * @param non-empty-string $buffer
     * @return non-empty-string
     * @throws DecompressionFailed
     */
    public function decompress(string $buffer): string;
}
