<?php

namespace App\Utils;

class SeriesCollection
{
    public function __construct(private array $series) {}

    /**
     * @param  string  $key
     * @param  string  $value
     * @return $this
     */
    public function where($key, $value)
    {
        $series = [];

        foreach ($this->series as $serie) {
            if ($serie[$key] == $value) {
                $series[] = $serie;
            }
        }

        return new SeriesCollection($series);
    }

    public function sum($key, $actual)
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

    public function count()
    {
        return count($this->series);
    }

    public function get()
    {
        return $this->series;
    }

    public function exists()
    {
        return ! empty($this->series);
    }

    public function first()
    {
        return $this->exists() ? $this->series[0] : null;
    }

    public function add($serie)
    {
        $this->series[$serie['slug']] = $serie;
    }
}
