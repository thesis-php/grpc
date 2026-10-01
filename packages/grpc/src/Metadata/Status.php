<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Google\Rpc;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class Status implements MetadataKey
{
    public const string STATUS_HEADER = 'grpc-status';
    public const string MESSAGE_HEADER = 'grpc-message';
    public const string DETAILS_HEADER = 'grpc-status-details-bin';

    public function __construct(
        public Rpc\Code $code,
        public ?string $message = null,
        public ?string $details = null,
    ) {}

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        $md = $md
            ->replace(self::STATUS_HEADER, (string) $this->code->value)
            ->replace(self::MESSAGE_HEADER, (string) preg_replace_callback(
                '/[^\x20-\x24\x26-\x7e]/',
                static fn(array $byte): string => \sprintf('%%%02X', \ord($byte[0])),
                $this->message ?? '',
            ));

        if ($this->details !== null && $this->details !== '') {
            $md = $md->replace(self::DETAILS_HEADER, $this->details);
        }

        return $md;
    }
}

/**
 * @internal
 */
function parseStatus(Metadata $md): Status
{
    $code = Rpc\Code::tryFrom((int) ($md->value(Status::STATUS_HEADER) ?? Rpc\Code::UNKNOWN->value)) ?? Rpc\Code::UNKNOWN;

    return new Status(
        $code,
        ($message = $md->value(Status::MESSAGE_HEADER)) !== null ? rawurldecode($message) : null,
        $md->value(Status::DETAILS_HEADER),
    );
}
