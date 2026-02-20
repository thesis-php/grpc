<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;

/**
 * @internal
 */
final readonly class ServerErrorHandler implements ErrorHandler
{
    #[\Override]
    public function handleError(int $status, ?string $reason = null, ?Request $request = null): Response
    {
        return new Response($status);
    }
}
