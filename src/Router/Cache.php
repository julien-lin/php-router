<?php

namespace JulienLinard\Router;

class Cache
{
  private array $cache = [];

  public function get(string $key)
  {
    return $this->cache[$key] ?? null;
  }

  public function set(string $key, $value): void
  {
    $this->cache[$key] = $value;
  }
}
