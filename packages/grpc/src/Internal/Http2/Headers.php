<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

use Thesis\Google\Rpc\Code;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;

/**
 * @internal
 * @return array<non-empty-string, list<string>>
 */
function encodeMetadata(Metadata $md): array
{
    $headers = $md->kv;

    foreach ($headers as $key => $values) {
        if (str_ends_with($key, '-bin')) {
            $headers[$key] = array_map(
                static fn(string $value): string => rtrim(base64_encode($value), '='),
                $values,
            );
        }
    }

    return $headers;
}

/**
 * @internal
 * @param array<non-empty-string, list<string>> $headers
 * @throws InvokeError
 */
function decodeMetadata(array $headers): Metadata
{
    foreach ($headers as $key => $values) {
        if (str_ends_with($key, '-bin')) {
            $headers[$key] = array_map(
                static fn(string $value): string => ($decoded = base64_decode($value, true)) !== false
                    ? $decoded
                    : throw new InvokeError(Code::INTERNAL, "Malformed binary metadata in header \"{$key}\""),
                explode(',', implode(',', $values)),
            );
        }
    }

    return new Metadata($headers);
}
