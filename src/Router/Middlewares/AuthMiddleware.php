<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class AuthMiddleware implements Middleware
{
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
