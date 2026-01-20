<?php

declare(strict_types=1);

namespace Thesis\Grpc\Encoding;

/**
 * @api
 */
interface Encoder
{
    /**
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * @template T of object
     * @param T $request
     */
    public function encode(object $request): string;

    /**
     * @template T of object
     * @param class-string<T> $classType
     * @return T
     */
    public function decode(string $buffer, string $classType): object;
}
