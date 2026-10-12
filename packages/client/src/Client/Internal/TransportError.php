<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Http\Client\SocketException;
use Amp\Http\Http2\Http2Parser;
use Amp\Http\Http2\Http2StreamException;
use Amp\Socket;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\InvokeError;

/**
 * Maps a transport failure to a gRPC status as PROTOCOL-HTTP2.md requires: a stream reset by the server
 * by its HTTP/2 error code, a lost or unreachable connection to UNAVAILABLE. Like grpc-go, a failure
 * of a call whose cancellation was already requested is reported as that cancellation.
 *
 * @internal
 */
final class TransportError
{
    public static function from(\Throwable $e, Cancellation $cancellation): InvokeError
    {
        if ($e instanceof InvokeError) {
            return $e;
        }

        try {
            $cancellation->throwIfRequested();
        } catch (CancelledException $cancelled) {
            return CancellationError::from($cancelled);
        }

        if ($e instanceof CancelledException) {
            return CancellationError::from($e);
        }

        $code = Code::INTERNAL;

        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof Http2StreamException) {
                $code = match ($cause->getCode()) {
                    Http2Parser::REFUSED_STREAM => Code::UNAVAILABLE,
                    Http2Parser::CANCEL => Code::CANCELLED,
                    Http2Parser::ENHANCE_YOUR_CALM => Code::RESOURCE_EXHAUSTED,
                    Http2Parser::INADEQUATE_SECURITY => Code::PERMISSION_DENIED,
                    default => Code::INTERNAL,
                };

                break;
            }

            if ($cause instanceof SocketException || $cause instanceof Socket\SocketException) {
                $code = Code::UNAVAILABLE;
            }
        }

        return new InvokeError($code, $e->getMessage(), previous: $e);
    }
}
