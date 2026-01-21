<?php

declare(strict_types=1);

namespace Thesis\Grpc;

/**
 * @api
 * @template-implements \IteratorAggregate<non-empty-string, list<string>>
 * @template-implements \ArrayAccess<non-empty-string, list<string>>
 */
final class Metadata implements
    \ArrayAccess,
    \IteratorAggregate,
    \Countable
{
    /** @var array<non-empty-string, list<string>> */
    public private(set) array $kv = [];

    /**
     * @param array<non-empty-string, string|list<string>> $kv
     */
    public function __construct(#[\SensitiveParameter] array $kv = [])
    {
        foreach ($kv as $key => $values) {
            if (\is_string($values)) {
                $values = [$values];
            }

            $this->kv[strtolower($key)] = $values;
        }
    }

    /**
     * @no-named-arguments
     * @param non-empty-string $key
     */
    public function with(string $key, #[\SensitiveParameter] string ...$values): self
    {
        $key = strtolower($key);

        $md = clone $this;
        $md->kv[$key] = [
            ...$md->kv[$key] ?? [],
            ...$values,
        ];

        return $md;
    }

    public function withKeys(Metadata\MetadataKey ...$keys): self
    {
        $md = clone $this;

        foreach ($keys as $key) {
            $md = $key->append($md);
        }

        return $md;
    }

    /**
     * @param non-empty-string $key
     */
    public function without(string $key): self
    {
        $key = strtolower($key);

        $md = clone $this;
        unset($md->kv[$key]);

        return $md;
    }

    public function merge(self $other): self
    {
        $md = clone $this;

        foreach ($other->kv as $key => $values) {
            $md->kv[$key] = [
                ...$md->kv[$key] ?? [],
                ...$values,
            ];
        }

        return $md;
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->kv;
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->kv);
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->kv[strtolower($offset)]);
    }

    #[\Override]
    public function offsetGet(mixed $offset): array
    {
        return $this->kv[strtolower($offset)] ?? [];
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Metadata is immutable.');
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Metadata is immutable.');
    }
}
