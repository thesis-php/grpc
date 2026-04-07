<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use League\Uri\Uri;

/**
 * @api
 * @see https://github.com/grpc/grpc/blob/master/doc/naming.md
 */
final readonly class Target
{
    /**
     * @param non-empty-string $target
     * @throws InvalidTarget
     */
    public static function parse(string $target): self
    {
        foreach (Scheme::cases() as $scheme) {
            $prefix = "{$scheme->value}:";

            if (str_starts_with($target, $prefix)) {
                $addr = substr($target, \strlen($prefix));
                if ($addr === '') {
                    throw new InvalidTarget($target);
                }

                return match ($scheme) {
                    Scheme::Dns, Scheme::Passthrough => self::parseDns($addr, $target, $scheme),
                    Scheme::Ipv4, Scheme::Ipv6 => new self($scheme, self::parseAddresses($addr, $target)),
                    Scheme::Unix => new self($scheme, [self::parseUnix($addr, $target)]),
                };
            }
        }

        return new self(Scheme::Dns, self::parseAddresses($target));
    }

    /**
     * @internal use {@see Target::parse()} instead
     * @param non-empty-list<TargetAddress> $addresses
     * @param ?non-empty-string $authority DNS server address (only for dns://authority/host form)
     */
    public function __construct(
        public Scheme $scheme,
        public array $addresses,
        public ?string $authority = null,
    ) {}

    /**
     * @param non-empty-string $addr
     * @param non-empty-string $target
     * @throws InvalidTarget
     */
    private static function parseDns(string $addr, string $target, Scheme $scheme): self
    {
        $authority = null;

        if (str_starts_with($addr, '//')) {
            $addr = substr($addr, 2);
            $slash = strpos($addr, '/');
            if ($slash === false) {
                throw new InvalidTarget($target);
            }

            $auth = substr($addr, 0, $slash);
            if ($auth !== '') {
                $authority = $auth;
            }

            $addr = substr($addr, $slash + 1);
            if ($addr === '') {
                throw new InvalidTarget($target);
            }
        }

        return new self(
            $scheme,
            self::parseAddresses($addr, $target),
            $authority,
        );
    }

    /**
     * @param non-empty-string $addr
     * @param non-empty-string $target
     * @throws InvalidTarget
     */
    private static function parseUnix(string $addr, string $target): TargetAddress
    {
        if (str_starts_with($addr, '//')) {
            $addr = substr($addr, 2);
        }

        if ($addr === '' || $addr[0] !== '/') {
            throw new InvalidTarget($target);
        }

        return new TargetAddress($addr, 0);
    }

    /**
     * @param non-empty-string $addr
     * @param ?non-empty-string $target
     * @return non-empty-list<TargetAddress>
     * @throws InvalidTarget
     */
    private static function parseAddresses(string $addr, ?string $target = null): array
    {
        return array_map(
            static fn(string $address) => self::parseAddress(trim($address), $target ?? $addr),
            explode(',', $addr),
        );
    }

    /**
     * @param non-empty-string $target
     * @throws InvalidTarget
     */
    private static function parseAddress(string $addr, string $target): TargetAddress
    {
        $addr = urldecode($addr);
        $uri = Uri::parse("tcp://{$addr}") ?? throw new InvalidTarget($target);

        $host = $uri->getHost() ?? '';

        if ($host === '') {
            throw new InvalidTarget($target);
        }

        $port = $uri->getPort() ?? 0;

        if ($port < 1 || $port > 65_535 || $uri->getPath() !== '') {
            throw new InvalidTarget($target);
        }

        return new TargetAddress($host, $port);
    }
}
