<?php

declare(strict_types=1);

namespace Thesis\Grpc\Interop;

final readonly class Flags
{
    /**
     * @param array<string, string> $values
     */
    private function __construct(
        private array $values,
    ) {}

    /**
     * Accepts `--name=value` for every flag that has a default.
     *
     * @param array<non-empty-string, string> $defaults
     */
    public static function parse(array $defaults): self
    {
        $flags = getopt('', array_map(static fn(string $name) => "{$name}:", array_keys($defaults)));

        return new self([
            ...$defaults,
            ...array_filter($flags === false ? [] : $flags, \is_string(...)),
        ]);
    }

    /**
     * @param non-empty-string $name
     */
    public function string(string $name): string
    {
        return $this->values[$name] ?? throw new \InvalidArgumentException("Unknown flag --{$name}.");
    }

    /**
     * @param non-empty-string $name
     */
    public function int(string $name): int
    {
        $value = $this->string($name);

        return is_numeric($value) ? (int) $value : throw new \InvalidArgumentException("Flag --{$name} must be an integer.");
    }

    /**
     * @param non-empty-string $name
     */
    public function bool(string $name): bool
    {
        return match ($this->string($name)) {
            'true' => true,
            'false' => false,
            default => throw new \InvalidArgumentException("Flag --{$name} must be true or false."),
        };
    }
}
