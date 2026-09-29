<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\CancelledException;
use Amp\TimeoutException;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\InvokeError;

/**
 * Reports a call the caller stopped waiting for the way grpc-go does: an expired deadline
 * (a {@see TimeoutException} behind the cancellation, e.g. from {@see \Amp\TimeoutCancellation})
 * is DEADLINE_EXCEEDED, any other cancellation is CANCELLED. The original exception is kept
 * as the previous one.
 *
 * @internal
 */
final class CancellationError
{
    public static function from(CancelledException $cancelled): InvokeError
    {
        for ($cause = $cancelled->getPrevious(); $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof TimeoutException) {
                return new InvokeError(Code::DEADLINE_EXCEEDED, $cause->getMessage(), previous: $cancelled);
            }
        }

        return new InvokeError(Code::CANCELLED, $cancelled->getMessage(), previous: $cancelled);
    }
}
