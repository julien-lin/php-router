<?php

namespace JulienLinard\Router;

class Route
{
  private string $method;
  private string $path;
  private array $handler;
  private array $middlewares;

  public function __construct(string $method, string $path, array $handler, array $middlewares = [])
  {
    $this->method = $method;
    $this->path = $path;
    $this->handler = $handler;
    $this->middlewares = $middlewares;
  }

  public function matches(Request $request): bool
  {
    return $this->method === $request->getMethod() && $this->path === $request->getPath();
  }

  public function run(Request $request): Response
  {
    foreach ($this->middlewares as $middleware) {
      if (is_array($middleware)) {
        $middlewareClass = $middleware[0];
        $middlewareArgs = $middleware[1] ?? [];
        $middlewareInstance = new $middlewareClass(...$middlewareArgs);
      } else {
        $middlewareInstance = new $middleware();
      }
      $middlewareInstance->handle($request);
    }

    $controller = new $this->handler[0]();
    return call_user_func([$controller, $this->handler[1]], $request);
  }
}
