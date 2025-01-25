<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class CorsMiddleware implements Middleware
{
  public function handle(Request $request): void
  {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($request->getMethod() === 'OPTIONS') {
      Response::json([], 204)->send();
      exit;
    }
  }
}
