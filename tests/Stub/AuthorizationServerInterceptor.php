<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stub;

use Amp\Cancellation;
use Google\Rpc\Code;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\ServerStream;

final readonly class AuthorizationServerInterceptor implements Server\Interceptor
{
    public function __construct(
        private string $password,
    ) {}

    #[\Override]
    public function intercept(
        ServerStream $stream,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): void {
        if ($md->value('Authorization') !== $this->password) {
            throw new InvokeError(Code::UNAUTHENTICATED, 'Use authorization, Luke!');
        }

        $next($stream, $info, $md, $cancellation);
    }
}
