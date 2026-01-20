<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

/**
 * @internal
 */
final readonly class UriFactory
{
    /**
     * @param non-empty-string $host
     */
    public function __construct(
        private string $host,
    ) {}

    /**
     * @param non-empty-string $method
     * @return non-empty-string
     */
    public function create(string $method): string
    {
        $clear = static fn(string $path) => trim($path, '/');

        return \sprintf('%s/%s', $clear($this->host), $clear($method));
    }
}
