<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class RoleMiddleware implements Middleware
{
  private string $requiredRole;

  public function __construct(string $requiredRole)
  {
    $this->requiredRole = $requiredRole;
  }

  public function handle(Request $request): void
  {

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $this->requiredRole) {
      Response::json(['error' => 'Accès denied'], 403)->send();
      exit;
    }
  }
}
