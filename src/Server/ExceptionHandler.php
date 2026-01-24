<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Http\Server\Request;

/**
 * @api
 */
interface ExceptionHandler
{
    public function handleException(Request $request, \Throwable $exception): void;
}
