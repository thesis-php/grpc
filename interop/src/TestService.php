<?php

declare(strict_types=1);

namespace Thesis\Grpc\Interop;

use Amp\Cancellation;
use Grpc\Testing\EchoStatus;
use Grpc\Testing\Empty_;
use Grpc\Testing\Payload;
use Grpc\Testing\ResponseParameters;
use Grpc\Testing\SimpleRequest;
use Grpc\Testing\SimpleResponse;
use Grpc\Testing\StreamingInputCallResponse;
use Grpc\Testing\StreamingOutputCallRequest;
use Grpc\Testing\StreamingOutputCallResponse;
use Grpc\Testing\TestServiceServer;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\BidirectionalStreamChannel;
use Thesis\Grpc\Server\ClientStreamChannel;
use function Amp\delay;

final readonly class TestService implements TestServiceServer
{
    #[\Override]
    public function emptyCall(Empty_ $request, Metadata $md, Cancellation $cancellation): Empty_
    {
        return new Empty_();
    }

    #[\Override]
    public function unaryCall(SimpleRequest $request, Metadata $md, Cancellation $cancellation): SimpleResponse
    {
        self::echoStatus($request->responseStatus);

        return new SimpleResponse(new Payload(body: str_repeat("\0", $request->responseSize)));
    }

    #[\Override]
    public function cacheableUnaryCall(SimpleRequest $request, Metadata $md, Cancellation $cancellation): SimpleResponse
    {
        throw new InvokeError(Code::UNIMPLEMENTED);
    }

    #[\Override]
    public function streamingOutputCall(StreamingOutputCallRequest $request, Metadata $md, Cancellation $cancellation): iterable
    {
        yield from self::responses($request->responseParameters, $cancellation);
    }

    #[\Override]
    public function streamingInputCall(ClientStreamChannel $stream, Metadata $md, Cancellation $cancellation): StreamingInputCallResponse
    {
        $size = 0;

        foreach ($stream as $request) {
            $size += \strlen($request->payload->body ?? '');
        }

        return new StreamingInputCallResponse($size);
    }

    #[\Override]
    public function fullDuplexCall(BidirectionalStreamChannel $stream, Metadata $md, Cancellation $cancellation): void
    {
        EchoMetadata::echo($md, $stream->responseHeaders, $stream->trailers);

        foreach ($stream as $request) {
            self::echoStatus($request->responseStatus);

            foreach (self::responses($request->responseParameters, $cancellation) as $response) {
                $stream->send($response);
            }
        }
    }

    #[\Override]
    public function halfDuplexCall(BidirectionalStreamChannel $stream, Metadata $md, Cancellation $cancellation): void
    {
        throw new InvokeError(Code::UNIMPLEMENTED);
    }

    #[\Override]
    public function unimplementedCall(Empty_ $request, Metadata $md, Cancellation $cancellation): Empty_
    {
        throw new InvokeError(Code::UNIMPLEMENTED);
    }

    private static function echoStatus(?EchoStatus $status): void
    {
        if ($status !== null && $status->code !== Code::OK->value) {
            throw new InvokeError(Code::tryFrom($status->code) ?? Code::UNKNOWN, $status->message);
        }
    }

    /**
     * @param list<ResponseParameters> $parameters
     * @return \Generator<int, StreamingOutputCallResponse>
     */
    private static function responses(array $parameters, Cancellation $cancellation): \Generator
    {
        foreach ($parameters as $parameter) {
            delay($parameter->intervalUs / 1_000_000, cancellation: $cancellation);

            yield new StreamingOutputCallResponse(new Payload(body: str_repeat("\0", $parameter->size)));
        }
    }
}
