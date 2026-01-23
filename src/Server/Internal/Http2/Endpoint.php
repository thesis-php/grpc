<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

/**
 * @internal
 */
final readonly class Endpoint
{
    /**
     * @param non-empty-string $path
     */
    public static function parse(string $path): self
    {
        $path = trim($path, '/');
        $idx = (int) strpos($path, '/');
        $method = substr($path, $idx + 1);
        $service = substr($path, 0, \strlen($path) - \strlen($method) - 1);

        return new self(
            $service,
            $method,
        );
    }

    public function __construct(
        public string $service,
        public string $method,
    ) {}
}
