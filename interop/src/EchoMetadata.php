<?php

declare(strict_types=1);

namespace Thesis\Grpc\Interop;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\Server\UnaryInterceptor;

final readonly class EchoMetadata implements UnaryInterceptor
{
    private const string INITIAL = 'x-grpc-test-echo-initial';
    private const string TRAILING = 'x-grpc-test-echo-trailing-bin';

    public static function echo(Metadata $md, Metadata $headers, Metadata $trailers): void
    {
        $headers->join(new Metadata(array_intersect_key($md->kv, [self::INITIAL => true])));
        $trailers->join(new Metadata(array_intersect_key($md->kv, [self::TRAILING => true])));
    }

    #[\Override]
    public function interceptUnary(
        object $request,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $handler,
    ): object {
        self::echo($md, $info->responseHeaders, $info->trailers);

        return $handler($request, $info, $md, $cancellation);
    }
}
