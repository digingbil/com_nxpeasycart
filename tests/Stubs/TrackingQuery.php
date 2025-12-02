<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Joomla\Database\ParameterType;

/**
 * Lightweight query stub that records bound parameters.
 */
class TrackingQuery
{
    /**
     * @var array<string, mixed>
     */
    public array $bindings = [];

    public function select($columns): self
    {
        return $this;
    }

    public function from($table): self
    {
        return $this;
    }

    public function leftJoin($clause): self
    {
        return $this;
    }

    public function innerJoin($clause): self
    {
        return $this;
    }

    public function where($condition): self
    {
        return $this;
    }

    public function order($ordering): self
    {
        return $this;
    }

    public function group($grouping): self
    {
        return $this;
    }

    public function setLimit($limit, $offset = 0): self
    {
        return $this;
    }

    public function bind($key, &$value, $dataType = ParameterType::STRING, $length = 0, $options = []): self
    {
        $this->bindings[(string) $key] = $value;

        return $this;
    }

    public function clear(): self
    {
        $this->bindings = [];

        return $this;
    }
}
