<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class Timeout implements
    MetadataKey,
    \Stringable
{
    public const string HEADER = 'grpc-timeout';
    public const string TYPE_HOUR = 'H';
    public const string TYPE_MINUTE = 'M';
    public const string TYPE_SECOND = 'S';
    public const string TYPE_MILLISECOND = 'm';
    public const string TYPE_MICROSECOND = 'u';
    public const string TYPE_NANOSECOND  = 'n';

    /** @var non-empty-array<self::TYPE_*, 1> */
    public const array UNITS = [
        self::TYPE_HOUR => 1,
        self::TYPE_MINUTE => 1,
        self::TYPE_SECOND => 1,
        self::TYPE_MILLISECOND => 1,
        self::TYPE_MICROSECOND => 1,
        self::TYPE_NANOSECOND => 1,
    ];

    /**
     * @param non-negative-int $value
     * @param self::TYPE_* $unit
     */
    private function __construct(
        private int $value,
        private string $unit,
    ) {}

    /**
     * @param non-negative-int $value
     */
    public static function hours(int $value): self
    {
        return new self($value, self::TYPE_HOUR);
    }

    /**
     * @param non-negative-int $value
     */
    public static function minutes(int $value): self
    {
        return new self($value, self::TYPE_MINUTE);
    }

    /**
     * @param non-negative-int $value
     */
    public static function seconds(int $value): self
    {
        return new self($value, self::TYPE_SECOND);
    }

    /**
     * @param non-negative-int $value
     */
    public static function milliseconds(int $value): self
    {
        return new self($value, self::TYPE_MILLISECOND);
    }

    /**
     * @param non-negative-int $value
     */
    public static function microseconds(int $value): self
    {
        return new self($value, self::TYPE_MICROSECOND);
    }

    /**
     * @param non-negative-int $value
     */
    public static function nanoseconds(int $value): self
    {
        return new self($value, self::TYPE_NANOSECOND);
    }

    public static function fromInterval(
        \DateInterval $interval,
        \DateTimeImmutable $time = new \DateTimeImmutable('NOW'),
    ): self {
        return self::fromDateTime($time->add($interval), $time);
    }

    public static function fromDateTime(
        \DateTimeImmutable $deadline,
        \DateTimeImmutable $time = new \DateTimeImmutable('NOW'),
    ): self {
        return self::seconds(max($deadline->getTimestamp() - $time->getTimestamp(), 0));
    }

    public function toSeconds(): float
    {
        return match ($this->unit) {
            self::TYPE_HOUR => 3_600 * $this->value,
            self::TYPE_MINUTE => 60 * $this->value,
            self::TYPE_SECOND => $this->value,
            self::TYPE_MILLISECOND => 0.001 * $this->value,
            self::TYPE_MICROSECOND => 0.000_001 * $this->value,
            self::TYPE_NANOSECOND => 0.000_000_001 * $this->value,
        };
    }

    #[\Override]
    public function append(Metadata $md): Metadata
    {
        return $md->replace(self::HEADER, (string) $this);
    }

    #[\Override]
    public function __toString(): string
    {
        return \sprintf('%d%s', $this->value, $this->unit);
    }
}

/**
 * @internal
 */
function parseTimeout(Metadata $md): ?Timeout
{
    $timeout = $md->value(Timeout::HEADER) ?? '';

    if (\strlen($timeout) < 2) {
        return null;
    }

    if (\strlen($timeout) > 9) {
        return null;
    }

    $unit = $timeout[\strlen($timeout) - 1];
    if (!isset(Timeout::UNITS[$unit])) {
        return null;
    }

    $value = substr($timeout, 0, \strlen($timeout) - 1);

    if (!is_numeric($value)) {
        return null;
    }

    $factory = match ($unit) {
        Timeout::TYPE_HOUR => Timeout::hours(...),
        Timeout::TYPE_MINUTE => Timeout::minutes(...),
        Timeout::TYPE_SECOND => Timeout::seconds(...),
        Timeout::TYPE_MILLISECOND => Timeout::milliseconds(...),
        Timeout::TYPE_MICROSECOND => Timeout::microseconds(...),
        Timeout::TYPE_NANOSECOND => Timeout::nanoseconds(...),
    };

    return $factory(max((int) $value, 0));
}
