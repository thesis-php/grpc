<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stub;

use Amp\Cancellation;
use Google\Rpc\Code;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\Server\StreamInterceptor;
use Thesis\Grpc\Server\UnaryInterceptor;
use Thesis\Grpc\ServerStream;

final readonly class AuthorizationServerInterceptor implements
    UnaryInterceptor,
    StreamInterceptor
{
    public function __construct(
        private string $password,
    ) {}

    #[\Override]
    public function interceptUnary(
        object $request,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $handler,
    ): object {
        $this->authorize($md);

        return $handler($request, $info, $md, $cancellation);
    }

    #[\Override]
    public function interceptStream(
        ServerStream $stream,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): void {
        $this->authorize($md);

        $next($stream, $info, $md, $cancellation);
    }

    private function authorize(Metadata $md): void
    {
        if ($md->value('Authorization') !== $this->password) {
            throw new InvokeError(Code::UNAUTHENTICATED, 'Use authorization, Luke!');
        }
    }
}
