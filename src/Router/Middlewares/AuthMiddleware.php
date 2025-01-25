<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class AuthMiddleware implements Middleware
{
  public function handle(Request $request): void
  {
    session_start();
    if (!isset($_SESSION['user'])) {
      Response::json(['error' => 'Unauthorized'], 401)->send();
      exit;
    }
  }
}
