<?php

declare(strict_types=1);

namespace Thesis\Grpc\Encoding;

/**
 * @api
 * @template T of object = object
 */
interface Encoder
{
    final public const string DEFAULT_ENCODING = 'proto';

    /**
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * @param T $request
     * @throws EncodingFailed
     */
    public function encode(object $request): string;

    /**
     * @template E of T
     * @param class-string<E> $classType
     * @return E
     * @throws DecodingFailed
     */
    public function decode(string $buffer, string $classType): object;
}
