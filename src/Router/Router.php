<?php

namespace JulienLinard\Router;

use ReflectionClass;
use JulienLinard\Router\Attributes\Route;

class Router
{
  private array $routes = [];
  private array $middlewares = [];

  public function addRoute(string $method, string $path, array $handler, array $middlewares = []): void
  {
    $this->routes[] = new Route($method, $path, $handler, $middlewares);
  }

  public function addMiddleware(Middleware $middleware): void
  {
    $this->middlewares[] = $middleware;
  }

  public function registerRoutes(string $controller): void
  {
    $reflection = new ReflectionClass($controller);
    foreach ($reflection->getMethods() as $method) {
      $attributes = $method->getAttributes(Route::class);
      foreach ($attributes as $attribute) {
        $route = $attribute->newInstance();
        $this->routes[$route->path] = [$controller, $method->getName()];
      }
    }
  }

  public function handle(Request $request): Response
  {
    foreach ($this->middlewares as $middleware) {
      $middleware->handle($request);
    }

    $path = $request->getPath();
    if (isset($this->routes[$path])) {
      [$controller, $method] = $this->routes[$path];
      return (new $controller)->$method($request);
    }

    return new Response(404, 'Not Found');
  }
}
