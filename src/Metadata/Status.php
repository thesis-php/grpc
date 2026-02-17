<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Google\Rpc\Code;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class Status implements MetadataKey
{
    public function __construct(
        public Code $code,
        public ?string $message = null,
    ) {}

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        return $md
            ->with('grpc-status', (string) $this->code->value)
            ->with('grpc-message', $this->message ?? ($this->code !== Code::UNKNOWN ? $this->code->name : ''));
    }
}
