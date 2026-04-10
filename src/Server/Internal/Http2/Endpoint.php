<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Thesis\Grpc\Server\InvalidRpcMethod;

/**
 * @internal
 */
final readonly class Endpoint
{
    /**
     * @param non-empty-string $path
     * @throws InvalidRpcMethod
     */
    public static function parse(string $path): self
    {
        $path = trim($path, '/');
        $idx = strpos($path, '/');
        if ($idx === false || $idx === 0 || $idx === \strlen($path) - 1) {
            throw new InvalidRpcMethod("Malformed method name: /{$path}");
        }

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
