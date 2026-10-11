<?php

declare(strict_types=1);

namespace Thesis\Grpc\Interop;

use Amp\DeferredCancellation;
use Grpc\Testing\EchoStatus;
use Grpc\Testing\Empty_;
use Grpc\Testing\Payload;
use Grpc\Testing\ResponseParameters;
use Grpc\Testing\SimpleRequest;
use Grpc\Testing\SimpleResponse;
use Grpc\Testing\StreamingInputCallRequest;
use Grpc\Testing\StreamingOutputCallRequest;
use Grpc\Testing\StreamingOutputCallResponse;
use Grpc\Testing\TestServiceClient;
use Grpc\Testing\UnimplementedServiceClient;
use PHPUnit\Framework\Assert;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Exception\ClientStreamIsClosed;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\RpcType;
use function Amp\async;
use function Amp\delay;
use function Amp\Future\await;

/**
 * Client test cases from https://github.com/grpc/grpc/blob/master/doc/interop-test-descriptions.md.
 */
final readonly class TestCases
{
    private const string STATUS_MESSAGE = 'test status message';
    private const string SPECIAL_STATUS_MESSAGE = "\t\ntest with whitespace\r\nand Unicode BMP ☺ and non-BMP 😈\t\n";
    private const string INITIAL_METADATA_KEY = 'x-grpc-test-echo-initial';
    private const string INITIAL_METADATA_VALUE = 'test_initial_metadata_value';
    private const string TRAILING_METADATA_KEY = 'x-grpc-test-echo-trailing-bin';
    private const string TRAILING_METADATA_VALUE = "\xab\xab\xab";

    private Client $client;

    private TestServiceClient $test;

    /**
     * @param \Closure(): Client $connect
     */
    public function __construct(
        private \Closure $connect,
        private Flags $flags,
        private string $serverUri,
    ) {
        $this->client = $connect();
        $this->test = new TestServiceClient($this->client);
    }

    public function emptyUnary(): void
    {
        $this->test->emptyCall(new Empty_());
    }

    public function largeUnary(): void
    {
        $response = $this->test->unaryCall(new SimpleRequest(responseSize: 314_159, payload: self::payload(271_828)));

        Assert::assertSame(314_159, self::size($response->payload));
    }

    public function clientStreaming(): void
    {
        $stream = $this->test->streamingInputCall();
        $stream->send(...array_map(
            static fn(int $size) => new StreamingInputCallRequest(self::payload($size)),
            [27_182, 8, 1_828, 45_904],
        ));

        Assert::assertSame(74_922, $stream->close()->aggregatedPayloadSize);
    }

    public function serverStreaming(): void
    {
        $sizes = [31_415, 9, 2_653, 58_979];

        $stream = $this->test->streamingOutputCall(new StreamingOutputCallRequest(
            responseParameters: array_map(static fn(int $size) => new ResponseParameters($size), $sizes),
        ));

        Assert::assertSame($sizes, self::sizes($stream));
    }

    public function pingPong(): void
    {
        $stream = $this->test->fullDuplexCall();

        foreach ([[31_415, 27_182], [9, 8], [2_653, 1_828], [58_979, 45_904]] as [$responseSize, $requestSize]) {
            $stream->send(new StreamingOutputCallRequest(
                responseParameters: [new ResponseParameters($responseSize)],
                payload: self::payload($requestSize),
            ));

            Assert::assertSame($responseSize, self::size($stream->receive()->payload));
        }

        $stream->close();

        Assert::assertSame([], self::sizes($stream));
    }

    public function emptyStream(): void
    {
        $stream = $this->test->fullDuplexCall();
        $stream->close();

        Assert::assertSame([], self::sizes($stream));
    }

    public function statusCodeAndMessage(): void
    {
        $status = new EchoStatus(Code::UNKNOWN->value, self::STATUS_MESSAGE);

        $error = self::error(fn() => $this->test->unaryCall(new SimpleRequest(responseStatus: $status)));
        Assert::assertSame([Code::UNKNOWN, self::STATUS_MESSAGE], [$error->statusCode, $error->statusMessage]);

        $error = self::error(function () use ($status): void {
            $stream = $this->test->fullDuplexCall();
            $stream->send(new StreamingOutputCallRequest(responseStatus: $status));
            $stream->close();
            self::sizes($stream);
        });
        Assert::assertSame([Code::UNKNOWN, self::STATUS_MESSAGE], [$error->statusCode, $error->statusMessage]);
    }

    public function specialStatusMessage(): void
    {
        $error = self::error(fn() => $this->test->unaryCall(new SimpleRequest(
            responseStatus: new EchoStatus(Code::UNKNOWN->value, self::SPECIAL_STATUS_MESSAGE),
        )));

        Assert::assertSame([Code::UNKNOWN, self::SPECIAL_STATUS_MESSAGE], [$error->statusCode, $error->statusMessage]);
    }

    public function unimplementedMethod(): void
    {
        $error = self::error(fn() => $this->test->unimplementedCall(new Empty_()));

        Assert::assertSame(Code::UNIMPLEMENTED, $error->statusCode);
    }

    public function unimplementedService(): void
    {
        $error = self::error(fn() => new UnimplementedServiceClient($this->client)->unimplementedCall(new Empty_()));

        Assert::assertSame(Code::UNIMPLEMENTED, $error->statusCode);
    }

    public function cancelAfterBegin(): void
    {
        $cancellation = new DeferredCancellation();
        $stream = $this->test->streamingInputCall(cancellation: $cancellation->getCancellation());
        $cancellation->cancel();

        Assert::assertSame(Code::CANCELLED, self::error($stream->close(...))->statusCode);
    }

    public function cancelAfterFirstResponse(): void
    {
        $cancellation = new DeferredCancellation();
        $stream = $this->test->fullDuplexCall(cancellation: $cancellation->getCancellation());
        $stream->send(new StreamingOutputCallRequest(
            responseParameters: [new ResponseParameters(31_415)],
            payload: self::payload(27_182),
        ));
        $stream->receive();
        $cancellation->cancel();

        Assert::assertSame(Code::CANCELLED, self::error($stream->receive(...))->statusCode);
    }

    public function timeoutOnSleepingServer(): void
    {
        $stream = $this->test->fullDuplexCall(new Metadata()->withKey(Metadata\Timeout::milliseconds(1)));

        $error = self::error(static function () use ($stream): void {
            try {
                $stream->send(new StreamingOutputCallRequest(payload: self::payload(27_182)));
            } catch (ClientStreamIsClosed) {
                // Like io.EOF from Send() in grpc-go: the status comes from receive().
            }

            $stream->receive();
        });

        Assert::assertSame(Code::DEADLINE_EXCEEDED, $error->statusCode);
    }

    public function customMetadata(): void
    {
        $md = new Metadata([
            self::INITIAL_METADATA_KEY => self::INITIAL_METADATA_VALUE,
            self::TRAILING_METADATA_KEY => self::TRAILING_METADATA_VALUE,
        ]);

        // Client::invoke() does not expose the response metadata of a unary call.
        $stream = $this->client->createStream(
            new Client\Invoke('/grpc.testing.TestService/UnaryCall', SimpleResponse::class, RpcType::Unary),
            $md,
        );
        $stream->send(new SimpleRequest(responseSize: 314_159, payload: self::payload(271_828)));
        $stream->close();

        Assert::assertSame(314_159, self::size($stream->receive()->payload));
        self::assertEchoedMetadata($stream);

        $stream = $this->client->createStream(
            new Client\Invoke('/grpc.testing.TestService/FullDuplexCall', StreamingOutputCallResponse::class, RpcType::BidirectionalStream),
            $md,
        );
        $stream->send(new StreamingOutputCallRequest(
            responseParameters: [new ResponseParameters(314_159)],
            payload: self::payload(271_828),
        ));
        $stream->close();

        Assert::assertSame([314_159], self::sizes($stream));
        self::assertEchoedMetadata($stream);
    }

    public function rpcSoak(): void
    {
        $this->soak(newChannel: false);
    }

    public function channelSoak(): void
    {
        $this->soak(newChannel: true);
    }

    private function soak(bool $newChannel): void
    {
        $iterations = $this->flags->int('soak_iterations');
        $threads = $this->flags->int('soak_num_threads');
        $maxLatency = $this->flags->int('soak_per_iteration_max_acceptable_latency_ms');
        $minInterval = $this->flags->int('soak_min_time_ms_between_rpcs');
        $requestSize = $this->flags->int('soak_request_size');
        $responseSize = $this->flags->int('soak_response_size');
        $deadline = hrtime(true) + $this->flags->int('soak_overall_timeout_seconds') * 1_000_000_000;

        Assert::assertSame(0, $iterations % $threads, 'soak_iterations must be divisible by soak_num_threads.');

        $results = await(array_map(fn(int $thread) => async(function () use (
            $thread,
            $iterations,
            $threads,
            $newChannel,
            $maxLatency,
            $minInterval,
            $requestSize,
            $responseSize,
            $deadline,
        ): array {
            $failures = 0;
            $i = 0;

            for (; $i < intdiv($iterations, $threads) && hrtime(true) < $deadline; ++$i) {
                $start = hrtime(true);
                $client = $newChannel ? ($this->connect)() : $this->client;

                try {
                    $response = new TestServiceClient($client)->unaryCall(new SimpleRequest(
                        responseSize: $responseSize,
                        payload: self::payload($requestSize),
                    ));
                    $error = self::size($response->payload) === $responseSize ? '' : 'unexpected response size';
                } catch (InvokeError $e) {
                    $error = $e->getMessage();
                }

                $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);

                if ($newChannel) {
                    $client->close();
                }

                if ($error === '' && $elapsed > $maxLatency) {
                    $error = "exceeds max acceptable latency {$maxLatency}ms";
                }

                $failures += (int) ($error !== '');

                fprintf(
                    \STDERR,
                    "thread_id: %d soak iteration: %d elapsed_ms: %d peer: %s server_uri: %s %s\n",
                    $thread,
                    $i,
                    $elapsed,
                    $this->serverUri,
                    $this->serverUri,
                    $error === '' ? 'succeeded' : "failed: {$error}",
                );

                delay(max($minInterval - $elapsed, 0) / 1_000);
            }

            return [$i, $failures];
        }), range(0, $threads - 1)));

        Assert::assertSame($iterations, array_sum(array_column($results, 0)), 'Soak test did not complete all iterations in time.');
        Assert::assertLessThanOrEqual($this->flags->int('soak_max_failures'), array_sum(array_column($results, 1)), 'Soak test failures exceeded soak_max_failures.');
    }

    /**
     * @param \Closure(): mixed $call
     */
    private static function error(\Closure $call): InvokeError
    {
        try {
            $call();
        } catch (InvokeError $e) {
            return $e;
        }

        Assert::fail('The call was expected to fail with a status.');
    }

    /**
     * @param ClientStream<*, *> $stream
     */
    private static function assertEchoedMetadata(ClientStream $stream): void
    {
        Assert::assertSame([self::INITIAL_METADATA_VALUE], $stream->headers()[self::INITIAL_METADATA_KEY]);
        Assert::assertSame([self::TRAILING_METADATA_VALUE], $stream->trailers()[self::TRAILING_METADATA_KEY]);
    }

    private static function payload(int $size): Payload
    {
        return new Payload(body: str_repeat("\0", $size));
    }

    private static function size(?Payload $payload): int
    {
        return \strlen($payload->body ?? '');
    }

    /**
     * Reads the stream to the end, so a non-OK status is thrown.
     *
     * @param iterable<StreamingOutputCallResponse> $stream
     * @return list<int>
     */
    private static function sizes(iterable $stream): array
    {
        return array_map(
            static fn(StreamingOutputCallResponse $response) => self::size($response->payload),
            iterator_to_array($stream, preserve_keys: false),
        );
    }
}
