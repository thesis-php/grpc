<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Amp\CancelledException;
use Google\Rpc;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handle;
use Thesis\Grpc\Server\Interceptor;
use Thesis\Grpc\ServerStream;
use Thesis\Grpc\Status;
use Thesis\Protobuf;

/**
 * @internal
 */
final readonly class StreamHandleInterceptor implements Interceptor
{
    private Metadata $ok;

    public function __construct(
        private Protobuf\Encoder $protobuf,
    ) {
        $this->ok = new Metadata()->withKey(new Metadata\Status(Rpc\Code::OK));
    }

    #[\Override]
    public function intercept(
        Handle $handle,
        Metadata $md,
        ServerStream $stream,
        Cancellation $cancellation,
        callable $next,
    ): void {
        $trailers = new Metadata();

        // Since the stream is closed by the rpc handler, we add "grpc-status" to trailers beforehand to avoid shifting this responsibility onto the handlers.
        // In case of an error, this trailer will be overridden below.
        $stream->trailers->join($this->ok);

        try {
            $next($handle, $md, $stream, $cancellation);
        } catch (InvokeError $e) {
            $trailers = $trailers->withKey(Status\serializeContext(
                new Status\Context($e->statusCode, $e->statusMessage, $e->details),
                $this->protobuf,
            ));
        } catch (CancelledException) {
            $trailers = $trailers->withKey(new Metadata\Status(Rpc\Code::DEADLINE_EXCEEDED));
        } catch (\Throwable) {
            $trailers = $trailers->withKey(new Metadata\Status(Rpc\Code::INTERNAL));
        } finally {
            $stream->trailers->join($trailers);
            $stream->close();
        }
    }
}
