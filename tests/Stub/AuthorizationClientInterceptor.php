<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stub;

use Amp\Cancellation;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\StreamInterceptor;
use Thesis\Grpc\Client\UnaryInterceptor;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

final readonly class AuthorizationClientInterceptor implements
    UnaryInterceptor,
    StreamInterceptor
{
    public function __construct(
        private string $password,
    ) {}

    #[\Override]
    public function interceptUnary(
        object $request,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $invoker,
    ): object {
        return $invoker($request, $invoke, $md->with('Authorization', $this->password), $cancellation);
    }

    #[\Override]
    public function interceptStream(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $newStream,
    ): ClientStream {
        return $newStream($invoke, $md->with('Authorization', $this->password), $cancellation);
    }
}
