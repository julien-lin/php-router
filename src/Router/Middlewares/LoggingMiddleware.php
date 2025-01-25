<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;

class LoggingMiddleware implements Middleware
{
  public function handle(Request $request): void
  {
    error_log($request->getMethod() . ' ' . $request->getPath());
  }
}
