<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stub;

use Amp\Cancellation;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

final readonly class AuthorizationClientInterceptor implements Client\Interceptor
{
    public function __construct(
        private string $password,
    ) {}

    #[\Override]
    public function intercept(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): ClientStream {
        return $next($invoke, $md->with('Authorization', $this->password), $cancellation);
    }
}
