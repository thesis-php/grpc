<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

/**
 * @internal
 * @phpstan-type OnRequestStart = \Closure(Request): void
 * @phpstan-type OnRequestFailed = \Closure(Request, \Throwable): void
 * @phpstan-type OnRequestEnd = \Closure(Request, Response): void
 * @phpstan-type OnRequestRejected = \Closure(Request): void
 * @phpstan-type OnApplicationInterceptorStart = \Closure(Request, ApplicationInterceptor): void
 * @phpstan-type OnApplicationInterceptorEnd = \Closure(Request, ApplicationInterceptor, Response): void
 * @phpstan-type OnNetworkInterceptorStart = \Closure(Request, NetworkInterceptor): void
 * @phpstan-type OnNetworkInterceptorEnd = \Closure(Request, NetworkInterceptor, Response): void
 * @phpstan-type OnConnectionAcquired = \Closure(Request, Connection, int): void
 * @phpstan-type OnPush = \Closure(Request): void
 * @phpstan-type OnRequestHeaderStart = \Closure(Request, Stream): void
 * @phpstan-type OnRequestHeaderEnd = \Closure(Request, Stream): void
 * @phpstan-type OnRequestBodyStart = \Closure(Request, Stream): void
 * @phpstan-type OnRequestBodyProgress = \Closure(Request, Stream): void
 * @phpstan-type OnRequestBodyEnd = \Closure(Request, Stream): void
 * @phpstan-type OnResponseHeaderStart = \Closure(Request, Stream): void
 * @phpstan-type OnResponseHeaderEnd = \Closure(Request, Stream, Response): void
 * @phpstan-type OnResponseBodyStart = \Closure(Request, Stream, Response): void
 * @phpstan-type OnResponseBodyProgress = \Closure(Request, Stream, Response): void
 * @phpstan-type OnResponseBodyEnd = \Closure(Request, Stream, Response): void
 */
final class RequestEventListener implements EventListener
{
    /** @var list<OnRequestStart> */
    private array $onRequestStart = [];

    /** @var list<OnRequestFailed> */
    private array $onRequestFailed = [];

    /** @var list<OnRequestEnd> */
    private array $onRequestEnd = [];

    /** @var list<OnRequestRejected> */
    private array $onRequestRejected = [];

    /** @var list<OnApplicationInterceptorStart> */
    private array $onApplicationInterceptorStart = [];

    /** @var list<OnApplicationInterceptorEnd> */
    private array $onApplicationInterceptorEnd = [];

    /** @var list<OnNetworkInterceptorStart> */
    private array $onNetworkInterceptorStart = [];

    /** @var list<OnNetworkInterceptorEnd> */
    private array $onNetworkInterceptorEnd = [];

    /** @var list<OnConnectionAcquired> */
    private array $onConnectionAcquired = [];

    /** @var list<OnPush> */
    private array $onPush = [];

    /** @var list<OnRequestHeaderStart> */
    private array $onRequestHeaderStart = [];

    /** @var list<OnRequestHeaderEnd> */
    private array $onRequestHeaderEnd = [];

    /** @var list<OnRequestBodyStart> */
    private array $onRequestBodyStart = [];

    /** @var list<OnRequestBodyProgress> */
    private array $onRequestBodyProgress = [];

    /** @var list<OnRequestBodyEnd> */
    private array $onRequestBodyEnd = [];

    /** @var list<OnResponseHeaderStart> */
    private array $onResponseHeaderStart = [];

    /** @var list<OnResponseHeaderEnd> */
    private array $onResponseHeaderEnd = [];

    /** @var list<OnResponseBodyStart> */
    private array $onResponseBodyStart = [];

    /** @var list<OnResponseBodyProgress> */
    private array $onResponseBodyProgress = [];

    /** @var list<OnResponseBodyEnd> */
    private array $onResponseBodyEnd = [];

