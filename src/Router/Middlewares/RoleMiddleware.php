<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Middlewares\Traits\SessionAware;

class RoleMiddleware implements Middleware
{
  use SessionAware;

  private string $requiredRole;

  /**
   * @param string $requiredRole Le rôle requis pour accéder à la route
   */
  public function __construct(string $requiredRole)
  {
    $this->requiredRole = $requiredRole;
  }

  /**
   * Vérifie si l'utilisateur a le rôle requis
   */
  public function handle(Request $request): ?Response
  {
    $this->ensureSessionStarted();
    
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== $this->requiredRole) {
      return Response::json(['error' => 'Access denied'], 403);
    }
    
    return null; // Continuer l'exécution
  }
}
