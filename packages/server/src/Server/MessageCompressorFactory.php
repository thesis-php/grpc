<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\Compression\Compressor;

/**
 * @api
 */
final readonly class MessageCompressorFactory
{
    /** @var non-empty-array<non-empty-string, Compressor> */
    private array $compressors;

    /**
     * @param non-empty-list<Compressor> $compressors
     */
    public function __construct(array $compressors)
    {
        $map = [];

        foreach ($compressors as $compressor) {
            $map[$compressor->name()] = $compressor;
        }

        $this->compressors = $map;
    }

    /**
     * @param non-empty-string $name
     * @throws CompressionIsNotSupported
     */
    public function compressor(string $name): Compressor
    {
        return $this->compressors[$name] ?? throw new CompressionIsNotSupported($name);
    }
}
