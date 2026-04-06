<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

/**
 * @api
 */
final readonly class TargetAddress implements \Stringable
{
    /**
     * @param non-empty-string $host
     * @param int<0, 65535> $port
     */
    public function __construct(
        public string $host,
        public int $port,
    ) {}

    /**
     * @return non-empty-string
     */
    #[\Override]
    public function __toString(): string
    {
        $host = $this->host;

        if ($this->port > 0) {
            $host = "{$host}:{$this->port}";
        }

        return $host;
    }
}
