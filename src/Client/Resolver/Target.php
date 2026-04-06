<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Resolver;

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
                    Scheme::Dns => self::parseDns($addr, $target),
                    Scheme::Ipv4, Scheme::Ipv6 => self::parseAddressList($scheme, $addr, $target),
                    Scheme::Unix => self::parseUnix($addr, $target),
                };
            }
        }

        return self::parseHostPort($target);
    }

    /**
     * @internal use {@see Target::parse()} instead
     * @param non-empty-list<non-empty-string> $addresses
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
    private static function parseDns(string $addr, string $target): self
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

        self::validateDnsEndpoint($addr, $target); // @phpstan-ignore staticMethod.alreadyNarrowedType

        return new self(Scheme::Dns, [$addr], $authority);
    }

    /**
     * @param non-empty-string $addr
     * @throws InvalidTarget
     */
    private static function parseAddressList(Scheme $scheme, string $addr, string $original): self
    {
        $addresses = explode(',', $addr);
        $result = [];

        foreach ($addresses as $address) {
            $address = trim($address);

            if ($address === '' || str_contains($address, ' ')) {
                throw new InvalidTarget($original);
            }

            $result[] = $address;
        }

        return new self($scheme, $result);
    }

    /**
     * @throws InvalidTarget
     */
    private static function parseHostPort(string $target): self
    {
        $colon = strpos($target, ':');

        if ($colon !== false && !ctype_digit(substr($target, $colon + 1))) {
            throw new InvalidTarget($target);
        }

        self::validateDnsEndpoint($target);

        return new self(Scheme::Dns, [$target]);
    }

    /**
     * @param non-empty-string $addr
     * @param non-empty-string $target
     * @throws InvalidTarget
     */
    private static function parseUnix(string $addr, string $target): self
    {
        if (str_starts_with($addr, '//')) {
            $addr = substr($addr, 2);
        }

        if ($addr === '' || $addr[0] !== '/') {
            throw new InvalidTarget($target);
        }

        return new self(Scheme::Unix, [$addr]);
    }

    /**
     * @phpstan-assert non-empty-string $addr
     * @throws InvalidTarget
     */
    private static function validateDnsEndpoint(string $addr, ?string $target = null): void
    {
        if ($addr === '' || str_contains($addr, '/') || str_contains($addr, ' ')) {
            throw new InvalidTarget($target ?? $addr);
        }
    }
}
