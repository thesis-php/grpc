<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\Encoding\Encoder;

/**
 * @api
 */
final readonly class MessageEncoderFactory
{
    /** @var non-empty-array<non-empty-string, Encoder> */
    private array $encoders;

    /**
     * @param non-empty-list<Encoder> $encoders
     */
    public function __construct(array $encoders)
    {
        $map = [];

        foreach ($encoders as $encoder) {
            $map[$encoder->name()] = $encoder;
        }

        $this->encoders = $map;
    }

    /**
     * @param non-empty-string $name
     * @throws EncodingIsNotSupported
     */
    public function encoder(string $name): Encoder
    {
        return $this->encoders[$name] ?? throw new EncodingIsNotSupported($name);
    }
}