    /**
     * @param OnRequestStart $f
     */
    public function onRequestStart(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestStart = [
            ...$listener->onRequestStart,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestStart(Request $request): void
    {
        foreach ($this->onRequestStart as $listener) {
            $listener($request);
        }
    }

    /**
     * @param OnRequestFailed $f
     */
    public function onRequestFailed(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestFailed = [
            ...$listener->onRequestFailed,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestFailed(Request $request, \Throwable $exception): void
    {
        foreach ($this->onRequestFailed as $listener) {
            $listener($request, $exception);
        }
    }

    /**
     * @param OnRequestEnd $f
     */
    public function onRequestEnd(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestEnd = [
            ...$listener->onRequestEnd,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestEnd(Request $request, Response $response): void
    {
        foreach ($this->onRequestEnd as $listener) {
            $listener($request, $response);
        }
    }

    /**
     * @param OnRequestRejected $f
     */
    public function onRequestRejected(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestRejected = [
            ...$listener->onRequestRejected,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestRejected(Request $request): void
    {
        foreach ($this->onRequestRejected as $listener) {
            $listener($request);
        }
    }

    /**
     * @param OnApplicationInterceptorStart $f
     */
    public function onApplicationInterceptorStart(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onApplicationInterceptorStart = [
            ...$listener->onApplicationInterceptorStart,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function applicationInterceptorStart(Request $request, ApplicationInterceptor $interceptor): void
    {
        foreach ($this->onApplicationInterceptorStart as $listener) {
            $listener($request, $interceptor);
        }
    }

    /**
     * @param OnApplicationInterceptorEnd $f
     */
    public function onApplicationInterceptorEnd(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onApplicationInterceptorEnd = [
            ...$listener->onApplicationInterceptorEnd,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function applicationInterceptorEnd(Request $request, ApplicationInterceptor $interceptor, Response $response): void
    {
        foreach ($this->onApplicationInterceptorEnd as $listener) {
            $listener($request, $interceptor, $response);
        }
    }

    /**
     * @param OnNetworkInterceptorStart $f
     */
    public function onNetworkInterceptorStart(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onNetworkInterceptorStart = [
            ...$listener->onNetworkInterceptorStart,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function networkInterceptorStart(Request $request, NetworkInterceptor $interceptor): void
    {
        foreach ($this->onNetworkInterceptorStart as $listener) {
            $listener($request, $interceptor);
        }
    }

    /**
     * @param OnNetworkInterceptorEnd $f
     */
    public function onNetworkInterceptorEnd(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onNetworkInterceptorEnd = [
            ...$listener->onNetworkInterceptorEnd,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function networkInterceptorEnd(Request $request, NetworkInterceptor $interceptor, Response $response): void
    {
        foreach ($this->onNetworkInterceptorEnd as $listener) {
            $listener($request, $interceptor, $response);
        }
    }

    /**
     * @param OnConnectionAcquired $f
     */
    public function onConnectionAcquired(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onConnectionAcquired = [
            ...$listener->onConnectionAcquired,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function connectionAcquired(Request $request, Connection $connection, int $streamCount): void
    {
        foreach ($this->onConnectionAcquired as $listener) {
            $listener($request, $connection, $streamCount);
        }
    }

    /**
     * @param OnPush $f
     */
    public function onPush(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onPush = [
            ...$listener->onPush,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function push(Request $request): void
    {
        foreach ($this->onPush as $listener) {
            $listener($request);
        }
    }

    /**
     * @param OnRequestHeaderStart $f
     */
    public function onRequestHeaderStart(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestHeaderStart = [
            ...$listener->onRequestHeaderStart,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestHeaderStart(Request $request, Stream $stream): void
    {
        foreach ($this->onRequestHeaderStart as $listener) {
            $listener($request, $stream);
        }
    }

    /**
     * @param OnRequestHeaderEnd $f
     */
    public function onRequestHeaderEnd(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestHeaderEnd = [
            ...$listener->onRequestHeaderEnd,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestHeaderEnd(Request $request, Stream $stream): void
    {
        foreach ($this->onRequestHeaderEnd as $listener) {
            $listener($request, $stream);
        }
    }

    /**
     * @param OnRequestBodyStart $f
     */
    public function onRequestBodyStart(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestBodyStart = [
            ...$listener->onRequestBodyStart,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestBodyStart(Request $request, Stream $stream): void
    {
        foreach ($this->onRequestBodyStart as $listener) {
            $listener($request, $stream);
        }
    }

    /**
     * @param OnRequestBodyProgress $f
     */
    public function onRequestBodyProgress(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestBodyProgress = [
            ...$listener->onRequestBodyProgress,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestBodyProgress(Request $request, Stream $stream): void
    {
        foreach ($this->onRequestBodyProgress as $listener) {
            $listener($request, $stream);
        }
    }

    /**
     * @param OnRequestBodyEnd $f
     */
    public function onRequestBodyEnd(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onRequestBodyEnd = [
            ...$listener->onRequestBodyEnd,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function requestBodyEnd(Request $request, Stream $stream): void
    {
        foreach ($this->onRequestBodyEnd as $listener) {
            $listener($request, $stream);
        }
    }

    /**
     * @param OnResponseHeaderStart $f
     */
    public function onResponseHeaderStart(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onResponseHeaderStart = [
            ...$listener->onResponseHeaderStart,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function responseHeaderStart(Request $request, Stream $stream): void
    {
        foreach ($this->onResponseHeaderStart as $listener) {
            $listener($request, $stream);
        }
    }

    /**
     * @param OnResponseHeaderEnd $f
     */
    public function onResponseHeaderEnd(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onResponseHeaderEnd = [
            ...$listener->onResponseHeaderEnd,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function responseHeaderEnd(Request $request, Stream $stream, Response $response): void
    {
        foreach ($this->onResponseHeaderEnd as $listener) {
            $listener($request, $stream, $response);
        }
    }

    /**
     * @param OnResponseBodyStart $f
     */
    public function onResponseBodyStart(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onResponseBodyStart = [
            ...$listener->onResponseBodyStart,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function responseBodyStart(Request $request, Stream $stream, Response $response): void
    {
        foreach ($this->onResponseBodyStart as $listener) {
            $listener($request, $stream, $response);
        }
    }

    /**
     * @param OnResponseBodyProgress $f
     */
    public function onResponseBodyProgress(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onResponseBodyProgress = [
            ...$listener->onResponseBodyProgress,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function responseBodyProgress(Request $request, Stream $stream, Response $response): void
    {
        foreach ($this->onResponseBodyProgress as $listener) {
            $listener($request, $stream, $response);
        }
    }

    /**
     * @param OnResponseBodyEnd $f
     */
    public function onResponseBodyEnd(\Closure $f): self
    {
        $listener = clone $this;
        $listener->onResponseBodyEnd = [
            ...$listener->onResponseBodyEnd,
            $f,
        ];

        return $listener;
    }

    #[\Override]
    public function responseBodyEnd(Request $request, Stream $stream, Response $response): void
    {
        foreach ($this->onResponseBodyEnd as $listener) {
            $listener($request, $stream, $response);
        }
    }
}
