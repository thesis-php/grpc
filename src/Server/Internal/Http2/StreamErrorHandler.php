<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\CancelledException;
use Google\Rpc;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Status;
use Thesis\Protobuf;

/**
 * @internal
 */
final readonly class StreamErrorHandler
{
    public function __construct(
        private Protobuf\Encoder $protobuf,
    ) {}

    public function handle(\Throwable $e): Metadata
    {
        $md = new Metadata();

        if ($e instanceof InvokeError) {
            $md = $md->withKey(Status\serializeContext(
                new Status\Context($e->statusCode, $e->statusMessage, $e->details),
                $this->protobuf,
            ));
        } elseif ($e instanceof CancelledException) {
            $md = $md->withKey(new Metadata\Status(Rpc\Code::DEADLINE_EXCEEDED));
        } else {
            $md = $md->withKey(new Metadata\Status(Rpc\Code::INTERNAL));
        }

        return $md;
    }
}
