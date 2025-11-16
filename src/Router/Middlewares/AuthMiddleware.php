<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Middlewares\Traits\SessionAware;

class AuthMiddleware implements Middleware
{
  use SessionAware;

  /**
   * Vérifie si l'utilisateur est authentifié
   */
  public function handle(Request $request): void
  {
    $this->ensureSessionStarted();
    
    if (!isset($_SESSION['user'])) {
      Response::json(['error' => 'Unauthorized'], 401)->send();
      exit;
    }
  }
}
