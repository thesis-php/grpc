<?php

declare(strict_types=1);

namespace Thesis\Grpc\Interop;

use Amp\Cancellation;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\StreamInterceptor;
use Thesis\Grpc\Client\UnaryInterceptor;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * Attaches `--additional_metadata` to every call.
 */
final readonly class AdditionalMetadata implements
    UnaryInterceptor,
    StreamInterceptor
{
    public function __construct(
        private Metadata $md,
    ) {}

    /**
     * Parses `key1:value1;key2:value2`, where a value may contain colons.
     */
    public static function parse(string $flag): self
    {
        preg_match_all('/([^:;]+):([^;]*)/', $flag, $pairs, \PREG_SET_ORDER);

        $md = new Metadata();

        foreach ($pairs as [, $key, $value]) {
            $md = $md->with($key, $value);
        }

        return new self($md);
    }

    #[\Override]
    public function interceptUnary(
        object $request,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $invoker,
    ): object {
        return $invoker($request, $invoke, $md->merge($this->md), $cancellation);
    }

    #[\Override]
    public function interceptStream(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $newStream,
    ): ClientStream {
        return $newStream($invoke, $md->merge($this->md), $cancellation);
    }
}
