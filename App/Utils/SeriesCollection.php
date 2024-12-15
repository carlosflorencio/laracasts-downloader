<?php

namespace App\Utils;

class SeriesCollection
{
    public function __construct(private array $series) {}

    public function where(string $key, string $value): self
    {
        $series = [];

        foreach ($this->series as $serie) {
            if ($serie[$key] == $value) {
                $series[] = $serie;
            }
        }

        return new SeriesCollection($series);
    }

    public function sum($key, $actual): int
    {
        $sum = 0;

        foreach ($this->series as $serie) {
            if ($actual) {
                $sum += intval(count($serie[str_replace('_count', '', $key).'s']));
            } else {
                $sum += intval($serie[$key]);
            }
        }

        return $sum;
    }

    public function count(): int
    {
        return count($this->series);
    }

    public function get(): array
    {
        return $this->series;
    }

    public function exists(): bool
    {
        return $this->series !== [];
    }

    public function first()
    {
        return $this->exists() ? $this->series[0] : null;
    }

    public function add(array $serie): void
    {
        $this->series[$serie['slug']] = $serie;
    }
}
