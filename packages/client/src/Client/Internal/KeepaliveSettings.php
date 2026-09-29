<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

/**
 * @internal
 */
final readonly class KeepaliveSettings
{
    /**
     * @param positive-int $idle seconds of idleness before the first probe
     * @param positive-int $interval seconds between unanswered probes
     * @param positive-int $count unanswered probes before the connection is dropped
     */
    public function __construct(
        public int $idle,
        public int $interval,
        public int $count,
    ) {}
}
