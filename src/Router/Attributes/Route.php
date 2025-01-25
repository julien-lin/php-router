<?php

namespace JulienLinard\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
  public string $path;
  public string $name;
  public array $methods;
  public array $middleware;

  public function __construct(string $path, string $name = '', array $methods = ['GET'], array $middleware = [])
  {
    $this->path = $path;
    $this->name = $name;
    $this->methods = $methods;
    $this->middleware = $middleware;
  }
}
