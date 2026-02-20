<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Google\Rpc;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\DecoratedStream;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 * @template-covariant In of object
 * @template Out of object
 * @template-extends DecoratedStream<In, Out>
 */
final class WrappedServerStream extends DecoratedStream
{
    public function __construct(ServerStream $stream)
    {
        parent::__construct($stream);
    }

    #[\Override]
    public function close(): void
    {
        if (!$this->trailers->has(Metadata\Status::STATUS_HEADER)) {
            $this->trailers->join(new Metadata()->withKey(new Metadata\Status(Rpc\Code::OK)));
        }

        parent::close();
    }
}
