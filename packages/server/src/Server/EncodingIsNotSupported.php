<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\UnimplementedException;

/**
 * @api
 */
final class EncodingIsNotSupported extends UnimplementedException
{
    /**
     * @param non-empty-string $encoding
     */
    public function __construct(
        public string $encoding,
    ) {
        parent::__construct("Encoding is not supported by server: {$encoding}");
    }
}
