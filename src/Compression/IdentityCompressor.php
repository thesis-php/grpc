<?php

declare(strict_types=1);

namespace Thesis\Grpc\Compression;

/**
 * @api
 */
enum IdentityCompressor implements Compressor
{
    case Compressor;

    #[\Override]
    public function name(): string
    {
        return 'identity';
    }

    #[\Override]
    public function compress(string $buffer): string
    {
        return $buffer;
    }

    #[\Override]
    public function decompress(string $buffer): string
    {
        return $buffer;
    }
}
