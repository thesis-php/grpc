<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Http\Server\ExceptionHandler as HttpExceptionHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\ExceptionHandler;
use Thesis\Grpc\UnimplementedException;

/**
 * @internal
 */
final readonly class ServerExceptionHandler implements HttpExceptionHandler
{
    public function __construct(
        private ?ExceptionHandler $handler = null,
    ) {}

    #[\Override]
    public function handleException(Request $request, \Throwable $exception): Response
    {
        $this->handler?->handleException($request, $exception);

        $md = new Metadata();

        if ($exception instanceof UnimplementedException) {
            $md = $md
                ->withKey(Metadata\StatusCode::UNIMPLEMENTED)
                ->withKey(new Metadata\StatusMessage($exception->getMessage()));
        } else {
            $md = $md
                ->withKey(Metadata\StatusCode::INTERNAL);
        }

        if (($contentType = $md->value('content-type')) !== null) {
            $md = $md->withKey(new Metadata\ContentType($contentType));
        }

        return new Response(headers: $md->kv);
    }
}
