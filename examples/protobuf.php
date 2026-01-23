<?php

declare(strict_types=1);

use Thesis\Grpc\Encoding;
use Thesis\Protobuf;
use Thesis\Protobuf\Reflection;

final readonly class Stub {}

/**
 * @api
 */
final readonly class ProtobufEncoder implements Encoding\Encoder
{
    public function __construct(
        private Protobuf\Serializer $serializer,
        private Reflection\Reflector $reflector,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'proto';
    }

    #[Override]
    public function encode(object $request): string
    {
        return $this->serializer->serialize(
            $this->reflector->message($request),
        );
    }

    #[Override]
    public function decode(string $buffer, string $classType): object
    {
        return $this->reflector->map(
            $this->serializer->deserialize(
                $this->reflector->type($classType),
                $buffer,
            ),
            $classType,
        );
    }
}
