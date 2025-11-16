<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class RoleMiddleware implements Middleware
{
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
  public function handle(Request $request): void
  {
    $this->ensureSessionStarted();
    
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== $this->requiredRole) {
      Response::json(['error' => 'Access denied'], 403)->send();
      exit;
    }
  }

  /**
   * Démarre la session si elle n'est pas déjà démarrée
   */
  private function ensureSessionStarted(): void
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
  }
}
