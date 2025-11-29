<?php

declare(strict_types=1);

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class LoggingMiddleware implements Middleware
{
  public function handle(Request $request): ?Response
  {
    error_log($request->getMethod() . ' ' . $request->getPath());
    return null; // Continuer l'exécution
  }
}
