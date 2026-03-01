<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\NullCancellation;

/**
 * @api
 */
interface Server
{
    /**
     * The server can subscribe to the {@see Cancellation} to stop automatically when it is triggered, for example on a signal.
     * Starting the server does not block the application. If you need to wait for the server to stop, wrap the call in a {@see \Amp\async()} and await it.
     */
    public function start(Cancellation $cancellation = new NullCancellation()): void;

    /**
     * Calling this method will attempt to gracefully finish processing all ongoing requests.
     * To prevent this from taking too long, you can provide a {@see Cancellation} that, once triggered, will cause the server to stop waiting and shutdown immediately.
     */
    public function stop(Cancellation $cancellation = new NullCancellation()): void;
}
