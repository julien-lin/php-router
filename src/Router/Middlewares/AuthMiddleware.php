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
  public function handle(Request $request): ?Response
  {
    $this->ensureSessionStarted();
    
    if (!isset($_SESSION['user'])) {
      return Response::json(['error' => 'Unauthorized'], 401);
    }
    
    return null; // Continuer l'exécution
  }
}
