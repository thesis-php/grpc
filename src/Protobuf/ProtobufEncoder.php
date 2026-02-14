<?php

declare(strict_types=1);

namespace Thesis\Grpc\Protobuf;

use Thesis\Grpc\Encoding;
use Thesis\Protobuf;
use Thesis\Protobuf\Reflection;

/**
 * @api
 */
final readonly class ProtobufEncoder implements Encoding\Encoder
{
    public function __construct(
        private Protobuf\Serializer $serializer,
        private Reflection\Reflector $reflector,
    ) {}

    public static function default(): self
    {
        return new self(
            new Protobuf\Serializer(),
            Reflection\Reflector::build(),
        );
    }

    #[\Override]
    public function name(): string
    {
        return 'proto';
    }

    #[\Override]
    public function encode(object $request): string
    {
        return $this->serializer->serialize(
            $this->reflector->message($request),
        );
    }

    #[\Override]
    public function decode(string $buffer, string $classType): object
    {
        try {
            return $this->reflector->map(
                $this->serializer->deserialize(
                    $this->reflector->type($classType),
                    $buffer,
                ),
                $classType,
            );
        } catch (\Throwable $e) {
            throw new Encoding\DecodingFailed($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
