<?php

namespace JulienLinard\Router;

class Request
{
  private string $path;
  private string $method;

  public function __construct()
  {
    $this->path = $_SERVER['REQUEST_URI'] ?? '/';
    $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  }

  public function getPath(): string
  {
    return $this->path;
  }

  public function getMethod(): string
  {
    return $this->method;
  }
}
