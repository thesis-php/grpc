<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 * @template In of object
 * @template Out of object
 */
interface Handler
{
    /**
     * @param ServerStream<In, Out> $stream
     */
    public function handle(ServerStream $stream, Metadata $md, Cancellation $cancellation): void;
}
