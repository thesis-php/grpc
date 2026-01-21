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
    private const string TYPE_HOUR = 'H';
    private const string TYPE_MINUTE = 'M';
    private const string TYPE_SECOND = 'S';
    private const string TYPE_MILLISECOND = 'm';
    private const string TYPE_MICROSECOND = 'u';
    private const string TYPE_NANOSECOND  = 'n';

    /** @var non-empty-array<self::TYPE_*, 1> */
    private const array UNITS = [
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

    /**
     * @param non-empty-string $timeout
     */
    public static function fromString(string $timeout): self
    {
        if (\strlen($timeout) < 2) {
            throw new \InvalidArgumentException("Timeout spec '{$timeout}' is too short.");
        }

        if (\strlen($timeout) > 9) {
            throw new \InvalidArgumentException("Timeout spec '{$timeout}' is too long.");
        }

        $unit = $timeout[\strlen($timeout) - 1];
        if (!isset(self::UNITS[$unit])) {
            throw new \InvalidArgumentException("Timeout unit '{$unit}' is not recognized.");
        }

        $value = substr($timeout, 0, \strlen($timeout) - 1);

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Timeout value '{$value}' is not a valid number.");
        }

        $factory = match ($unit) {
            self::TYPE_HOUR => self::hours(...),
            self::TYPE_MINUTE => self::minutes(...),
            self::TYPE_SECOND => self::seconds(...),
            self::TYPE_MILLISECOND => self::milliseconds(...),
            self::TYPE_MICROSECOND => self::microseconds(...),
            self::TYPE_NANOSECOND => self::nanoseconds(...),
        };

        return $factory(max((int) $value, 0));
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
        return $md->with('grpc-timeout', (string) $this);
    }

    #[\Override]
    public function __toString(): string
    {
        return \sprintf('%d%s', $this->value, $this->unit);
    }
}
