<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;
use Amp\Http\Client\HttpException;
use Google\Rpc\Code;
use Random\Randomizer;
use Thesis\Grpc\Exception\ClientStreamIsClosed;
use Thesis\Grpc\GrpcException;
use Thesis\Grpc\InvokeError;
use function Amp\delay;

/**
 * @api
 */
final readonly class Retry
{
    /**
     * @param positive-int $maxAttempts total attempts, including the first
     * @param float $initialBackoff base back-off in seconds before the second attempt
     * @param float $maxBackoff upper bound for a single back-off in seconds
     * @param float $backoffMultiplier factor the back-off grows by each attempt
     * @param list<Code> $retryableStatusCodes gRPC statuses that trigger a retry
     */
    public function __construct(
        private int $maxAttempts = 3,
        private float $initialBackoff = 0.05,
        private float $maxBackoff = 1.0,
        private float $backoffMultiplier = 2.0,
        private array $retryableStatusCodes = [
            Code::UNAVAILABLE,
        ],
        private Randomizer $randomizer = new Randomizer(),
    ) {}

    public static function disabled(): self
    {
        return new self(maxAttempts: 1);
    }

    /**
     * @template T
     * @param \Closure(): T $call
     * @return T
     * @throws GrpcException
     */
    public function call(\Closure $call, Cancellation $cancellation): mixed
    {
        $error = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; ++$attempt) {
            if ($attempt > 1) {
                $this->backoff($attempt - 1, $cancellation);
            }

            try {
                return $call();
            } catch (\Throwable $e) {
                if (!$this->shouldRetry($e)) {
                    throw self::createException($e);
                }

                $error = $e;
            }
        }

        throw self::createException($error ?? new InvokeError(Code::UNAVAILABLE, 'no attempts were made'));
    }

    private function shouldRetry(\Throwable $e): bool
    {
        if ($e instanceof InvokeError) {
            return \in_array($e->statusCode, $this->retryableStatusCodes, true);
        }

        return $e instanceof HttpException;
    }

    /**
     * @param positive-int $attempt
     */
    private function backoff(int $attempt, Cancellation $cancellation): void
    {
        $ceiling = min($this->maxBackoff, $this->initialBackoff * $this->backoffMultiplier ** ($attempt - 1));

        if ($ceiling > 0.0) {
            delay($this->randomizer->getFloat(0.0, $ceiling), cancellation: $cancellation);
        }
    }

    private static function createException(\Throwable $e): GrpcException
    {
        if ($e instanceof InvokeError || $e instanceof ClientStreamIsClosed) {
            return $e;
        }

        return new InvokeError(Code::UNAVAILABLE, $e->getMessage(), previous: $e);
    }
}
